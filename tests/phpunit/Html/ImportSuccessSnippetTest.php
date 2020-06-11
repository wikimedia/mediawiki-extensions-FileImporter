<?php

namespace FileImporter\Tests\Html;

use FileImporter\Html\ImportSuccessSnippet;
use FileImporter\Services\SuccessCache;
use HamcrestPHPUnitIntegration;
use HashBagOStuff;
use MediaWikiTestCase;
use Message;
use MessageLocalizer;
use StatusValue;
use Title;

/**
 * @covers \FileImporter\Html\ImportSuccessSnippet
 *
 * @license GPL-2.0-or-later
 */
class ImportSuccessSnippetTest extends MediaWikiTestCase {
	use HamcrestPHPUnitIntegration;

	public function testGetHtml_notOK() {
		$title = $this->createTitleWithResult( StatusValue::newFatal( 'fileimporter-badtoken' ) );

		$snippet = new ImportSuccessSnippet();
		$this->assertSame(
			'',
			$snippet->getHtml(
				$this->createMessageLocalizer(),
				$title
			)
		);
	}

	public function testGetHtml_successful() {
		$this->setContentLang( 'qqx' );

		$title = $this->createTitleWithResult( StatusValue::newGood( 'fileimporter-cleanup-summary' ) );

		$snippet = new ImportSuccessSnippet();
		$html = $snippet->getHtml(
			$this->createMessageLocalizer(),
			$title
		);

		$this->assertThatHamcrest(
			$html,
			is( htmlPiece(
				both( havingChild(
					both( withTagName( 'div' ) )
						->andAlso( withClass( 'successbox' ) )
						->andAlso( havingTextContents( '(fileimporter-cleanup-summary)' ) )
				) )
				->andAlso( not( havingChild(
					both( withTagName( 'div' ) )
					->andAlso( withClass( 'warningbox' ) )
				) ) )
			) )
		);
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

		$this->assertThatHamcrest(
			$html,
			is( htmlPiece(
				both( havingChild(
					both( withTagName( 'div' ) )
					->andAlso( withClass( 'successbox' ) )
					->andAlso( havingTextContents( '(fileimporter-cleanup-summary)' ) )
				) )
				->andAlso( havingChild(
					both( withTagName( 'div' ) )
					->andAlso( withClass( 'warningbox' ) )
					->andAlso( havingTextContents( '(fileimporter-import-wait)' ) )
				) )
			) )
		);
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
