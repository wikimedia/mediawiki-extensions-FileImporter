<?php

namespace FileImporter\Tests\Services;

use FileImporter\Services\FileTextRevisionValidator;

/**
 * @covers \FileImporter\Services\FileTextRevisionValidator
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class FileTextRevisionValidatorTest extends \MediaWikiLangTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( 'wgHooks', [] );
	}

	public function testSuccess() {
		$validator = new FileTextRevisionValidator();
		$title = \Title::makeTitle( NS_FILE, __METHOD__ );
		$user = $this->getTestUser()->getUser();
		$content = new \TextContent( '' );

		$status = $validator->validate( $title, $user, $content, '', false );
		$this->assertTrue( $status->isOK() );
	}

	public function testInvalidNamespace() {
		$validator = new FileTextRevisionValidator();
		$title = \Title::makeTitle( NS_MAIN, __METHOD__ );
		$user = $this->getTestUser()->getUser();
		$content = new \TextContent( '' );

		$status = $validator->validate( $title, $user, $content, '', false );
		$this->assertFalse( $status->isOK() );
		$this->assertSame( 'fileimporter-badnamespace', $status->getMessage()->getKey() );
	}

	public function testAbuseFilterHook() {
		$validator = new FileTextRevisionValidator();
		$expectedTitle = \Title::makeTitle( NS_FILE, __METHOD__ );
		$expectedUser = $this->getTestUser()->getUser();
		$expectedContent = new \TextContent( '' );

		$this->setTemporaryHook(
			'EditFilterMergedContent',
			function (
				\IContextSource $context,
				$content,
				\StatusValue $status,
				$summary,
				$user,
				$minor
			) use ( $expectedTitle, $expectedContent, $expectedUser ) {
				// Check if all expected values make it to this AbuseFilter hook
				$this->assertSame( $expectedUser, $context->getUser() );
				$this->assertSame( $expectedTitle, $context->getTitle() );
				$this->assertInstanceOf( \WikiFilePage::class, $context->getWikiPage() );
				$this->assertSame( $expectedTitle, $context->getWikiPage()->getTitle() );
				$this->assertSame( $expectedContent, $content );
				$this->assertSame( '<SUMMARY>', $summary );
				$this->assertSame( $expectedUser, $user );
				$this->assertTrue( $minor );

				// This is the way AbuseFilter communicates with the caller
				$status->warning( new \RawMessage( '<RAW>' ) );
			}
		);

		$status = $validator->validate( $expectedTitle, $expectedUser, $expectedContent, '<SUMMARY>', true );
		$this->assertTrue( $status->isOK() );
		$this->assertSame( '<RAW>', $status->getMessage()->getKey() );
	}

}
