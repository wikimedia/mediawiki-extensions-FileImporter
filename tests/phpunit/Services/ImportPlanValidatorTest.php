<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\SourceUrl;
use FileImporter\Data\TextRevisions;
use FileImporter\Exceptions\DuplicateFilesException;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\RecoverableTitleException;
use FileImporter\Exceptions\TitleException;
use FileImporter\Interfaces\ImportTitleChecker;
use FileImporter\Remote\MediaWiki\CommonsHelperConfigRetriever;
use FileImporter\Remote\MediaWiki\HttpApiLookup;
use FileImporter\Services\DuplicateFileRevisionChecker;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\ImportPlanValidator;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\UploadBase\ValidatingUploadBase;
use FileImporter\Services\Wikitext\WikiLinkParser;
use FileImporter\Services\Wikitext\WikiLinkParserFactory;
use MalformedTitleException;
use MediaWiki\Linker\LinkTarget;
use MediaWikiLangTestCase;
use Title;
use TitleValue;
use UploadBase;

/**
 * @covers \FileImporter\Services\ImportPlanValidator
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPlanValidatorTest extends MediaWikiLangTestCase {

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( [
			'wgHooks' => [],
			'wgGroupPermissions' => [ '*' => [ 'upload' => true ] ],
		] );

		// FIXME: The following can be removed when the services are injected via the constructor.
		$httpRequestExecutor = $this->createMock( HttpRequestExecutor::class );
		$httpRequestExecutor->method( 'execute' )
			->willReturn( $this->createMock( \MWHttpRequest::class ) );
		$this->setService( 'FileImporterHttpRequestExecutor', $httpRequestExecutor );

		$httpApiLookup = $this->createMock( HttpApiLookup::class );
		$this->setService( 'FileImporterMediaWikiHttpApiLookup', $httpApiLookup );
	}

	/**
	 * @param int $callCount
	 * @param bool $allowed
	 *
	 * @return ImportTitleChecker
	 */
	private function getMockImportTitleChecker( $callCount = 0, $allowed = true ) {
		$mock = $this->createMock( ImportTitleChecker::class );
		$mock->expects( $this->exactly( $callCount ) )
			->method( 'importAllowed' )
			->willReturn( $allowed );
		return $mock;
	}

	/**
	 * @param int $callCount
	 * @param int $arrayElements
	 *
	 * @return DuplicateFileRevisionChecker
	 */
	private function getMockDuplicateFileRevisionChecker( $callCount = 0, $arrayElements = 0 ) {
		$mock = $this->createMock( DuplicateFileRevisionChecker::class );
		$mock->expects( $this->exactly( $callCount ) )
			->method( 'findDuplicates' )
			->willReturn( array_fill( 0, $arrayElements, 'value' ) );
		return $mock;
	}

	/**
	 * @param string $text
	 * @param bool $exists
	 *
	 * @return Title
	 */
	private function getMockTitle( $text, $exists = false ) {
		$mock = $this->createMock( Title::class );
		$mock->method( 'getText' )
			->willReturn( $text );
		$mock->method( 'exists' )
			->willReturn( $exists );
		$mock->method( 'getRestrictions' )
			->willReturn( [] );
		return $mock;
	}

	/**
	 * @return FileRevisions
	 */
	private function getMockFileRevisions() {
		$mock = $this->createMock( FileRevisions::class );
		$mockFileRevision = $this->createMock( FileRevision::class );
		$mock->method( 'getLatest' )
			->willReturn( $mockFileRevision );
		return $mock;
	}

	/**
	 * @param LinkTarget $sourceTitle
	 *
	 * @return ImportDetails
	 */
	private function getMockImportDetails( LinkTarget $sourceTitle ) {
		$details = new ImportDetails(
			new SourceUrl( '//w.invalid' ),
			$sourceTitle,
			$this->createMock( TextRevisions::class ),
			$this->getMockFileRevisions()
		);
		$details->setPageLanguage( 'de' );
		return $details;
	}

	/**
	 * @param Title|false $planTitle False makes {@see ImportPlan::getTitle} throw an exception.
	 * @param LinkTarget|null $sourceTitle Defaults to a valid title.
	 *
	 * @return ImportPlan
	 */
	private function getMockImportPlan( $planTitle, LinkTarget $sourceTitle = null ) {
		$mock = $this->getMockBuilder( ImportPlan::class )
			->setConstructorArgs( [
				new ImportRequest( '//w.invalid' ),
				$this->getMockImportDetails( $sourceTitle ?: $this->getMockTitle( 'Source.JPG' ) ),
				''
			] )
			// This makes this a partial mock where all other methods still call the original code.
			->setMethods( [ 'getTitle' ] )
			->getMock();

		if ( $planTitle ) {
			$mock->method( 'getTitle' )
				->willReturn( $planTitle );
		} else {
			$mock->method( 'getTitle' )
				->willThrowException( new MalformedTitleException( 'mockexception' ) );
		}

		return $mock;
	}

	/**
	 * @param UploadBase $uploadBase
	 *
	 * @return UploadBaseFactory
	 */
	private function getMockUploadBaseFactory( UploadBase $uploadBase ) {
		$mock = $this->createMock( UploadBaseFactory::class );
		$mock->method( 'newValidatingUploadBase' )
			->willReturn( $uploadBase );
		return $mock;
	}

	/**
	 * @param bool $validTitle
	 * @param bool $validFile
	 *
	 * @return ValidatingUploadBase
	 */
	private function getMockValidatingUploadBase( $validTitle = true, $validFile = true ) {
		$mock = $this->createMock( ValidatingUploadBase::class );
		$mock->method( 'validateTitle' )
			->willReturn( $validTitle );
		$mock->method( 'validateFile' )
			->willReturn( $validFile );
		return $mock;
	}

	/**
	 * @param int $callCount
	 * @param WikiLinkParser|null $wikiLinkParser
	 *
	 * @return WikiLinkParserFactory
	 */
	private function getMockWikiLinkParserFactory(
		$callCount = 0,
		WikiLinkParser $wikiLinkParser = null
	) {
		$mock = $this->createMock( WikiLinkParserFactory::class );
		$mock->expects( $this->exactly( $callCount ) )
			->method( 'getWikiLinkParser' )
			->willReturn( $wikiLinkParser ?: new WikiLinkParser() );
		return $mock;
	}

	public function provideValidate() {
		$emptyPlan = $this->getMockImportPlan( false );

		$allowedFileTitles = [
			'Regular name' => 'SourceName.JPG',
			'Multiple extensions are allowed' => 'SourceName.JPG.JPG',
			'Change of extension case is allowed' => 'SourceName.jpg',
		];
		$validTests = [];
		foreach ( $allowedFileTitles as $description => $titleString ) {
			$validTests["Valid Plan - '$titleString' ($description)"] = [
				null,
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG' ),
					$this->getMockTitle( $titleString )
				),
				$this->getMockDuplicateFileRevisionChecker( 1 ),
				$this->getMockImportTitleChecker( 1 ),
				$this->getMockWikiLinkParserFactory( 1 )
			];
		}

		$invalidTests = [
			'Invalid, duplicate file found' => [
				new DuplicateFilesException( [ 'someFile' ] ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG' )
				),
				$this->getMockDuplicateFileRevisionChecker( 1, 1 ),
				$this->getMockImportTitleChecker(),
				$this->getMockWikiLinkParserFactory()
			],
			'Invalid, title exists on target site' => [
				new RecoverableTitleException(
					'fileimporter-localtitleexists',
					$emptyPlan
				),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG', true )
				),
				$this->getMockDuplicateFileRevisionChecker( 1 ),
				$this->getMockImportTitleChecker(),
				$this->getMockWikiLinkParserFactory( 1 )
			],
			'Invalid, title exists on source site' => [
				new RecoverableTitleException(
					'fileimporter-sourcetitleexists',
					$emptyPlan
				),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG' )
				),
				$this->getMockDuplicateFileRevisionChecker( 1 ),
				$this->getMockImportTitleChecker( 1, false ),
				$this->getMockWikiLinkParserFactory( 1 )
			],
			'Invalid, file extension has changed' => [
				new TitleException( 'fileimporter-filenameerror-missmatchextension' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG' ),
					$this->getMockTitle( 'SourceName.PNG' )
				),
				$this->getMockDuplicateFileRevisionChecker(),
				$this->getMockImportTitleChecker(),
				$this->getMockWikiLinkParserFactory()
			],
			'Invalid, No Extension on planned name' => [
				new TitleException( 'fileimporter-filenameerror-noplannedextension' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG/Foo' )
				),
				$this->getMockDuplicateFileRevisionChecker(),
				$this->getMockImportTitleChecker(),
				$this->getMockWikiLinkParserFactory()
			],
			'Invalid, No Extension on source name' => [
				new TitleException( 'fileimporter-filenameerror-nosourceextension' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG' ),
					$this->getMockTitle( 'SourceName.jpg/Foo' )
				),
				$this->getMockDuplicateFileRevisionChecker(),
				$this->getMockImportTitleChecker(),
				$this->getMockWikiLinkParserFactory()
			],
			'Invalid, Bad title (includes another namespace)' => [
				new RecoverableTitleException(
					'fileimporter-illegalfilenamechars',
					$emptyPlan
				),
				$this->getMockImportPlan(
					$this->getMockTitle( 'Talk:FinalName.JPG' )
				),
				$this->getMockDuplicateFileRevisionChecker( 1 ),
				$this->getMockImportTitleChecker(),
				$this->getMockWikiLinkParserFactory( 1 )
			],
			'Invalid, Bad filename (too long)' => [
				new RecoverableTitleException(
					'fileimporter-filenameerror-toolong',
					$emptyPlan
				),
				$this->getMockImportPlan(
					$this->getMockTitle( str_repeat( 'a', 242 ) . '.JPG' )
				),
				$this->getMockDuplicateFileRevisionChecker( 1 ),
				$this->getMockImportTitleChecker(),
				$this->getMockWikiLinkParserFactory( 1 ),
				$this->getMockValidatingUploadBase( UploadBase::FILENAME_TOO_LONG, true )
			],
			'Invalid, Bad characters "<", getTitle throws MalformedTitleException' => [
				new RecoverableTitleException(
					'mockexception',
					$emptyPlan
				),
				$this->getMockImportPlan( false ),
				$this->getMockDuplicateFileRevisionChecker(),
				$this->getMockImportTitleChecker(),
				$this->getMockWikiLinkParserFactory(),
				$this->getMockValidatingUploadBase( true, true )
			],
		];

		return $validTests + $invalidTests;
	}

	/**
	 * @dataProvider provideValidate
	 */
	public function testValidate(
		ImportException $expected = null,
		ImportPlan $plan,
		DuplicateFileRevisionChecker $duplicateChecker,
		ImportTitleChecker $titleChecker,
		WikiLinkParserFactory $wikiLinkParserFactory,
		ValidatingUploadBase $validatingUploadBase = null
	) {
		if ( $validatingUploadBase === null ) {
			$validatingUploadBase = $this->getMockValidatingUploadBase();
		}

		$validator = new ImportPlanValidator(
			$duplicateChecker,
			$titleChecker,
			$this->getMockUploadBaseFactory( $validatingUploadBase ),
			null,
			null,
			$wikiLinkParserFactory
		);

		if ( $expected !== null ) {
			$this->setExpectedException( get_class( $expected ), $expected->getMessage() );
		}
		$validator->validate( $plan, $this->getTestUser()->getUser() );
		$this->addToAssertionCount( 1 );
	}

	public function testValidateFailsWhenCoreChangesTheName() {
		$mockRequest = $this->createMock( ImportRequest::class );
		$mockRequest->expects( $this->atLeastOnce() )
			->method( 'getIntendedName' )
			->willReturn( 'Before.jpg#After' );
		$mockDetails = $this->getMockImportDetails( new TitleValue( NS_FILE, 'SourceName.jpg' ) );

		$importPlan = new ImportPlan( $mockRequest, $mockDetails, '' );

		$validator = new ImportPlanValidator(
			$this->getMockDuplicateFileRevisionChecker( 1 ),
			$this->getMockImportTitleChecker(),
			$this->getMockUploadBaseFactory( $this->getMockValidatingUploadBase() ),
			null,
			null,
			$this->getMockWikiLinkParserFactory( 1 )
		);

		$this->setExpectedException( RecoverableTitleException::class, '"Before"' );
		$validator->validate( $importPlan, $this->getTestUser()->getUser() );
	}

	public function testValidateFailsOnFailingPermissionCheck() {
		$this->setMwGlobals( [
			'wgGroupPermissions' => [ '*' => [ 'upload' => false ] ],
		] );
		$importRequest = new ImportRequest( '//w.invalid' );
		$mockTitle = $this->getMockTitle( 'Title.jpg', true );
		$mockDetails = $this->getMockImportDetails( $mockTitle );

		$importPlan = new ImportPlan( $importRequest, $mockDetails, '' );

		$validator = new ImportPlanValidator(
			$this->getMockDuplicateFileRevisionChecker(),
			$this->getMockImportTitleChecker(),
			$this->getMockUploadBaseFactory( $this->getMockValidatingUploadBase() ),
			null,
			null,
			$this->getMockWikiLinkParserFactory()
		);

		$this->setExpectedException(
			RecoverableTitleException::class,
			'You are not allowed to execute the action you have requested'
		);
		$validator->validate( $importPlan, $this->getTestUser()->getUser() );
	}

	public function testCommonsHelperAndWikiLinkParserIntegration() {
		$this->setTemporaryHook( 'TitleExists', function ( LinkTarget $title, &$exists ) {
			$this->assertSame( 'De', $title->getDBkey() );
			$exists = true;
		} );

		$importPlan = $this->getMockImportPlan( $this->getMockTitle( 'Valid.jpg' ) );
		$importPlan->setCleanedLatestRevisionText( "{{MOVE}}\n{{INFO|foo}}" );

		$commonsHelperConfigRetriever = $this->createMock( CommonsHelperConfigRetriever::class );
		$commonsHelperConfigRetriever->expects( $this->once() )
			->method( 'retrieveConfiguration' )
			->willReturn( true );
		$commonsHelperConfigRetriever->method( 'getConfigWikitext' )
			->willReturn( "==Categories==\n===Bad===\n" .
				"==Templates==\n===Good===\n===Bad===\n===Remove===\n*MOVE\n===Transfer===\n" .
				";INFO:INFO|@desc=1\n==Information==\n===Description===\n===Licensing===" );

		$wikiLinkParser = $this->createMock( WikiLinkParser::class );
		$wikiLinkParser->expects( $this->once() )
			->method( 'parse' )
			->with( '{{INFO|desc={{de|foo}}}}' )
			->willReturn( 'PARSED' );

		$validator = new ImportPlanValidator(
			$this->getMockDuplicateFileRevisionChecker( 1 ),
			$this->getMockImportTitleChecker( 1 ),
			$this->getMockUploadBaseFactory( $this->getMockValidatingUploadBase() ),
			$commonsHelperConfigRetriever,
			'',
			$this->getMockWikiLinkParserFactory( 1, $wikiLinkParser )
		);

		$validator->validate( $importPlan, $this->getTestUser()->getUser() );
		$this->assertSame( 'PARSED', $importPlan->getCleanedLatestRevisionText() );
		$this->assertSame( 2, $importPlan->getNumberOfTemplateReplacements() );
	}

}
