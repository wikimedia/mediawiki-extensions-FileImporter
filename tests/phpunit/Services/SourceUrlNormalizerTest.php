<?php

namespace FileImporter\Tests\Services;

use FileImporter\Data\SourceUrl;
use FileImporter\Services\SourceUrlNormalizer;

/**
 * @covers \FileImporter\Services\SourceUrlNormalizer
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class SourceUrlNormalizerTest extends \PHPUnit\Framework\TestCase {

	public function testNormalize() {
		$url = new SourceUrl( '//wikimedia.de' );
		$normalizer = new SourceUrlNormalizer( function ( SourceUrl $parameter ) use ( $url ) {
			$this->assertSame( $url, $parameter );
			return '<NORMALIZED>';
		} );

		$this->assertSame( '<NORMALIZED>', $normalizer->normalize( $url ) );
	}

}
