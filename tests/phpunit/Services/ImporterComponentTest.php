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
use FileImporter\Services\FileTextRevisionValidator;
use FileImporter\Services\Http\HttpRequestExecutor;
use FileImporter\Services\Importer;
use FileImporter\Services\NullRevisionCreator;
use FileImporter\Services\UploadBase\UploadBaseFactory;
use FileImporter\Services\UploadBase\ValidatingUploadBase;
use FileImporter\Services\WikiPageFactory;
use FileImporter\Services\WikiRevisionFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Revision\RevisionRecord;
use OldRevisionImporter;
use Psr\Log\NullLogger;
use UploadRevisionImporter;
use User;
use WikiRevision;

/**
 * @covers \FileImporter\Services\FileTextRevisionValidator
 * @covers \FileImporter\Services\Importer
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class ImporterComponentTest extends \MediaWikiTestCase {
	use \PHPUnit4And6Compat;

	const URL = 'http://source.url';
	const TITLE = 'FilePageTitle';
	const PREFIX = 'interwiki-prefix';

	const COMMENT = "<!--This file was moved here using FileImporter from http://source.url-->\n";
	const CLEANED_WIKITEXT = 'Auto-cleaned wikitext.';
	const USER_WIKITEXT = 'User-provided wikitext.';

	const NULL_EDIT_SUMMARY = 'Imported with FileImporter from http://source.url';
	const USER_SUMMARY = 'User-provided summary';

	/**
	 * @var User
	 */
	private $user;

	protected function setUp() {
		parent::setUp();

		$this->user = $this->getTestUser()->getUser();
	}

	public function testImportingOneFileRevision() {
		$textRevision = $this->newTextRevision();
		$fileRevision = $this->newFileRevision();
		$wikiRevision = $this->createWikiRevisionMock();

		$minimalRequest = new ImportRequest( self::URL );
		$importPlan = $this->newImportPlan( $minimalRequest, $textRevision, $fileRevision );

		$importer = new Importer(
			$this->createWikiPageFactoryMock( $this->user, self::COMMENT . self::CLEANED_WIKITEXT, null ),
			$this->createWikiRevisionFactoryMock( $textRevision, $fileRevision, $wikiRevision ),
			$this->createNullRevisionCreatorMock( $this->user ),
			$this->createHttpRequestExecutorMock(),
			$this->createUploadBaseFactoryMock( $this->user, $textRevision ),
			$this->createOldRevisionImporterMock( $wikiRevision ),
			$this->createUploadRevisionImporterMock( $wikiRevision ),
			new FileTextRevisionValidator(),
			new \NullStatsdDataFactory(),
			new NullLogger()
		);

		$this->assertTrue( $importer->import( $this->user, $importPlan ) );
	}

	public function testImportingOneFileRevisionWithUserProvidedValues() {
		$textRevision = $this->newTextRevision();
		$fileRevision = $this->newFileRevision();
		$wikiRevision = $this->createWikiRevisionMock();

		$request = new ImportRequest( self::URL, null, self::USER_WIKITEXT, self::USER_SUMMARY );
		$importPlan = $this->newImportPlan( $request, $textRevision, $fileRevision );

		$importer = new Importer(
			$this->createWikiPageFactoryMock( $this->user, self::USER_WIKITEXT, self::USER_SUMMARY ),
			$this->createWikiRevisionFactoryMock( $textRevision, $fileRevision, $wikiRevision ),
			$this->createNullRevisionCreatorMock( $this->user ),
			$this->createHttpRequestExecutorMock(),
			$this->createUploadBaseFactoryMock( $this->user, $textRevision ),
			$this->createOldRevisionImporterMock( $wikiRevision ),
			$this->createUploadRevisionImporterMock( $wikiRevision ),
			new FileTextRevisionValidator(),
			new \NullStatsdDataFactory(),
			new NullLogger()
		);

		$this->assertTrue( $importer->import( $this->user, $importPlan ) );
	}

	private function newImportPlan(
		ImportRequest $request,
		TextRevision $textRevision,
		FileRevision $fileRevision
	) {
		$details = new ImportDetails(
			new SourceUrl( self::URL ),
			new \TitleValue( NS_FILE, self::TITLE ),
			new TextRevisions( [ $textRevision ] ),
			new FileRevisions( [ $fileRevision ] )
		);
		$details->setCleanedRevisionText( self::CLEANED_WIKITEXT );

		return new ImportPlan( $request, $details, self::PREFIX );
	}

	private function newTextRevision() {
		return new TextRevision( [
			'minor' => null,
			'user' => null,
			'timestamp' => '20190101000000',
			'sha1' => null,
			'contentmodel' => null,
			'contentformat' => null,
			'comment' => null,
			'*' => null,
			'title' => null,
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

	/**
	 * @param int $expectedLogActions Number of actions to expect that are only called when an
	 *  upload log entry is created.
	 *
	 * @return WikiRevision
	 */
	private function createWikiRevisionMock( $expectedLogActions = 0 ) {
		$revision = $this->createMock( WikiRevision::class );

		$revision->expects( $this->exactly( $expectedLogActions ) )
			->method( 'getTitle' )
			->willReturn( \Title::makeTitle( NS_FILE, self::TITLE ) );
		$revision->expects( $this->exactly( $expectedLogActions ) )
			->method( 'getID' )
			->willReturn( 0 );
		$revision->expects( $this->exactly( $expectedLogActions ) )
			->method( 'getUserObj' )
			->willReturn( $this->user );

		$revision->expects( $this->once() )
			->method( 'getContent' )
			->willReturn( $this->createMock( \Content::class ) );

		return $revision;
	}

	/**
	 * @param TextRevision $expectedTextRevision
	 * @param FileRevision $expectedFileRevision
	 * @param WikiRevision $returnedWikiRevision
	 *
	 * @return WikiRevisionFactory
	 */
	private function createWikiRevisionFactoryMock(
		TextRevision $expectedTextRevision,
		FileRevision $expectedFileRevision,
		WikiRevision $returnedWikiRevision
	) {
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
	 * @param User $expectedUser
	 * @param string $expectedWikiText
	 * @param string|null $expectedSummary
	 *
	 * @return WikiPageFactory
	 */
	private function createWikiPageFactoryMock(
		User $expectedUser,
		$expectedWikiText,
		$expectedSummary
	) {
		$page = $this->createMock( \WikiPage::class );
		$page->expects( $this->never() )
			->method( 'getTitle' );
		$page->expects( $this->once() )
			->method( 'doEditContent' )
			->with(
				new \WikitextContent( $expectedWikiText ),
				$expectedSummary,
				EDIT_UPDATE,
				false,
				$expectedUser
			)
			->willReturn( new \Status() );

		$factory = $this->createMock( WikiPageFactory::class );
		$factory->expects( $this->once() )
			->method( 'newFromID' )
			->with( 0 )
			->willReturn( $page );
		return $factory;
	}

	/**
	 * @return HttpRequestExecutor
	 */
	private function createHttpRequestExecutorMock() {
		$request = $this->createMock( \MWHttpRequest::class );

		$executor = $this->createMock( HttpRequestExecutor::class );
		$executor->expects( $this->once() )
			->method( 'executeAndSave' )
			->with( self::URL, $this->stringContains( 'fileimporter_' ) )
			->willReturn( $request );
		return $executor;
	}

	/**
	 * @param User $expectedUser
	 * @param TextRevision $expectedTextRevision
	 *
	 * @return UploadBaseFactory
	 */
	private function createUploadBaseFactoryMock(
		User $expectedUser,
		TextRevision $expectedTextRevision
	) {
		$uploadBase = $this->createMock( ValidatingUploadBase::class );
		$uploadBase->expects( $this->once() )
			->method( 'validateTitle' )
			->willReturn( true );
		$uploadBase->expects( $this->once() )
			->method( 'validateFile' )
			->willReturn( true );
		$uploadBase->expects( $this->once() )
			->method( 'validateUpload' )
			->with( $expectedUser, $expectedTextRevision )
			->willReturn( new \Status() );

		$factory = $this->createMock( UploadBaseFactory::class );
		$factory->expects( $this->once() )
			->method( 'newValidatingUploadBase' )
			->with( $this->isInstanceOf( LinkTarget::class ), '' )
			->willReturn( $uploadBase );
		return $factory;
	}

	/**
	 * @param WikiRevision $expectedWikiRevision
	 *
	 * @return UploadRevisionImporter
	 */
	private function createUploadRevisionImporterMock( WikiRevision $expectedWikiRevision ) {
		$importer = $this->createMock( UploadRevisionImporter::class );
		$importer->expects( $this->once() )
			->method( 'import' )
			->with( $expectedWikiRevision )
			->willReturn( new \Status() );
		return $importer;
	}

	/**
	 * @param WikiRevision $expectedWikiRevision
	 *
	 * @return OldRevisionImporter
	 */
	private function createOldRevisionImporterMock( WikiRevision $expectedWikiRevision ) {
		$importer = $this->createMock( OldRevisionImporter::class );
		$importer->expects( $this->once() )
			->method( 'import' )
			->with( $expectedWikiRevision )
			->willReturn( true );
		return $importer;
	}

	/**
	 * @param User $expectedUser
	 *
	 * @return NullRevisionCreator
	 */
	private function createNullRevisionCreatorMock( User $expectedUser ) {
		$creator = $this->createMock( NullRevisionCreator::class );
		$creator->expects( $this->once() )
			->method( 'createForLinkTarget' )
			->with(
				$this->callback( function ( LinkTarget $title ) {
					return $title->getNamespace() === NS_FILE
						&& $title->getText() === self::TITLE;
				} ),
				$expectedUser,
				self::NULL_EDIT_SUMMARY
			)
			->willReturn( $this->createNullRevisionMock() );
		return $creator;
	}

	/**
	 * @return RevisionRecord
	 */
	private function createNullRevisionMock() {
		$revision = $this->createMock( RevisionRecord::class );
		$revision->expects( $this->exactly( 2 ) )
			->method( 'getId' )
			->willReturn( 0 );
		return $revision;
	}

}
