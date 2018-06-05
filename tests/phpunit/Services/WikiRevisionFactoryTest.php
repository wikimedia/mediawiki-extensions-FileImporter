<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\FileRevision;
use FileImporter\Data\TextRevision;
use FileImporter\Services\WikiRevisionFactory;
use MediaWiki\MediaWikiServices;

/**
 * @covers \FileImporter\Services\WikiRevisionFactory
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class WikiRevisionFactoryTest extends \PHPUnit\Framework\TestCase {

	public function provideNewFromWithPrefix() {
		return [
			[ null, WikiRevisionFactory::DEFAULT_USERNAME_PREFIX ],
			[ '', WikiRevisionFactory::DEFAULT_USERNAME_PREFIX ],
			[ 'test', 'test' ],
			[ 'test:en', 'test:en' ],
		];
	}

	/**
	 * @dataProvider provideNewFromWithPrefix
	 */
	public function testNewFromTextWithPrefix( $prefix, $expected ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$wikiRevisionFactory = new WikiRevisionFactory( $config );

		if ( $prefix !== null ) {
			$wikiRevisionFactory->setUserNamePrefix( $prefix );
		}

		$revision = $wikiRevisionFactory->newFromTextRevision( $this->createTextRevision() );

		$this->assertSame( true, $revision->getMinor() );
		$this->assertSame( $expected . '>TestUser', $revision->getUser() );
		$this->assertSame( '19700101000042', $revision->getTimestamp() );
		$this->assertSame( 'SHA1HASH', $revision->getSha1Base36() );
		$this->assertSame( 'cf', $revision->getFormat() );
		$this->assertSame( 'cm', $revision->getModel() );
		$this->assertSame( 'TestComment', $revision->getComment() );
		$this->assertSame( 'TestText', $revision->getText() );
		$this->assertSame( 'TestTitle', $revision->getTitle()->getText() );
	}

	/**
	 * @dataProvider provideNewFromWithPrefix
	 */
	public function testNewFromFileWithPrefix( $prefix, $expected ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$wikiRevisionFactory = new WikiRevisionFactory( $config );

		if ( $prefix !== null ) {
			$wikiRevisionFactory->setUserNamePrefix( $prefix );
		}

		$revision = $wikiRevisionFactory->newFromFileRevision( $this->createFileRevision(), '' );

		$this->assertSame( false, $revision->getMinor() );
		$this->assertSame( $expected . '>TestUser', $revision->getUser() );
		$this->assertSame( '19700101000042', $revision->getTimestamp() );
		$this->assertSame( 'SHA1HASH', $revision->getSha1Base36() );
		$this->assertSame( 'TestFileName', $revision->getTitle()->getText() );
		$this->assertSame( '', $revision->getFileSrc() );
	}

	private function createTextRevision() {
		return new TextRevision( [
			'minor' => true,
			'user' => 'TestUser',
			'timestamp' => '42',
			'sha1' => 'SHA1HASH',
			'contentmodel' => 'cm',
			'contentformat' => 'cf',
			'comment' => 'TestComment',
			'*' => 'TestText',
			'title' => 'File:TestTitle',
		] );
	}

	private function createFileRevision() {
		return new FileRevision( [
			'name' => 'TestFileName',
			'description' => 'TestFileText',
			'user' => 'TestUser',
			'timestamp' => '42',
			'sha1' => 'SHA1HASH',
			'size' => '23',
			'thumburl' => 'testthumb.url',
			'url' => 'testimg.url',
		] );
	}

}
