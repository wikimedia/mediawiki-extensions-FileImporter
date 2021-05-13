<?php

namespace FileImporter\Tests\Exceptions;

use FileImporter\Exceptions\AbuseFilterWarningsException;
use RawMessage;

/**
 * @covers \FileImporter\Exceptions\AbuseFilterWarningsException
 *
 * @license GPL-2.0-or-later
 */
class AbuseFilterWarningsExceptionTest extends \MediaWikiIntegrationTestCase {

	public function testException() {
		$messages = [ new RawMessage( 'example-message' ) ];
		$ex = new AbuseFilterWarningsException( $messages );

		$this->assertSame( 'warningabusefilter', $ex->getCode() );
		$this->assertSame( $messages, $ex->getMessages() );
		$msg = $ex->getMessageObject()->useDatabase( false );
		$this->assertSame( '(fileimporter-warningabusefilter)', $msg->inLanguage( 'qqx' )->plain() );
		$this->assertSame( $msg->inLanguage( 'en' )->plain(), $ex->getMessage() );
	}

}
