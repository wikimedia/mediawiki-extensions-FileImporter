<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\FileRevision;
use FileImporter\Data\TextRevision;
use FileImporter\Services\WikiRevisionFactory;
use MediaWiki\MediaWikiServices;
use Wikimedia\TestingAccessWrapper;

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
			$wikiRevisionFactory->setInterWikiPrefix( $prefix );
		}

		$revision = $wikiRevisionFactory->newFromTextRevision( $this->createTextRevision() );

		$this->assertSame( true, $revision->getMinor() );
		$this->assertSame( $expected . '>TestUser', $revision->getUser() );
		$this->assertSame( '19700101000042', $revision->getTimestamp() );
		$this->assertSame( 'SHA1HASH', $revision->getSha1Base36() );
		$this->assertSame( 'cf', $revision->getFormat() );
		$this->assertSame( 'cm', $revision->getModel() );
		if ( $prefix ) {
			$this->assertSame( "TestComment [[$prefix:Link]]", $revision->getComment() );
		} else {
			$this->assertSame( 'TestComment [[Link]]', $revision->getComment() );
		}
		$this->assertSame( 'TestText', $revision->getText() );
		$this->assertSame( 'TestTitle', $revision->getTitle()->getText() );
	}

	/**
	 * @dataProvider provideNewFromWithPrefix
	 */
	public function testNewFromFileWithPrefix( $prefix ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$wikiRevisionFactory = new WikiRevisionFactory( $config );

		if ( $prefix !== null ) {
			$wikiRevisionFactory->setInterWikiPrefix( $prefix );
		}

		$revision = $wikiRevisionFactory->newFromFileRevision( $this->createFileRevision(), '' );

		$this->assertSame( false, $revision->getMinor() );
		$this->assertSame( 'TestUser', $revision->getUser() );
		$this->assertSame( '19700101000042', $revision->getTimestamp() );
		$this->assertSame( 'SHA1HASH', $revision->getSha1Base36() );
		$this->assertSame( 'TestFileName', $revision->getTitle()->getText() );
		$this->assertSame( '', $revision->getFileSrc() );
	}

	public function providePrefixCommentLinks() {
		return [
			// empty prefix leaves links untouched
			[ '', 'See [[Link]]', 'See [[Link]]' ],
			// don't prefix external links
			[ 'w', 'See [//de.wikipedia.org]', 'See [//de.wikipedia.org]' ],
			[ 'w', 'See [//de.wikipedia.org Test]', 'See [//de.wikipedia.org Test]' ],
			// add prefix to links
			[ 'w', 'See [[Link]]', 'See [[w:Link]]' ],
			[ 'w', 'See [[:Link]]', 'See [[w:Link]]' ],
			[ 'w', 'See [[Link Target]]', 'See [[w:Link Target]]' ],
			[ 'w', 'See [[ : Link Target]]', 'See [[w: Link Target]]' ],
			[ 'w', 'See [[Link]] and [[Link2]]', 'See [[w:Link]] and [[w:Link2]]' ],
			// keep link text intact
			[ 'w', 'See [[Link | click here]]', 'See [[w:Link | click here]]' ],
			[ 'w', 'See [[Link Target|click here]]', 'See [[w:Link Target|click here]]' ],
			[ 'w', 'See [[Link | [Bracket] Text]]', 'See [[w:Link | [Bracket] Text]]' ],
			// on semi broke links prefix the inner part
			[ 'w', 'See [[Link[[Link2]]', 'See [[Link[[w:Link2]]' ],
			// don't prefix completely broken link tags
			[ 'w', 'See [[Link | ', 'See [[Link | ' ],
			[ 'w', 'See Link]]', 'See Link]]' ],
			[ 'w', 'See [Link]]', 'See [Link]]' ],
			[ 'w', 'See [[Link]', 'See [[Link]' ],
		];
	}

	/**
	 * @dataProvider providePrefixCommentLinks
	 */
	public function testPrefixCommentLinks( $prefix, $input, $expected ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$wikiRevisionFactory = TestingAccessWrapper::newFromObject(
			new WikiRevisionFactory( $config )
		);

		$wikiRevisionFactory->setInterWikiPrefix( $prefix );

		$this->assertSame( $expected, $wikiRevisionFactory->prefixCommentLinks( $input ) );
	}

	private function createTextRevision() {
		return new TextRevision( [
			'minor' => true,
			'user' => 'TestUser',
			'timestamp' => '42',
			'sha1' => 'SHA1HASH',
			'contentmodel' => 'cm',
			'contentformat' => 'cf',
			'comment' => 'TestComment [[Link]]',
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
