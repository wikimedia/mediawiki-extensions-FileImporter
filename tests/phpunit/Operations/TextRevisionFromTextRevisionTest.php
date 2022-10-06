<?php

namespace FileImporter\Tests\Operations;

use FileImporter\Data\TextRevision;
use FileImporter\Operations\TextRevisionFromTextRevision;
use FileImporter\Services\FileTextRevisionValidator;
use FileImporter\Services\WikiRevisionFactory;
use ImportableOldRevisionImporter;
use MediaWiki\MediaWikiServices;
use Psr\Log\NullLogger;
use StatusValue;
use Title;

/**
 * @covers \FileImporter\Operations\TextRevisionFromTextRevision
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class TextRevisionFromTextRevisionTest extends \MediaWikiIntegrationTestCase {

	private const TITLE = 'Test-29e8a6ff58c5eb980fc0642a13b59cb9c5a3cf66.png';

	public function testPrepare() {
		$title = Title::newFromText( self::TITLE, NS_FILE );
		$textRevisionFromTextRevision = $this->newTextRevisionFromTextRevision( $title );

		$this->assertNull( $textRevisionFromTextRevision->getWikiRevision() );

		$status = $textRevisionFromTextRevision->prepare();
		$wikiRevision = $textRevisionFromTextRevision->getWikiRevision();

		$this->assertTrue( $status->isOK() );
		$this->assertFalse( $title->exists() );
		$this->assertTrue( $title->isWikitextPage() );
		$this->assertSame( 0, $wikiRevision->getID() );
		$this->assertSame( $title, $wikiRevision->getTitle() );
		$this->assertSame( 'Imported>SourceUser1', $wikiRevision->getUser() );
		$this->assertSame( 'Original text of Test.png', $wikiRevision->getText() );
		$this->assertSame( 'Original upload comment of Test.png', $wikiRevision->getComment() );
		$this->assertSame( '20180624133723', $wikiRevision->getTimestamp() );
		$this->assertSame( 'text/x-wiki', $wikiRevision->getContent()->getDefaultFormat() );
		$this->assertSame( 'TextSHA1', $wikiRevision->getSha1Base36() );
		$this->assertFalse( $wikiRevision->getMinor() );
	}

	public function testValidate() {
		$title = Title::newFromText( self::TITLE, NS_FILE );
		$textRevisionFromTextRevision = $this->newTextRevisionFromTextRevision( $title );

		$this->assertTrue( $textRevisionFromTextRevision->prepare()->isOK() );
		$this->assertFalse( $title->exists() );
		$this->assertTrue( $textRevisionFromTextRevision->validate()->isOK() );
	}

	public function testCommit() {
		$title = Title::newFromText( self::TITLE, NS_FILE );
		$textRevisionFromTextRevision = $this->newTextRevisionFromTextRevision( $title );

		$this->assertTrue( $textRevisionFromTextRevision->prepare()->isOK() );
		$this->assertTrue( $textRevisionFromTextRevision->validate()->isOK() );

		$this->assertFalse( $title->exists() );

		$this->assertTrue( $textRevisionFromTextRevision->commit()->isOK() );

		$this->assertTrue( $title->exists() );
		$firstRevision = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getFirstRevision( $title );

		$this->assertNotNull( $firstRevision->getUser() );
		$this->assertSame( 'Imported>SourceUser1', $firstRevision->getUser()->getName() );
		$this->assertNotNull( $firstRevision->getComment() );
		$this->assertSame(
			'Original upload comment of Test.png',
			$firstRevision->getComment()->text
		);
		$this->assertSame( '20180624133723', $firstRevision->getTimestamp() );
		$this->assertFalse( $firstRevision->isMinor() );
	}

	private function newTextRevisionFromTextRevision( Title $title ) {
		$services = MediaWikiServices::getInstance();
		$oldRevisionImporter = new ImportableOldRevisionImporter(
			true,
			new NullLogger(),
			$services->getDBLoadBalancer(),
			$services->getRevisionStore(),
			$services->getSlotRoleRegistry(),
			$services->getWikiPageFactory()
		);

		return new TextRevisionFromTextRevision(
			$title,
			$this->getTestUser()->getUser(),
			$this->newTextRevision(),
			new WikiRevisionFactory(),
			$oldRevisionImporter,
			$this->newFileTextRevisionValidator(),
			$services->getRestrictionStore()
		);
	}

	private function newFileTextRevisionValidator(): FileTextRevisionValidator {
		$mock = $this->createMock( FileTextRevisionValidator::class );
		$mock->method( 'validate' )
			->willReturn( StatusValue::newGood() );
		return $mock;
	}

	private function newTextRevision() {
		return new TextRevision( [
			'minor' => '',
			'user' => 'SourceUser1',
			'timestamp' => '2018-06-24T13:37:23Z',
			'sha1' => 'TextSHA1',
			'contentmodel' => 'wikitext',
			'contentformat' => 'text/x-wiki',
			'comment' => 'Original upload comment of Test.png',
			'*' => 'Original text of Test.png',
			'title' => 'Test.png',
			'tags' => [],
		] );
	}

}
