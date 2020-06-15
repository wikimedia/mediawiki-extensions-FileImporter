<?php

namespace FileImporter\Tests\Html;

use FileImporter\Html\ImportSuccessSnippet;
use FileImporter\Services\SuccessCache;
use HashBagOStuff;
use MediaWikiTestCase;
use Message;
use MessageLocalizer;
use OOUI\BlankTheme;
use OOUI\Theme;
use StatusValue;
use Title;

/**
 * @covers \FileImporter\Html\ImportSuccessSnippet
 *
 * @license GPL-2.0-or-later
 */
class ImportSuccessSnippetTest extends MediaWikiTestCase {

	public function setUp() : void {
		parent::setUp();
		Theme::setSingleton( new BlankTheme() );
	}

	public function tearDown() : void {
		Theme::setSingleton( null );
		parent::tearDown();
	}

	public function testGetHtml_notOK() {
		$title = $this->createTitleWithResult( StatusValue::newFatal( 'fileimporter-badtoken' ) );

		$snippet = new ImportSuccessSnippet();
		$html = $snippet->getHtml( $this->createMessageLocalizer(), $title );

		$this->assertStringContainsString( '<div class="mw-ext-fileimporter-noticebox">', $html );
		$this->assertStringContainsString( '(fileimporter-badtoken)', $html );
	}

	public function testGetHtml_successful() {
		$this->setContentLang( 'qqx' );

		$title = $this->createTitleWithResult( StatusValue::newGood( 'fileimporter-cleanup-summary' ) );

		$snippet = new ImportSuccessSnippet();
		$html = $snippet->getHtml(
			$this->createMessageLocalizer(),
			$title
		);

		$this->assertStringContainsString( 'icon-check', $html );
		$this->assertStringContainsString( '(fileimporter-cleanup-summary)', $html );
		$this->assertStringNotContainsString( 'icon-alert', $html );
	}

	public function testGetHtml_warnings() {
		$this->setContentLang( 'qqx' );

		$resultStatus = StatusValue::newGood( 'fileimporter-cleanup-summary' );
		$resultStatus->warning( 'fileimporter-import-wait' );
		$title = $this->createTitleWithResult( $resultStatus );

		$snippet = new ImportSuccessSnippet();
		$html = $snippet->getHtml(
			$this->createMessageLocalizer(),
			$title
		);

		$this->assertStringContainsString( 'icon-check', $html );
		$this->assertStringContainsString( '(fileimporter-cleanup-summary)', $html );
		$this->assertStringContainsString( 'icon-alert', $html );
		$this->assertStringContainsString( '(fileimporter-import-wait)', $html );
	}

	/**
	 * @param StatusValue $status
	 *
	 * @return Title
	 */
	private function createTitleWithResult( StatusValue $status ) {
		$title = $this->createMock( Title::class );
		$cache = new SuccessCache( new HashBagOStuff() );
		$cache->stashImportResult( $title, $status );
		$this->setService( 'FileImporterSuccessCache', $cache );
		return $title;
	}

	/**
	 * @return MessageLocalizer
	 */
	private function createMessageLocalizer() {
		$localizer = $this->createMock( MessageLocalizer::class );
		$localizer->method( 'msg' )->willReturnCallback( function ( $key ) {
			$msg = $this->createMock( Message::class );
			$msg->method( 'parse' )->willReturn( "($key)" );
			return $msg;
		} );
		return $localizer;
	}

}
