<?php

namespace FileImporter\Tests\Services;

use FileImporter\Services\FileTextRevisionValidator;
use MediaWiki\Content\TextContent;
use MediaWiki\Context\IContextSource;
use MediaWiki\Page\WikiFilePage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * @covers \FileImporter\Services\FileTextRevisionValidator
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class FileTextRevisionValidatorTest extends \MediaWikiLangTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->clearHooks();
	}

	public function testSuccess() {
		$validator = new FileTextRevisionValidator();
		$title = Title::makeTitle( NS_FILE, __METHOD__ );
		$user = $this->createNoOpMock( User::class );
		$content = new TextContent( '' );

		$status = $validator->validate( $title, $user, $content, '', false );
		$this->assertStatusGood( $status );
	}

	public function testInvalidNamespace() {
		$validator = new FileTextRevisionValidator();
		$title = Title::makeTitle( NS_MAIN, __METHOD__ );
		$user = $this->createNoOpMock( User::class );
		$content = new TextContent( '' );

		$status = $validator->validate( $title, $user, $content, '', false );
		$this->assertFalse( $status->isOK() );
		$this->assertTrue( $status->hasMessage( 'fileimporter-badnamespace' ) );
	}

	public function testAbuseFilterHook() {
		$validator = new FileTextRevisionValidator();
		$expectedTitle = Title::makeTitle( NS_FILE, __METHOD__ );
		$expectedUser = $this->createNoOpMock( User::class );
		$expectedContent = new TextContent( '' );

		$this->setTemporaryHook(
			'EditFilterMergedContent',
			function (
				IContextSource $context,
				$content,
				\StatusValue $status,
				$summary,
				$user,
				$minor
			) use ( $expectedTitle, $expectedContent, $expectedUser ) {
				// Check if all expected values make it to this AbuseFilter hook
				$wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();
				$wikiPage = $wikiPageFactory->newFromTitle( $context->getTitle() );
				$this->assertSame( $expectedUser, $context->getUser() );
				$this->assertSame( $expectedTitle, $context->getTitle() );
				$this->assertInstanceOf( WikiFilePage::class, $wikiPage );
				$this->assertSame( $expectedTitle, $wikiPage->getTitle() );
				$this->assertSame( $expectedContent, $content );
				$this->assertSame( '<SUMMARY>', $summary );
				$this->assertSame( $expectedUser, $user );
				$this->assertTrue( $minor );

				/**
				 * AbuseFilter communicates with callers via ApiMessage objects,
				 * {@see FilteredActionsHandler::getApiStatus}. The isOK status is meaningless,
				 * that's why we test with a warning.
				 */
				$status->warning( $this->getMockMessage( '<RAW>' ) );
			}
		);

		$status = $validator->validate( $expectedTitle, $expectedUser, $expectedContent, '<SUMMARY>', true );
		$this->assertStatusWarning( '<RAW>', $status );
	}

}
