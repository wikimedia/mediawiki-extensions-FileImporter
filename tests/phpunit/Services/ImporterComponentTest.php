<?php

namespace FileImporter\Tests\Services;

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
use FileImporter\Services\WikiPageFactory;
use FileImporter\Services\WikiRevisionFactory;
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
class ImporterComponentTest extends \PHPUnit\Framework\TestCase {
	use \PHPUnit4And6Compat;

	const URL = 'http://source.url';
	const TITLE = 'FilePageTitle';
	const PREFIX = 'interwiki-prefix';

	const COMMENT = "<!--This file was moved here using FileImporter from http://source.url-->\n";
	const ORIGINAL_WIKITEXT = 'Original wikitext.';
	const CLEANED_WIKITEXT = 'Auto-cleaned wikitext.';
	const USER_WIKITEXT = 'User-provided wikitext.';

	const NULL_EDIT_SUMMARY = 'Imported with FileImporter from http://source.url';
	const USER_SUMMARY = 'User-provided summary';

	public function testImportingZeroFileRevisions() {
		$textRevision = $this->newTextRevision();
		$wikiRevision = $this->createWikiRevisionMock();
		$user = $this->createMock( User::class );
		$logger = new NullLogger();

		$request = new ImportRequest( self::URL, null, self::USER_WIKITEXT, self::USER_SUMMARY );
		$details = new ImportDetails(
			new SourceUrl( self::URL ),
			new \TitleValue( NS_FILE, self::TITLE ),
			new TextRevisions( [ $textRevision ] ),
			new FileRevisions( [] )
		);
		$details->setCleanedRevisionText( self::CLEANED_WIKITEXT );
		$importPlan = new ImportPlan( $request, $details, self::PREFIX );

		$importer = new Importer(
			$this->createWikiPageFactoryMock( $user ),
			$this->createWikiRevisionFactoryMock( $textRevision, $wikiRevision ),
			$this->createNullRevisionCreatorMock( $user ),
			$this->createHttpRequestExecutorMock(),
			new UploadBaseFactory( $logger ),
			$this->createOldRevisionImporterMock( $wikiRevision ),
			$this->createUploadRevisionImporterMock(),
			new FileTextRevisionValidator(),
			new \NullStatsdDataFactory(),
			$logger
		);

		$this->assertTrue( $importer->import( $user, $importPlan ) );
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
	 * @param WikiRevision $returnedWikiRevision
	 *
	 * @return WikiRevisionFactory
	 */
	private function createWikiRevisionFactoryMock(
		TextRevision $expectedTextRevision,
		WikiRevision $returnedWikiRevision
	) {
		$factory = $this->createMock( WikiRevisionFactory::class );
		$factory->expects( $this->once() )
			->method( 'newFromTextRevision' )
			->with( $expectedTextRevision )
			->willReturn( $returnedWikiRevision );
		$factory->expects( $this->never() )
			->method( 'newFromFileRevision' );
		return $factory;
	}

	/**
	 * @param User $expectedUser
	 *
	 * @return WikiPageFactory
	 */
	private function createWikiPageFactoryMock( User $expectedUser ) {
		$page = $this->createMock( \WikiPage::class );
		$page->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( \Title::makeTitle( NS_FILE, self::TITLE ) );
		$page->expects( $this->once() )
			->method( 'doEditContent' )
			->withConsecutive(
				[
					new \WikitextContent( self::USER_WIKITEXT ),
					self::USER_SUMMARY,
					EDIT_UPDATE,
					false,
					$expectedUser
				]
			)
			->willReturn( new \Status() );

		$factory = $this->createMock( WikiPageFactory::class );
		$factory->expects( $this->once() )
			->method( 'newFromID' )
			->willReturn( $page );
		return $factory;
	}

	/**
	 * @return HttpRequestExecutor
	 */
	private function createHttpRequestExecutorMock() {
		$executor = $this->createMock( HttpRequestExecutor::class );
		// TODO: Never called because there are no file revisions (yet) in this test!
		$executor->expects( $this->never() )
			->method( 'executeAndSave' );
		return $executor;
	}

	/**
	 * @return UploadRevisionImporter
	 */
	private function createUploadRevisionImporterMock() {
		$importer = $this->createMock( UploadRevisionImporter::class );
		// TODO: Never called because there are no file revisions (yet) in this test!
		$importer->expects( $this->never() )
			->method( 'import' );
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
			->willReturn( true );
		return $creator;
	}

}
