<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\FileRevision;
use FileImporter\Data\TextRevision;
use FileImporter\Services\WikiRevisionFactory;
use MediaWiki\MediaWikiServices;
use User;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \FileImporter\Services\WikiRevisionFactory
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class WikiRevisionFactoryTest extends \MediaWikiTestCase {

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
		$testUserName = $this->getRandomUserName();

		if ( $prefix !== null ) {
			$wikiRevisionFactory->setInterWikiPrefix( $prefix );
		}

		$revision = $wikiRevisionFactory->newFromTextRevision(
			$this->createTextRevision( $testUserName )
		);

		$this->assertSame( true, $revision->getMinor() );
		$this->assertSame( $expected . '>' . $testUserName, $revision->getUser() );
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
	public function testNewFromFileWithPrefix( $prefix, $expected ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$wikiRevisionFactory = new WikiRevisionFactory( $config );
		$testUserName = $this->getRandomUserName();

		if ( $prefix !== null ) {
			$wikiRevisionFactory->setInterWikiPrefix( $prefix );
		}

		$revision = $wikiRevisionFactory->newFromFileRevision(
			$this->createFileRevision( $testUserName ),
			''
		);

		$this->assertFalse( $revision->getMinor() );
		$this->assertSame( $expected . '>' . $testUserName, $revision->getUser() );
		$this->assertFalse( $revision->getUserObj() );
		$this->assertSame( '19700101000042', $revision->getTimestamp() );
		$this->assertSame( 'SHA1HASH', $revision->getSha1Base36() );
		$this->assertSame( 'TestFileName', $revision->getTitle()->getText() );
		$this->assertSame( '', $revision->getFileSrc() );
	}

	public function testUserAutoCreation() {
		$textUserName = $this->getRandomUserName();
		$fileUserName = $this->getRandomUserName();

		// mock the hook that would trigger user creation
		$localUserId = null;
		$this->setMwGlobals( 'wgHooks', [
			'ImportHandleUnknownUser' => [ function ( $name ) use ( &$localUserId ) {
				$user = User::createNew( $name );
				$this->assertNotNull( $user );
				$localUserId = $user->getId();
				return false;
			} ]
		] );

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$wikiRevisionFactory = new WikiRevisionFactory( $config );
		$wikiRevisionFactory->setInterWikiPrefix( 'prefix' );

		$textRevision = $wikiRevisionFactory->newFromTextRevision(
			$this->createTextRevision( $textUserName )
		);

		$this->assertSame( $textUserName, $textRevision->getUser() );
		$this->assertSame( $localUserId, User::idFromName( $textUserName ) );
		$this->assertSame( 'TestComment [[prefix:Link]]', $textRevision->getComment() );

		$fileRevision = $wikiRevisionFactory->newFromFileRevision(
			$this->createFileRevision( $fileUserName ),
			''
		);

		$this->assertSame( $fileUserName, $fileRevision->getUser() );
		$this->assertSame( $localUserId, User::idFromName( $fileUserName ) );
	}

	public function testExistingUserAttribution() {
		$existingUser = $this->getTestUser()->getUser();

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$wikiRevisionFactory = new WikiRevisionFactory( $config );
		$wikiRevisionFactory->setInterWikiPrefix( 'prefix' );

		$textRevision = $wikiRevisionFactory->newFromTextRevision(
			$this->createTextRevision( $existingUser->getName() )
		);

		$this->assertSame( $existingUser->getName(), $textRevision->getUser() );
		$this->assertSame( $existingUser->getId(), User::idFromName( $textRevision->getUser() ) );

		$fileRevision = $wikiRevisionFactory->newFromFileRevision(
			$this->createFileRevision( $existingUser->getName() ),
			''
		);

		$this->assertSame( $existingUser->getName(), $fileRevision->getUser() );
		$this->assertSame( $existingUser->getId(), User::idFromName( $fileRevision->getUser() ) );
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
		/** @var WikiRevisionFactory $wikiRevisionFactory */
		$wikiRevisionFactory = TestingAccessWrapper::newFromObject(
			new WikiRevisionFactory( $config )
		);

		$wikiRevisionFactory->setInterWikiPrefix( $prefix );

		$this->assertSame( $expected, $wikiRevisionFactory->prefixCommentLinks( $input ) );
	}

	private function createTextRevision( $userName ) {
		return new TextRevision( [
			'minor' => true,
			'user' => $userName,
			'timestamp' => '42',
			'sha1' => 'SHA1HASH',
			'contentmodel' => 'cm',
			'contentformat' => 'cf',
			'comment' => 'TestComment [[Link]]',
			'*' => 'TestText',
			'title' => 'File:TestTitle',
		] );
	}

	private function createFileRevision( $userName ) {
		return new FileRevision( [
			'name' => 'TestFileName',
			'description' => 'TestFileText',
			'user' => $userName,
			'timestamp' => '42',
			'sha1' => 'SHA1HASH',
			'size' => '23',
			'thumburl' => 'testthumb.url',
			'url' => 'testimg.url',
		] );
	}

	private function getRandomUserName() {
		return 'TestUser ' . wfRandomString( 16 );
	}

}
