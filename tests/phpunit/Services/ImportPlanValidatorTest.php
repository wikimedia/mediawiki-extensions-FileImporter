<?php

namespace FileImporter\Services\Test;

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
use Title;
use UploadBase;
use User;

/**
 * @covers \FileImporter\Services\ImportPlanValidator
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ImportPlanValidatorTest extends \MediaWikiTestCase {

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( 'wgHooks', [] );

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
	private function getMockImportTitleChecker( $callCount, $allowed = true ) {
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
	private function getMockDuplicateFileRevisionChecker( $callCount, $arrayElements = 0 ) {
		$mock = $this->createMock( DuplicateFileRevisionChecker::class );
		$mock->expects( $this->exactly( $callCount ) )
			->method( 'findDuplicates' )
			->willReturn( array_fill( 0, $arrayElements, 'value' ) );
		return $mock;
	}

	/**
	 * @param string $text
	 * @param bool $exists
	 * @param array $userPermissionsErrors
	 *
	 * @return Title
	 */
	private function getMockTitle( $text, $exists = false, array $userPermissionsErrors = [] ) {
		$mock = $this->createMock( Title::class );
		$mock->method( 'getText' )
			->willReturn( $text );
		$mock->method( 'exists' )
			->willReturn( $exists );
		$mock->method( 'getUserPermissionsErrors' )
			->willReturn( $userPermissionsErrors );
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
		return new ImportDetails(
			new SourceUrl( 'http://test.test' ),
			$sourceTitle,
			$this->createMock( TextRevisions::class ),
			$this->getMockFileRevisions()
		);
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
				new ImportRequest( 'http://test.test' ),
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
	 * @param WikiLinkParser|null $wikiLinkParser
	 *
	 * @return WikiLinkParserFactory
	 */
	private function getMockWikiLinkParserFactory( WikiLinkParser $wikiLinkParser = null ) {
		$mock = $this->createMock( WikiLinkParserFactory::class );
		$mock->expects( $this->once() )
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
				$this->getMockImportTitleChecker( 1 )
			];
		}

		$invalidTests = [
			'Invalid, duplicate file found' => [
				new DuplicateFilesException( [ 'someFile' ] ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG' )
				),
				$this->getMockDuplicateFileRevisionChecker( 1, 1 ),
				$this->getMockImportTitleChecker( 0 )
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
				$this->getMockImportTitleChecker( 0 )
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
				$this->getMockImportTitleChecker( 1, false )
			],
			'Invalid, file extension has changed' => [
				new TitleException( 'fileimporter-filenameerror-missmatchextension' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG' ),
					$this->getMockTitle( 'SourceName.PNG' )
				),
				$this->getMockDuplicateFileRevisionChecker( 0 ),
				$this->getMockImportTitleChecker( 0 )
			],
			'Invalid, No Extension on planned name' => [
				new TitleException( 'fileimporter-filenameerror-noplannedextension' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG/Foo' )
				),
				$this->getMockDuplicateFileRevisionChecker( 0 ),
				$this->getMockImportTitleChecker( 0 )
			],
			'Invalid, No Extension on source name' => [
				new TitleException( 'fileimporter-filenameerror-nosourceextension' ),
				$this->getMockImportPlan(
					$this->getMockTitle( 'FinalName.JPG' ),
					$this->getMockTitle( 'SourceName.jpg/Foo' )
				),
				$this->getMockDuplicateFileRevisionChecker( 0 ),
				$this->getMockImportTitleChecker( 0 )
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
				$this->getMockImportTitleChecker( 0 )
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
				$this->getMockImportTitleChecker( 0 ),
				$this->getMockValidatingUploadBase( UploadBase::FILENAME_TOO_LONG, true )
			],
			'Invalid, Bad characters "<", getTitle throws MalformedTitleException' => [
				new RecoverableTitleException(
					'mockexception',
					$emptyPlan
				),
				$this->getMockImportPlan( false ),
				$this->getMockDuplicateFileRevisionChecker( 0 ),
				$this->getMockImportTitleChecker( 0 ),
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
		ValidatingUploadBase $validatingUploadBase = null
	) {
		if ( $validatingUploadBase === null ) {
			$validatingUploadBase = $this->getMockValidatingUploadBase();
		}

		if ( $expected !== null ) {
			$this->setExpectedException( get_class( $expected ), $expected->getMessage() );
		}

		$validator = new ImportPlanValidator(
			$duplicateChecker,
			$titleChecker,
			$this->getMockUploadBaseFactory( $validatingUploadBase ),
			null,
			null,
			$this->getMockWikiLinkParserFactory()
		);
		$validator->validate( $plan, $this->createMock( User::class ) );

		if ( $expected === null ) {
			$this->addToAssertionCount( 1 );
		}
	}

	public function testValidateFailsWhenCoreChangesTheName() {
		$mockRequest = $this->createMock( ImportRequest::class );
		$mockRequest->expects( $this->atLeastOnce() )
			->method( 'getIntendedName' )
			->willReturn( 'Before#After' );
		$mockDetails = $this->getMockImportDetails( Title::makeTitle( NS_FILE, __METHOD__ ) );

		$importPlan = new ImportPlan( $mockRequest, $mockDetails, '' );

		$this->setExpectedException( RecoverableTitleException::class, '"Before"' );

		$validator = new ImportPlanValidator(
			$this->getMockDuplicateFileRevisionChecker( 0 ),
			$this->getMockImportTitleChecker( 0 ),
			$this->getMockUploadBaseFactory( $this->getMockValidatingUploadBase() ),
			null,
			null,
			$this->getMockWikiLinkParserFactory()
		);
		$validator->validate( $importPlan, $this->createMock( User::class ) );
	}

	public function testValidateFailsOnFailingTitlePermissionCheck() {
		$importRequest = new ImportRequest( 'http://test.test' );
		$mockTitle = $this->getMockTitle( 'Title', true, [ 'error' ] );
		$mockDetails = $this->getMockImportDetails( $mockTitle );

		$importPlan = new ImportPlan( $importRequest, $mockDetails, '' );

		$this->setExpectedException(
			RecoverableTitleException::class,
			'The action you have requested is limited to users in one of the groups'
		);

		$validator = new ImportPlanValidator(
			$this->getMockDuplicateFileRevisionChecker( 0 ),
			$this->getMockImportTitleChecker( 0 ),
			$this->getMockUploadBaseFactory( $this->getMockValidatingUploadBase() ),
			null,
			null,
			$this->getMockWikiLinkParserFactory()
		);
		$validator->validate( $importPlan, $this->createMock( User::class ) );
	}

	public function testCommonsHelperAndWikiLinkParserIntegration() {
		$importPlan = $this->getMockImportPlan( $this->getMockTitle( 'Valid.jpg' ) );
		$importPlan->setCleanedLatestRevisionText( "{{MOVE}}\nORIGINAL" );

		$commonsHelperConfigRetriever = $this->createMock( CommonsHelperConfigRetriever::class );
		$commonsHelperConfigRetriever->expects( $this->once() )
			->method( 'retrieveConfiguration' )
			->willReturn( true );
		$commonsHelperConfigRetriever->method( 'getConfigWikitext' )
			->willReturn( "==Categories==\n===Bad===\n" .
				"==Templates==\n===Good===\n===Bad===\n===Remove===\n*MOVE\n===Transfer===\n" .
				"==Information==\n===Description===\n===Licensing===" );

		$wikiLinkParser = $this->createMock( WikiLinkParser::class );
		$wikiLinkParser->expects( $this->once() )
			->method( 'parse' )
			->with( 'ORIGINAL' )
			->willReturn( 'PARSED' );

		$validator = new ImportPlanValidator(
			$this->getMockDuplicateFileRevisionChecker( 1 ),
			$this->getMockImportTitleChecker( 1 ),
			$this->getMockUploadBaseFactory( $this->getMockValidatingUploadBase() ),
			$commonsHelperConfigRetriever,
			'',
			$this->getMockWikiLinkParserFactory( $wikiLinkParser )
		);

		$validator->validate( $importPlan, $this->createMock( User::class ) );
		$this->assertSame( 'PARSED', $importPlan->getCleanedLatestRevisionText() );
		$this->assertSame( 1, $importPlan->getNumberOfTemplateReplacements() );
	}

}
