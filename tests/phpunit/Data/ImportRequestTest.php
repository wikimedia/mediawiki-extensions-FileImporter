<?php

namespace FileImporter\Data\Test;

use FileImporter\Data\ImportRequest;
use FileImporter\Exceptions\LocalizedImportException;

/**
 * @covers \FileImporter\Data\ImportRequest
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class ImportRequestTest extends \PHPUnit\Framework\TestCase {
	use \PHPUnit4And6Compat;

	public function testConstructor() {
		$url = 'https://ar.wikipedia.org/wiki/ملف:1967+TUN.jpg';
		$importRequest = new ImportRequest( $url, '<NAME>', '<TEXT>', '<SUMMARY>' );

		$this->assertSame( $url, $importRequest->getUrl()->getUrl() );
		$this->assertSame( '<NAME>', $importRequest->getIntendedName() );
		$this->assertSame( '<TEXT>', $importRequest->getIntendedText() );
		$this->assertSame( '<SUMMARY>', $importRequest->getIntendedSummary() );
	}

	public function testInvalidUrl() {
		$this->setExpectedException( LocalizedImportException::class );
		new ImportRequest( 'invalid' );
	}

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
