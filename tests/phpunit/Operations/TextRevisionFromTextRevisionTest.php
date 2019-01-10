<?php

namespace FileImporter\Tests\Operations;

use FileImporter\Data\TextRevision;
use FileImporter\Operations\TextRevisionFromTextRevision;
use FileImporter\Services\FileTextRevisionValidator;
use FileImporter\Services\WikiRevisionFactory;
use HashConfig;
use ImportableOldRevisionImporter;
use MediaWiki\MediaWikiServices;
use Psr\Log\NullLogger;
use Title;

/**
 * @covers \FileImporter\Operations\TextRevisionFromTextRevision
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class TextRevisionFromTextRevisionTest extends \MediaWikiTestCase {

	const TITLE = 'Test-29e8a6ff58c5eb980fc0642a13b59cb9c5a3cf66.png';

	public function testPrepare() {
		$title = Title::newFromText( self::TITLE, NS_FILE );
		$textRevisionFromTextRevision = $this->newTextRevisionFromTextRevision( $title );

		$this->assertNull( $textRevisionFromTextRevision->getWikiRevision() );

		$result = $textRevisionFromTextRevision->prepare();
		$wikiRevision = $textRevisionFromTextRevision->getWikiRevision();

		$this->assertTrue( $result );
		$this->assertFalse( $title->exists() );
		$this->assertTrue( $title->isWikitextPage() );
		$this->assertSame( 0, $wikiRevision->getID() );
		$this->assertSame( $title, $wikiRevision->getTitle() );
		$this->assertSame( 'imported>SourceUser1', $wikiRevision->getUser() );
		$this->assertSame( 'Original text of Test.png', $wikiRevision->getText() );
		$this->assertSame( 'Original upload comment of Test.png', $wikiRevision->getComment() );
		$this->assertSame( '20180624133723', $wikiRevision->getTimestamp() );
		$this->assertSame( 'text/x-wiki', $wikiRevision->getFormat() );
		$this->assertSame( 'TextSHA1', $wikiRevision->getSha1Base36() );
		$this->assertFalse( $wikiRevision->getMinor() );
	}

	public function testValidate() {
		$title = Title::newFromText( self::TITLE, NS_FILE );
		$textRevisionFromTextRevision = $this->newTextRevisionFromTextRevision( $title );

		$textRevisionFromTextRevision->prepare();
		$this->assertFalse( $title->exists() );
		$this->assertTrue( $textRevisionFromTextRevision->validate() );
	}

	public function testCommit() {
		$title = Title::newFromText( self::TITLE, NS_FILE );
		$textRevisionFromTextRevision = $this->newTextRevisionFromTextRevision( $title );

		$textRevisionFromTextRevision->prepare();
		$textRevisionFromTextRevision->validate();

		$this->assertFalse( $title->exists() );

		$textRevisionFromTextRevision->commit();

		$this->assertTrue( $title->exists() );
		$firstRevision = $title->getFirstRevision();

		$this->assertSame( 'imported>SourceUser1', $firstRevision->getUserText() );
		$this->assertSame( 'Original upload comment of Test.png', $firstRevision->getComment() );
		$this->assertSame( '20180624133723', $firstRevision->getTimestamp() );
		$this->assertFalse( $firstRevision->isMinor() );
	}

	private function newTextRevisionFromTextRevision( Title $title ) {
		$services = MediaWikiServices::getInstance();
		$logger = new NullLogger();

		$oldRevisionImporter = new ImportableOldRevisionImporter(
			true,
			$logger,
			$services->getDBLoadBalancer()
		);

		return new TextRevisionFromTextRevision(
			$title,
			$this->getTestUser()->getUser(),
			$this->newTextRevision(),
			new WikiRevisionFactory( new HashConfig() ),
			$oldRevisionImporter,
			$this->newFileTextRevisionValidator(),
			$logger
		);
	}

	/**
	 * @return FileTextRevisionValidator
	 */
	private function newFileTextRevisionValidator() {
		$mock = $this->createMock( FileTextRevisionValidator::class );
		$mock->method( 'validate' )
			->willReturn( true );
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
		] );
	}

}
