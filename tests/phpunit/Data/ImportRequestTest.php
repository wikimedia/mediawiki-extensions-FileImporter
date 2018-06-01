<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\ImportRequest;

/**
 * @covers \FileImporter\Data\ImportRequest
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class ImportRequestTest extends \PHPUnit\Framework\TestCase {

	public function provideTestRemoveTrailingWhitespacesInText() {
		return [
			[ 'Some Input Text', 'Some Input Text' ],
			[ 'Some Input Text ', 'Some Input Text' ],
			[ "Some Input Text \n", 'Some Input Text' ],
			[ null, null ],
		];
	}

	/**
	 * @dataProvider provideTestRemoveTrailingWhitespacesInText
	 * @param string $userInput
	 * @param string $expectedRequestObjectText
	 */
	public function testRemoveTrailingWhitespacesInText( $userInput, $expectedRequestObjectText ) {
		$importRequest = new ImportRequest( 'http://foo', null, $userInput );

		$this->assertSame( $expectedRequestObjectText, $importRequest->getIntendedText() );
	}

}
