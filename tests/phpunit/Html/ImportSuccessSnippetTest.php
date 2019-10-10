<?php

namespace FileImporter\Tests\Html;

use FileImporter\Html\ImportSuccessSnippet;
use FileImporter\Services\SuccessCache;
use HamcrestPHPUnitIntegration;
use MediaWikiTestCase;
use RequestContext;
use StatusValue;
use Title;

/**
 * @covers \FileImporter\Html\ImportSuccessSnippet
 */
class ImportSuccessSnippetTest extends MediaWikiTestCase {
	use HamcrestPHPUnitIntegration;

	public function testGetHtml_notOK() {
		$mockCache = $this->createMock( SuccessCache::class );
		$mockCache
			->method( 'fetchImportResult' )
			->willReturn( StatusValue::newFatal( 'fileimporter-badtoken' ) );
		$this->setService( 'FileImporterSuccessCache', $mockCache );

		$snippet = new ImportSuccessSnippet();
		$this->assertSame(
			'',
			$snippet->getHtml(
				RequestContext::getMain(),
				$this->createMock( Title::class )
			)
		);
	}

	public function testGetHtml_successful() {
		$this->setContentLang( 'qqx' );

		$mockCache = $this->createMock( SuccessCache::class );
		$mockCache
			->method( 'fetchImportResult' )
			->willReturn( StatusValue::newGood( 'fileimporter-cleanup-summary' ) );
		$this->setService( 'FileImporterSuccessCache', $mockCache );

		$snippet = new ImportSuccessSnippet();
		$html = $snippet->getHtml(
			RequestContext::getMain(),
			$this->createMock( Title::class )
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
		$mockCache = $this->createMock( SuccessCache::class );
		$mockCache
			->method( 'fetchImportResult' )
			->willReturn( $resultStatus );
		$this->setService( 'FileImporterSuccessCache', $mockCache );

		$snippet = new ImportSuccessSnippet();
		$html = $snippet->getHtml(
			RequestContext::getMain(),
			$this->createMock( Title::class )
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

}
