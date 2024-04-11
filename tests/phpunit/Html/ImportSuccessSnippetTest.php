<?php

namespace FileImporter\Tests\Html;

use FileImporter\Html\ImportSuccessSnippet;
use FileImporter\Services\SuccessCache;
use HashBagOStuff;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use MessageLocalizer;
use MessageSpecifier;
use OOUI\BlankTheme;
use OOUI\Theme;
use StatusValue;

/**
 * @covers \FileImporter\Html\ImportSuccessSnippet
 *
 * @license GPL-2.0-or-later
 */
class ImportSuccessSnippetTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		Theme::setSingleton( new BlankTheme() );
	}

	protected function tearDown(): void {
		Theme::setSingleton();
		parent::tearDown();
	}

	public function testGetHtml_notOK() {
		$title = $this->createTitleWithResult( StatusValue::newFatal( 'fileimporter-badtoken' ) );
		$user = $this->createMock( User::class );

		$snippet = new ImportSuccessSnippet();
		$html = $snippet->getHtml( $this->createMessageLocalizer(), $title, $user );

		$this->assertStringContainsString( '<div class="mw-ext-fileimporter-noticebox">', $html );
		$this->assertStringContainsString( '(fileimporter-badtoken)', $html );
	}

	public function testGetHtml_successful() {
		$this->setUserLang( 'qqx' );

		$title = $this->createTitleWithResult( StatusValue::newGood( 'fileimporter-cleanup-summary' ) );
		$user = $this->createMock( User::class );

		$snippet = new ImportSuccessSnippet();
		$html = $snippet->getHtml(
			$this->createMessageLocalizer(),
			$title,
			$user
		);

		$this->assertStringContainsString( 'icon-success', $html );
		$this->assertStringContainsString( '(fileimporter-cleanup-summary)', $html );
		$this->assertStringNotContainsString( 'icon-alert', $html );
	}

	public function testGetHtml_warnings() {
		$this->setUserLang( 'qqx' );

		$resultStatus = StatusValue::newGood( 'fileimporter-cleanup-summary' );
		$resultStatus->warning( 'fileimporter-import-wait' );
		$title = $this->createTitleWithResult( $resultStatus );
		$user = $this->createMock( User::class );

		$snippet = new ImportSuccessSnippet();
		$html = $snippet->getHtml(
			$this->createMessageLocalizer(),
			$title,
			$user
		);

		$this->assertStringContainsString( 'icon-success', $html );
		$this->assertStringContainsString( '(fileimporter-cleanup-summary)', $html );
		$this->assertStringContainsString( 'icon-alert', $html );
		$this->assertStringContainsString( '(fileimporter-import-wait)', $html );
	}

	/**
	 * @return Title
	 */
	private function createTitleWithResult( StatusValue $status ) {
		$title = Title::makeTitle( NS_FILE, __METHOD__ );
		$user = $this->createMock( User::class );
		$cache = new SuccessCache( new HashBagOStuff() );
		$cache->stashImportResult( $title, $user, $status );
		$this->setService( 'FileImporterSuccessCache', $cache );
		return $title;
	}

	/**
	 * @return MessageLocalizer
	 */
	private function createMessageLocalizer() {
		$localizer = $this->createMock( MessageLocalizer::class );
		$localizer->method( 'msg' )->willReturnCallback( function ( $msg ): Message {
			$key = $msg instanceof MessageSpecifier ? $msg->getKey() : $msg;
			return $this->getMockMessage( "($key)" );
		} );
		return $localizer;
	}

}
