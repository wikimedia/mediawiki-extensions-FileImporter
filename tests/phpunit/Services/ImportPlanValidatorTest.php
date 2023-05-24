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
use HashConfig;
use MalformedTitleException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiLangTestCase;
use MessageLocalizer;
use MockTitleTrait;
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
	use MockTitleTrait;
	use MockAuthorityTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgHooks' => [],
			'wgGroupPermissions' => [ '*' => [ 'upload' => true, 'createpage' => true ] ],
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
	 * @param bool|null $importAllowed Null if the checker is not supposed to be called
	 *
	 * @return ImportTitleChecker
	 */
	private function getMockImportTitleChecker( ?bool $importAllowed = null ): ImportTitleChecker {
		$mock = $this->createMock( ImportTitleChecker::class );
		$mock->expects( $this->exactly( (int)is_bool( $importAllowed ) ) )
			->method( 'importAllowed' )
			->willReturn( $importAllowed ?? false );
		return $mock;
	}

	/**
	 * @param bool|null $hasDuplicates Null if the checker is not supposed to be called
	 *
	 * @return DuplicateFileRevisionChecker
	 */
	private function getMockDuplicateFileRevisionChecker( ?bool $hasDuplicates = null ): DuplicateFileRevisionChecker {
		$mock = $this->createMock( DuplicateFileRevisionChecker::class );
		$mock->expects( $this->exactly( (int)is_bool( $hasDuplicates ) ) )
			->method( 'findDuplicates' )
			->willReturn( array_fill( 0, (int)$hasDuplicates, 'value' ) );
		return $mock;
	}

	private function getMockFileRevisions(): FileRevisions {
		$mock = $this->createMock( FileRevisions::class );
		$mockFileRevision = $this->createMock( FileRevision::class );
		$mock->method( 'getLatest' )
			->willReturn( $mockFileRevision );
		return $mock;
	}

	private function getMockImportDetails( LinkTarget $sourceTitle ): ImportDetails {
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
	 * @param string $planTitle A "<" makes {@see ImportPlan::getTitle} throw an exception
	 * @param string|null $sourceTitle Defaults to a valid title
	 * @param bool $exists
	 *
	 * @return ImportPlan
	 */
	private function getMockImportPlan(
		string $planTitle,
		string $sourceTitle = null,
		bool $exists = false
	): ImportPlan {
		$linkTarget = $this->createMock( LinkTarget::class );
		$linkTarget->method( 'getText' )->willReturn( $sourceTitle ?? 'Source.JPG' );
		$mock = $this->getMockBuilder( ImportPlan::class )
			->setConstructorArgs( [
				new ImportRequest( '//w.invalid' ),
				$this->getMockImportDetails( $linkTarget ),
				new HashConfig(),
				$this->createMock( MessageLocalizer::class ),
				''
			] )
			// This makes this a partial mock where all other methods still call the original code.
			->onlyMethods( [ 'getTitle' ] )
			->getMock();

		if ( !str_contains( $planTitle, '<' ) ) {
			$title = $this->createMock( Title::class );
			$title->method( 'getText' )->willReturn( $planTitle );
			$title->method( 'exists' )->willReturn( $exists );
			$mock->method( 'getTitle' )
				->willReturn( $title );
		} else {
			$mock->method( 'getTitle' )
				->willThrowException( new MalformedTitleException( 'mockexception' ) );
		}

		return $mock;
	}

	private function getMockUploadBaseFactory( UploadBase $uploadBase ): UploadBaseFactory {
		$mock = $this->createMock( UploadBaseFactory::class );
		$mock->method( 'newValidatingUploadBase' )
			->willReturn( $uploadBase );
		return $mock;
	}

	/**
	 * @param int $titleValidationError Numeric error code or UploadBase::OK on success
	 *
	 * @return ValidatingUploadBase
	 */
	private function getMockValidatingUploadBase(
		int $titleValidationError = UploadBase::OK
	): ValidatingUploadBase {
		$mock = $this->createMock( ValidatingUploadBase::class );
		$mock->method( 'validateTitle' )
			->willReturn( $titleValidationError );
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
	): WikiLinkParserFactory {
		$mock = $this->createMock( WikiLinkParserFactory::class );
		$mock->expects( $this->exactly( $callCount ) )
			->method( 'getWikiLinkParser' )
			->willReturn( $wikiLinkParser ?? new WikiLinkParser() );
		return $mock;
	}

	public function provideValidate() {
		$emptyPlan = $this->getMockImportPlan( '<invalid title>' );

		$allowedFileTitles = [
			'Regular name' => 'SourceName.JPG',
			'Multiple extensions are allowed' => 'SourceName.JPG.JPG',
			'Change of extension case is allowed' => 'SourceName.jpg',
		];
		$validTests = [];
		foreach ( $allowedFileTitles as $description => $titleString ) {
			$validTests["Valid Plan - '$titleString' ($description)"] = [
				null,
				$this->getMockImportPlan( 'FinalName.JPG', $titleString ),
				'hasDuplicates' => false,
				'importAllowed' => true,
				'wikiLinkParserFactoryCalls' => 1
			];
		}

		$invalidTests = [
			'Invalid, duplicate file found' => [
				new DuplicateFilesException( [] ),
				$this->getMockImportPlan( 'FinalName.JPG' ),
				'hasDuplicates' => true,
			],
			'Invalid, title exists on target site' => [
				new RecoverableTitleException(
					'fileimporter-localtitleexists',
					$emptyPlan
				),
				$this->getMockImportPlan( 'FinalName.JPG', null, true ),
				'hasDuplicates' => false,
				'importAllowed' => null,
				'wikiLinkParserFactoryCalls' => 1,
			],
			'Invalid, title exists on source site' => [
				new RecoverableTitleException(
					'fileimporter-sourcetitleexists',
					$emptyPlan
				),
				$this->getMockImportPlan( 'FinalName.JPG' ),
				'hasDuplicates' => false,
				'importAllowed' => false,
				'wikiLinkParserFactoryCalls' => 1,
			],
			'Invalid, file extension has changed' => [
				new TitleException( 'fileimporter-filenameerror-missmatchextension' ),
				$this->getMockImportPlan( 'FinalName.JPG', 'SourceName.PNG' ),
			],
			'Invalid, No Extension on planned name' => [
				new TitleException( 'fileimporter-filenameerror-noplannedextension' ),
				$this->getMockImportPlan( 'FinalName.JPG/Foo' ),
			],
			'Invalid, No Extension on source name' => [
				new TitleException( 'fileimporter-filenameerror-nosourceextension' ),
				$this->getMockImportPlan( 'FinalName.JPG', 'SourceName.jpg/Foo' ),
			],
			'Invalid, Bad title (includes another namespace)' => [
				new RecoverableTitleException(
					'fileimporter-illegalfilenamechars',
					$emptyPlan
				),
				$this->getMockImportPlan( 'File:Talk:FinalName.JPG' ),
				'hasDuplicates' => false,
				'importAllowed' => null,
				'wikiLinkParserFactoryCalls' => 1,
			],
			'Invalid, Bad filename (too long)' => [
				new RecoverableTitleException(
					'fileimporter-filenameerror-toolong',
					$emptyPlan
				),
				$this->getMockImportPlan( str_repeat( 'a', 242 ) . '.JPG' ),
				'hasDuplicates' => false,
				'importAllowed' => null,
				'wikiLinkParserFactoryCalls' => 1,
				'titleValidationError' => UploadBase::FILENAME_TOO_LONG,
			],
			'Invalid, Bad characters "<", getTitle throws MalformedTitleException' => [
				new RecoverableTitleException(
					'mockexception',
					$emptyPlan
				),
				$this->getMockImportPlan( '<invalid title>' ),
			],
		];

		return $validTests + $invalidTests;
	}

	/**
	 * @dataProvider provideValidate
	 */
	public function testValidate(
		?ImportException $expected,
		ImportPlan $plan,
		?bool $hasDuplicates = null,
		?bool $importAllowed = null,
		int $wikiLinkParserFactoryCalls = 0,
		int $titleValidationError = UploadBase::OK
	) {
		$validatingUploadBase = $this->getMockValidatingUploadBase( $titleValidationError );

		$validator = new ImportPlanValidator(
			$this->getMockDuplicateFileRevisionChecker( $hasDuplicates ),
			$this->getMockImportTitleChecker( $importAllowed ),
			$this->getMockUploadBaseFactory( $validatingUploadBase ),
			null,
			null,
			$this->getMockWikiLinkParserFactory( $wikiLinkParserFactoryCalls ),
			$this->getServiceContainer()->getRestrictionStore()
		);

		if ( $expected ) {
			$this->expectException( get_class( $expected ) );
			$this->expectExceptionMessage( $expected->getMessage() );
		}

		$validator->validate(
			$plan,
			$this->mockRegisteredAuthorityWithPermissions( [ 'edit', 'upload' ] )
		);
	}

	public function testValidateFailsWhenCoreChangesTheName() {
		$mockRequest = $this->createMock( ImportRequest::class );
		$mockRequest->expects( $this->atLeastOnce() )
			->method( 'getIntendedName' )
			->willReturn( 'Before.jpg#After' );
		$mockDetails = $this->getMockImportDetails( new TitleValue( NS_FILE, 'SourceName.jpg' ) );
		$config = new HashConfig();
		$messageLocalizer = $this->createMock( MessageLocalizer::class );

		$importPlan = new ImportPlan( $mockRequest, $mockDetails, $config, $messageLocalizer, '' );

		$validator = new ImportPlanValidator(
			$this->getMockDuplicateFileRevisionChecker( false ),
			$this->getMockImportTitleChecker(),
			$this->getMockUploadBaseFactory( $this->getMockValidatingUploadBase() ),
			null,
			null,
			$this->getMockWikiLinkParserFactory( 1 ),
			$this->getServiceContainer()->getRestrictionStore()
		);

		$this->expectException( RecoverableTitleException::class );
		$this->expectExceptionMessage( '"Before"' );
		$validator->validate(
			$importPlan,
			$this->mockRegisteredAuthorityWithPermissions( [ 'edit', 'upload' ] )
		);
	}

	public function testValidateFailsOnFailingUploadPermissionCheck() {
		$this->setMwGlobals( [
			'wgGroupPermissions' => [ '*' => [ 'upload' => false, 'createpage' => true ] ],
		] );
		$importRequest = new ImportRequest( '//w.invalid' );
		$mockDetails = $this->getMockImportDetails( $this->createMock( LinkTarget::class ) );
		$config = new HashConfig();
		$messageLocalizer = $this->createMock( MessageLocalizer::class );

		$importPlan = new ImportPlan( $importRequest, $mockDetails, $config, $messageLocalizer, '' );

		$validator = new ImportPlanValidator(
			$this->getMockDuplicateFileRevisionChecker( null ),
			$this->getMockImportTitleChecker(),
			$this->getMockUploadBaseFactory( $this->getMockValidatingUploadBase() ),
			null,
			null,
			$this->getMockWikiLinkParserFactory(),
			$this->getServiceContainer()->getRestrictionStore()
		);

		$this->expectException( RecoverableTitleException::class );
		$this->expectExceptionMessage( 'You are not allowed to execute the action you have requested' );
		$validator->validate( $importPlan, $this->getTestUser()->getUser() );
	}

	public function testCommonsHelperAndWikiLinkParserIntegration() {
		$this->setTemporaryHook( 'TitleExists', function ( LinkTarget $title, &$exists ) {
			$this->assertSame( 'De', $title->getDBkey() );
			$exists = true;
		} );

		$importPlan = $this->getMockImportPlan( 'Valid.jpg' );
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
			$this->getMockDuplicateFileRevisionChecker( false ),
			$this->getMockImportTitleChecker( true ),
			$this->getMockUploadBaseFactory( $this->getMockValidatingUploadBase() ),
			$commonsHelperConfigRetriever,
			'',
			$this->getMockWikiLinkParserFactory( 1, $wikiLinkParser ),
			$this->getServiceContainer()->getRestrictionStore()
		);

		$validator->validate(
			$importPlan,
			$this->mockRegisteredAuthorityWithPermissions( [ 'edit', 'upload' ] )
		);
		$this->assertSame( 'PARSED', $importPlan->getCleanedLatestRevisionText() );
		$this->assertSame( 2, $importPlan->getNumberOfTemplateReplacements() );
	}

}
