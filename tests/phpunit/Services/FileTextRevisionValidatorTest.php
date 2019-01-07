<?php

namespace FileImporter\Tests\Services;

use FileImporter\Exceptions\ImportException;
use FileImporter\Services\FileTextRevisionValidator;

/**
 * @covers \FileImporter\Services\FileTextRevisionValidator
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class FileTextRevisionValidatorTest extends \MediaWikiLangTestCase {

	public function testSuccess() {
		$validator = new FileTextRevisionValidator();
		$title = \Title::makeTitle( NS_FILE, __METHOD__ );
		$user = $this->getTestUser()->getUser();
		$content = $this->createMock( \Content::class );

		$validator->validate( $title, $user, $content, '', false );
		$this->addToAssertionCount( 1 );
	}

	public function testInvalidNamespace() {
		$validator = new FileTextRevisionValidator();
		$title = \Title::makeTitle( NS_MAIN, __METHOD__ );
		$user = $this->getTestUser()->getUser();
		$content = $this->createMock( \Content::class );

		$this->setExpectedException( ImportException::class, 'Wrong text revision namespace' );
		$validator->validate( $title, $user, $content, '', false );
	}

	public function testAbuseFilterHook() {
		$validator = new FileTextRevisionValidator();
		$expectedTitle = \Title::makeTitle( NS_FILE, __METHOD__ );
		$expectedUser = $this->getTestUser()->getUser();
		$expectedContent = $this->createMock( \Content::class );

		$this->mergeMwGlobalArrayValue( 'wgHooks', [
			'EditFilterMergedContent' => [ function (
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
			} ],
		] );

		$this->setExpectedException( ImportException::class, '<RAW>' );
		$validator->validate( $expectedTitle, $expectedUser, $expectedContent, '<SUMMARY>', true );
	}

}
