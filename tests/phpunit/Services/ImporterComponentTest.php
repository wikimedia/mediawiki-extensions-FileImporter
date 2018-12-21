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

	public function testImportingZeroFileRevisions() {
		$textRevision = $this->newTextRevision();
		$wikiRevision = $this->createWikiRevisionMock();
		$user = $this->getTestUser()->getUser();

		$minimalRequest = new ImportRequest( self::URL );
		$importPlan = $this->newImportPlan( $minimalRequest, $textRevision );

		$importer = new Importer(
			$this->createWikiPageFactoryMock( $user, self::COMMENT . self::CLEANED_WIKITEXT, null ),
			$this->createWikiRevisionFactoryMock( $textRevision, null, $wikiRevision ),
			$this->createNullRevisionCreatorMock( $user ),
			$this->createHttpRequestExecutorMock(),
			$this->createUploadBaseFactoryMock(),
			$this->createOldRevisionImporterMock( $wikiRevision ),
			$this->createUploadRevisionImporterMock(),
			new FileTextRevisionValidator(),
			new \NullStatsdDataFactory(),
			new NullLogger()
		);

		$this->assertTrue( $importer->import( $user, $importPlan ) );
	}

	public function testImportingZeroFileRevisionsWithUserProvidedValues() {
		$textRevision = $this->newTextRevision();
		$wikiRevision = $this->createWikiRevisionMock();
		$user = $this->getTestUser()->getUser();

		$request = new ImportRequest( self::URL, null, self::USER_WIKITEXT, self::USER_SUMMARY );
		$importPlan = $this->newImportPlan( $request, $textRevision );

		$importer = new Importer(
			$this->createWikiPageFactoryMock( $user, self::USER_WIKITEXT, self::USER_SUMMARY ),
			$this->createWikiRevisionFactoryMock( $textRevision, null, $wikiRevision ),
			$this->createNullRevisionCreatorMock( $user ),
			$this->createHttpRequestExecutorMock(),
			$this->createUploadBaseFactoryMock(),
			$this->createOldRevisionImporterMock( $wikiRevision ),
			$this->createUploadRevisionImporterMock(),
			new FileTextRevisionValidator(),
			new \NullStatsdDataFactory(),
			new NullLogger()
		);

		$this->assertTrue( $importer->import( $user, $importPlan ) );
	}

	public function testImportingOneFileRevision() {
		$textRevision = $this->newTextRevision();
		$fileRevision = $this->newFileRevision();
		$wikiRevision = $this->createWikiRevisionMock();
		$user = $this->getTestUser()->getUser();

		$request = new ImportRequest( self::URL, null, self::USER_WIKITEXT, self::USER_SUMMARY );
		$importPlan = $this->newImportPlan( $request, $textRevision, $fileRevision );

		$importer = new Importer(
			$this->createWikiPageFactoryMock( $user, self::USER_WIKITEXT, self::USER_SUMMARY ),
			$this->createWikiRevisionFactoryMock( $textRevision, $fileRevision, $wikiRevision ),
			$this->createNullRevisionCreatorMock( $user ),
			$this->createHttpRequestExecutorMock( 1 ),
			$this->createUploadBaseFactoryMock( $user, $textRevision ),
			$this->createOldRevisionImporterMock( $wikiRevision ),
			$this->createUploadRevisionImporterMock( $wikiRevision ),
			new FileTextRevisionValidator(),
			new \NullStatsdDataFactory(),
			new NullLogger()
		);

		$this->assertTrue( $importer->import( $user, $importPlan ) );
	}

	private function newImportPlan(
		ImportRequest $request,
		TextRevision $textRevision,
		FileRevision $fileRevision = null
	) {
		$details = new ImportDetails(
			new SourceUrl( self::URL ),
			new \TitleValue( NS_FILE, self::TITLE ),
			new TextRevisions( [ $textRevision ] ),
			new FileRevisions( $fileRevision ? [ $fileRevision ] : [] )
		);
		$details->setCleanedRevisionText( self::CLEANED_WIKITEXT );

		return new ImportPlan( $request, $details, self::PREFIX );
	}

	private function newTextRevision() {
		return new TextRevision( [
			'minor' => null,
			'user' => null,
			'timestamp' => null,
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
			'timestamp' => null,
			'sha1' => null,
			'size' => null,
			'thumburl' => null,
			'url' => self::URL,
		] );
	}

	/**
	 * @return WikiRevision
	 */
	private function createWikiRevisionMock() {
		$revision = $this->createMock( WikiRevision::class );
		$revision->expects( $this->once() )
			->method( 'getContent' )
			->willReturn( $this->createMock( \Content::class ) );
		return $revision;
	}

	/**
	 * @param TextRevision $expectedTextRevision
	 * @param FileRevision|null $expectedFileRevision
	 * @param WikiRevision $returnedWikiRevision
	 *
	 * @return WikiRevisionFactory
	 */
	private function createWikiRevisionFactoryMock(
		TextRevision $expectedTextRevision,
		FileRevision $expectedFileRevision = null,
		WikiRevision $returnedWikiRevision
	) {
		$factory = $this->createMock( WikiRevisionFactory::class );
		$factory->expects( $this->once() )
			->method( 'newFromTextRevision' )
			->with( $expectedTextRevision )
			->willReturn( $returnedWikiRevision );
		$factory->expects( $this->exactly( $expectedFileRevision ? 1 : 0 ) )
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
		$page->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( \Title::makeTitle( NS_FILE, self::TITLE ) );
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
	 * @param int $calls
	 *
	 * @return HttpRequestExecutor
	 */
	private function createHttpRequestExecutorMock( $calls = 0 ) {
		$request = $this->createMock( \MWHttpRequest::class );

		$executor = $this->createMock( HttpRequestExecutor::class );
		$executor->expects( $this->exactly( $calls ) )
			->method( 'executeAndSave' )
			->with( self::URL, $this->stringContains( 'fileimporter_' ) )
			->willReturn( $request );
		return $executor;
	}

	/**
	 * @param User|null $expectedUser
	 * @param TextRevision|null $expectedTextRevision
	 *
	 * @return UploadBaseFactory
	 */
	private function createUploadBaseFactoryMock(
		User $expectedUser = null,
		TextRevision $expectedTextRevision = null
	) {
		$calls = $expectedUser ? 1 : 0;

		$uploadBase = $this->createMock( ValidatingUploadBase::class );
		$uploadBase->expects( $this->exactly( $calls ) )
			->method( 'validateTitle' )
			->willReturn( true );
		$uploadBase->expects( $this->exactly( $calls ) )
			->method( 'validateFile' )
			->willReturn( true );
		$uploadBase->expects( $this->exactly( $calls ) )
			->method( 'validateUpload' )
			->with( $expectedUser, $expectedTextRevision )
			->willReturn( new \Status() );

		$factory = $this->createMock( UploadBaseFactory::class );
		$factory->expects( $this->exactly( $calls ) )
			->method( 'newValidatingUploadBase' )
			->with( $this->isInstanceOf( LinkTarget::class ), '' )
			->willReturn( $uploadBase );
		return $factory;
	}

	/**
	 * @param WikiRevision|null $expectedWikiRevision
	 *
	 * @return UploadRevisionImporter
	 */
	private function createUploadRevisionImporterMock( WikiRevision $expectedWikiRevision = null ) {
		$importer = $this->createMock( UploadRevisionImporter::class );
		$importer->expects( $this->exactly( $expectedWikiRevision ? 1 : 0 ) )
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
				\Title::makeTitle( NS_FILE, self::TITLE ),
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
		$revision->expects( $this->once() )
			->method( 'getId' )
			->willReturn( 0 );
		return $revision;
	}

}
