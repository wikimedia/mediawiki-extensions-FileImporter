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
class FileTextRevisionValidatorTest extends \MediaWikiTestCase {

	public function testAbuseFilterHook() {
		$validator = new FileTextRevisionValidator();

		$title = $this->createMock( \Title::class );
		$title->method( 'getNamespace' )
			->willReturn( NS_FILE );

		$user = $this->getTestUser()->getUser();
		$content = $this->createMock( \Content::class );
		$summary = '';
		$minor = false;

		$this->mergeMwGlobalArrayValue( 'wgHooks', [
			'EditFilterMergedContent' => [ function (
				$context,
				$content,
				\Status $status,
				$summary,
				$user,
				$minor
			) {
				$status->warning( new \RawMessage( '<RAW>' ) );
			} ],
		] );

		$this->setExpectedException( ImportException::class, '<RAW>' );
		$validator->validate( $title, $user, $content, $summary, $minor );
	}

}
