<?php

namespace FileImporter\Tests\Services\Http;

use FileImporter\Services\Http\FileChunkSaver;
use RuntimeException;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \FileImporter\Services\Http\FileChunkSaver
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class FileChunkSaverTest extends \MediaWikiIntegrationTestCase {

	public function provideSaveFileChunk() {
		return [
			'save less bytes then allowed' =>
				[ str_repeat( 'x', 10 ), 20, 10 ],
			'save exactly the bytes allowed' =>
				[ str_repeat( 'x', 20 ), 20, 20 ],
			'save empty buffer' =>
				[ '', 20, 0 ],
			'empty buffer and maxbytes is zero' =>
				[ '', 0, 0 ],
			'save more bytes then allowed' =>
				[ str_repeat( 'x', 20 ), 1, RuntimeException::class ],
			'maxbytes is zero' =>
				[ str_repeat( 'x', 20 ), 0, RuntimeException::class ],
		];
	}

	/**
	 * @dataProvider provideSaveFileChunk
	 */
	public function testSaveFileChunk( $buffer, $maxBytes, $expectedResult ) {
		$saver = $this->createChunkSaver( $maxBytes );

		if ( !is_int( $expectedResult ) ) {
			$this->expectException( $expectedResult );
		}

		$this->assertSame( $expectedResult, $saver->saveFileChunk( null, $buffer ) );
	}

	public function testGetHandleFails() {
		$saver = new FileChunkSaver( '', 0 );
		/** @var FileChunkSaver $saver */
		$saver = TestingAccessWrapper::newFromObject( $saver );

		$this->expectException( RuntimeException::class );

		$saver->getHandle();
	}

	private function createChunkSaver( $maxBytes ) {
		$file = $this->getNewTempFile();
		return new FileChunkSaver( $file, $maxBytes );
	}

}
