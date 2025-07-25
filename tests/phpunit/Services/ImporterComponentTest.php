<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\FileRevision;
use FileImporter\Data\FileRevisions;
use FileImporter\Data\ImportDetails;
use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\SourceUrl;
use FileImporter\Data\TextRevision;
use FileImporter\Data\TextRevisions;
use FileImporter\Exceptions\AbuseFilterWarningsException;
use FileImporter\Exceptions\LocalizedImportException;
use FileImporter\Services\FileTextRevisionValidator;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\Importer;
use FileImporter\Services\NullRevisionCreator;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\UploadBase\ValidatingUploadBase;
use FileImporter\Services\WikiRevisionFactory;
use MediaWiki\Api\ApiMessage;
use MediaWiki\Config\HashConfig;
use MediaWiki\Content\TextContent;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\WikiPage;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Storage\PageUpdateStatus;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MessageLocalizer;
use OldRevisionImporter;
use StatusValue;
use UploadRevisionImporter;
use Wikimedia\TestingAccessWrapper;
use WikiRevision;

/**
 * @covers \FileImporter\Services\Importer
 *
 * @group Database
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class ImporterComponentTest extends \MediaWikiIntegrationTestCase {

	private const URL = 'http://w.invalid';
	private const TITLE = 'FilePageTitle';
	private const PREFIX = 'interwiki-prefix';

	private const COMMENT = '<!--COMMENT-->';
	private const CLEANED_WIKITEXT = 'Auto-cleaned wikitext.';
	private const USER_WIKITEXT = 'User-provided wikitext.';

	private const NULL_EDIT_SUMMARY = 'Imported with FileImporter from ' . self::URL;
	private const USER_SUMMARY = 'User-provided summary';

	protected function setUp(): void {
		parent::setUp();

		$this->clearHooks();
	}

	public function testImportingOneFileRevision() {
		$user = $this->createNoOpMock( User::class );
		$textRevision = $this->newTextRevision();
		$fileRevision = $this->newFileRevision();
		$wikiRevision = $this->createWikiRevisionMock();

		$localizer = $this->createMock( MessageLocalizer::class );
		$localizer->method( 'msg' )
			->willReturnCallback( fn ( $key ) => $this->getMockMessage( "($key)" ) );

		$minimalRequest = new ImportRequest( self::URL );
		$importPlan = $this->newImportPlan( $minimalRequest, $textRevision, $fileRevision, $localizer );

		$expectedWikitext = self::COMMENT . "\n(fileimporter-post-import-revision-annotation)\n" .
			self::CLEANED_WIKITEXT;
		$importer = new Importer(
			$this->createWikiPageFactoryMock( $user, $expectedWikitext, null ),
			$this->createWikiRevisionFactoryMock( $textRevision, $fileRevision, $wikiRevision ),
			$this->createNullRevisionCreatorMock( $user ),
			$this->createNoOpMock( UserIdentityLookup::class ),
			$this->createHttpRequestExecutorMock(),
			$this->createUploadBaseFactoryMock( $user, $textRevision ),
			$this->createOldRevisionImporterMock( $wikiRevision ),
			$this->createUploadRevisionImporterMock( $wikiRevision ),
			new FileTextRevisionValidator(),
			$this->createNoOpMock( RestrictionStore::class, [ 'isProtected' ] )
		);

		$importer->import( $user, $importPlan );
	}

	public function testImportingOneFileRevisionWithUserProvidedValues() {
		$user = $this->createNoOpMock( User::class );
		$textRevision = $this->newTextRevision();
		$fileRevision = $this->newFileRevision();
		$wikiRevision = $this->createWikiRevisionMock();
		$messageLocalizer = $this->createMock( MessageLocalizer::class );

		$request = new ImportRequest( self::URL, null, self::USER_WIKITEXT, self::USER_SUMMARY );
		$importPlan = $this->newImportPlan( $request, $textRevision, $fileRevision, $messageLocalizer );

		$importer = new Importer(
			$this->createWikiPageFactoryMock( $user, self::USER_WIKITEXT, self::USER_SUMMARY ),
			$this->createWikiRevisionFactoryMock( $textRevision, $fileRevision, $wikiRevision ),
			$this->createNullRevisionCreatorMock( $user ),
			$this->createNoOpMock( UserIdentityLookup::class ),
			$this->createHttpRequestExecutorMock(),
			$this->createUploadBaseFactoryMock( $user, $textRevision ),
			$this->createOldRevisionImporterMock( $wikiRevision ),
			$this->createUploadRevisionImporterMock( $wikiRevision ),
			new FileTextRevisionValidator(),
			$this->createNoOpMock( RestrictionStore::class, [ 'isProtected' ] )
		);

		$importer->import( $user, $importPlan );
	}

	public function testValidateImportOperations() {
		$importPlan = $this->createMock( ImportPlan::class );
		$importPlan->expects( $this->exactly( 2 ) )
			->method( 'getValidationWarnings' )
			->willReturn( [ 2, 3 ] );
		$importPlan->expects( $this->once() )
			->method( 'addValidationWarning' )
			->with( 1 );

		$apiMessage1 = ApiMessage::create( '1', null, [
			'abusefilter' => [
				'id' => 1,
				'actions' => [ 'warn' ]
			]
		] );

		$apiMessage2 = ApiMessage::create( '2', null, [
			'abusefilter' => [
				'id' => 2,
				'actions' => [ 'warn' ]
			]
		] );

		$status = StatusValue::newGood();
		$status->warning( $apiMessage1 );
		$status->warning( $apiMessage2 );

		$importer = new Importer(
			$this->createNoOpMock( WikiPageFactory::class ),
			$this->createNoOpMock( WikiRevisionFactory::class ),
			$this->createNoOpMock( NullRevisionCreator::class ),
			$this->createNoOpMock( UserIdentityLookup::class ),
			$this->createNoOpMock( HttpRequestExecutor::class ),
			$this->createNoOpMock( UploadBaseFactory::class ),
			$this->createNoOpMock( OldRevisionImporter::class ),
			$this->createNoOpMock( UploadRevisionImporter::class ),
			new FileTextRevisionValidator(),
			$this->createNoOpMock( RestrictionStore::class )
		);

		/** @var Importer $importer */
		$importer = TestingAccessWrapper::newFromObject( $importer );

		try {
			$importer->validateImportOperations( $status, $importPlan );
			$this->fail( 'Failed asserting that exception of type "ImportValidationException" is thrown.' );
		} catch ( AbuseFilterWarningsException $e ) {
			$this->assertCount( 1, $e->getMessages() );
			$this->assertArrayEquals( [ $apiMessage1 ], $e->getMessages() );
		}
	}

	public function testValidateImportOperationsWithAbuseFilterDisallow() {
		$importPlan = $this->createMock( ImportPlan::class );
		$importPlan->expects( $this->once() )
			->method( 'getValidationWarnings' )
			->willReturn( [] );
		$importPlan->expects( $this->once() )
			->method( 'addValidationWarning' )
			->with( 1 );

		$apiMessage1 = ApiMessage::create( '1', null, [
			'abusefilter' => [
				'id' => 1,
				'actions' => [ 'warn' ]
			]
		] );

		$apiMessage2 = ApiMessage::create( '2', null, [
			'abusefilter' => [
				'id' => 2,
				'actions' => [ 'disallow' ]
			]
		] );

		$status = StatusValue::newGood();
		$status->warning( $apiMessage1 );
		$status->warning( $apiMessage2 );

		$importer = new Importer(
			$this->createNoOpMock( WikiPageFactory::class ),
			$this->createNoOpMock( WikiRevisionFactory::class ),
			$this->createNoOpMock( NullRevisionCreator::class ),
			$this->createNoOpMock( UserIdentityLookup::class ),
			$this->createNoOpMock( HttpRequestExecutor::class ),
			$this->createNoOpMock( UploadBaseFactory::class ),
			$this->createNoOpMock( OldRevisionImporter::class ),
			$this->createNoOpMock( UploadRevisionImporter::class ),
			new FileTextRevisionValidator(),
			$this->createNoOpMock( RestrictionStore::class )
		);

		/** @var Importer $importer */
		$importer = TestingAccessWrapper::newFromObject( $importer );

		$this->expectException( LocalizedImportException::class );
		$importer->validateImportOperations( $status, $importPlan );
	}

	public function testValidateImportOperationsWithStatusParams() {
		/** @var Importer $importer */
		$importer = TestingAccessWrapper::newFromObject( new Importer(
			$this->createNoOpMock( WikiPageFactory::class ),
			$this->createNoOpMock( WikiRevisionFactory::class ),
			$this->createNoOpMock( NullRevisionCreator::class ),
			$this->createNoOpMock( UserIdentityLookup::class ),
			$this->createNoOpMock( HttpRequestExecutor::class ),
			$this->createNoOpMock( UploadBaseFactory::class ),
			$this->createNoOpMock( OldRevisionImporter::class ),
			$this->createNoOpMock( UploadRevisionImporter::class ),
			new FileTextRevisionValidator(),
			$this->createNoOpMock( RestrictionStore::class )
		) );

		$status = StatusValue::newFatal( 'fileimporter-cantimportfileinvalid', 'The reason' );
		$this->expectExceptionMessage( 'The reason' );
		$importer->validateImportOperations( $status, $this->createNoOpMock( ImportPlan::class ) );
	}

	private function newImportPlan(
		ImportRequest $request,
		TextRevision $textRevision,
		FileRevision $fileRevision,
		MessageLocalizer $messageLocalizer
	) {
		$details = new ImportDetails(
			new SourceUrl( self::URL ),
			new TitleValue( NS_FILE, self::TITLE ),
			new TextRevisions( [ $textRevision ] ),
			new FileRevisions( [ $fileRevision ] )
		);
		$config = new HashConfig( [
			'FileImporterTextForPostImportRevision' => self::COMMENT,
		] );

		$plan = new ImportPlan( $request, $details, $config, $messageLocalizer, self::PREFIX );
		$plan->setCleanedLatestRevisionText( self::CLEANED_WIKITEXT );
		return $plan;
	}

	private function newTextRevision() {
		return new TextRevision( [
			'minor' => null,
			'user' => null,
			'timestamp' => '20190101000000',
			'sha1' => null,
			'comment' => null,
			'slots' => null,
			'title' => null,
			'tags' => [],
		] );
	}

	private function newFileRevision() {
		return new FileRevision( [
			'name' => null,
			'description' => null,
			'user' => null,
			'timestamp' => '20190101000000',
			'sha1' => null,
			'size' => null,
			'thumburl' => null,
			'url' => self::URL,
		] );
	}

	private function createWikiRevisionMock(): WikiRevision {
		$revision = $this->createMock( WikiRevision::class );
		$revision->expects( $this->once() )
			->method( 'getContent' )
			->willReturn( new TextContent( '' ) );
		return $revision;
	}

	private function createWikiRevisionFactoryMock(
		TextRevision $expectedTextRevision,
		FileRevision $expectedFileRevision,
		WikiRevision $returnedWikiRevision
	): WikiRevisionFactory {
		$factory = $this->createMock( WikiRevisionFactory::class );
		$factory->expects( $this->once() )
			->method( 'newFromTextRevision' )
			->with( $expectedTextRevision )
			->willReturn( $returnedWikiRevision );
		$factory->expects( $this->once() )
			->method( 'newFromFileRevision' )
			->with( $expectedFileRevision, $this->stringContains( 'fileimporter_' ) )
			->willReturn( $returnedWikiRevision );
		return $factory;
	}

	/**
	 * @param Authority $expectedUser
	 * @param string $expectedWikitext
	 * @param string|null $expectedSummary
	 */
	private function createWikiPageFactoryMock(
		Authority $expectedUser,
		$expectedWikitext,
		$expectedSummary
	): WikiPageFactory {
		$page = $this->createMock( WikiPage::class );
		$page->expects( $this->never() )
			->method( 'getTitle' );
		$page->expects( $this->once() )
			->method( 'doUserEditContent' )
			->with(
				new WikitextContent( $expectedWikitext ),
				$expectedUser,
				$expectedSummary,
				EDIT_UPDATE,
				false,
				[ 'fileimporter' ]
			)
			->willReturn( PageUpdateStatus::newGood() );

		$factory = $this->createMock( WikiPageFactory::class );
		$factory->expects( $this->once() )
			->method( 'newFromID' )
			->with( 0 )
			->willReturn( $page );
		return $factory;
	}

	private function createHttpRequestExecutorMock(): HttpRequestExecutor {
		$request = $this->createNoOpMock( \MWHttpRequest::class );

		$executor = $this->createMock( HttpRequestExecutor::class );
		$executor->expects( $this->once() )
			->method( 'executeAndSave' )
			->with( self::URL, $this->stringContains( 'fileimporter_' ) )
			->willReturn( $request );
		return $executor;
	}

	private function createUploadBaseFactoryMock(
		User $expectedUser,
		TextRevision $expectedTextRevision
	): UploadBaseFactory {
		$uploadBase = $this->createMock( ValidatingUploadBase::class );
		$uploadBase->expects( $this->once() )
			->method( 'validateTitle' )
			->willReturn( \UploadBase::OK );
		$uploadBase->expects( $this->once() )
			->method( 'validateFile' )
			->willReturn( \StatusValue::newGood() );
		$uploadBase->expects( $this->once() )
			->method( 'validateUpload' )
			->with( $expectedUser, $expectedTextRevision )
			->willReturn( \StatusValue::newGood() );

		$factory = $this->createMock( UploadBaseFactory::class );
		$factory->expects( $this->once() )
			->method( 'newValidatingUploadBase' )
			->with( $this->isInstanceOf( LinkTarget::class ), '' )
			->willReturn( $uploadBase );
		return $factory;
	}

	private function createUploadRevisionImporterMock(
		WikiRevision $expectedWikiRevision
	): UploadRevisionImporter {
		$importer = $this->createMock( UploadRevisionImporter::class );
		$importer->expects( $this->once() )
			->method( 'import' )
			->with( $expectedWikiRevision )
			->willReturn( \StatusValue::newGood( '' ) );
		return $importer;
	}

	private function createOldRevisionImporterMock(
		WikiRevision $expectedWikiRevision
	): OldRevisionImporter {
		$importer = $this->createMock( OldRevisionImporter::class );
		$importer->expects( $this->once() )
			->method( 'import' )
			->with( $expectedWikiRevision )
			->willReturn( true );
		return $importer;
	}

	private function createNullRevisionCreatorMock( UserIdentity $expectedUser ): NullRevisionCreator {
		$creator = $this->createMock( NullRevisionCreator::class );
		$creator->expects( $this->once() )
			->method( 'createForLinkTarget' )
			->with(
				$this->callback( static function ( LinkTarget $title ) {
					return $title->inNamespace( NS_FILE )
						&& $title->getText() === self::TITLE;
				} ),
				$this->isInstanceOf( FileRevision::class ),
				$expectedUser,
				self::NULL_EDIT_SUMMARY
			);
		return $creator;
	}

}
