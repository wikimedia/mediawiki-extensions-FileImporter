<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\ImportRequest;

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

		$this->assertEquals( $expectedRequestObjectText, $importRequest->getIntendedText() );
	}

}
