<?php

namespace FileImporter\Tests\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\RemoteApiActionExecutor;
use FileImporter\Remote\MediaWiki\RemoteApiRequestExecutor;
use MediaWikiUnitTestCase;
use User;

/**
 * @covers \FileImporter\Remote\MediaWiki\RemoteApiActionExecutor
 *
 * @license GPL-2.0-or-later
 */
class RemoteApiActionExecutorTest extends MediaWikiUnitTestCase {

	public function testExecuteEditAction_noToken() {
		$mockRequestExecutor = $this->createMock( RemoteApiRequestExecutor::class );
		$mockRequestExecutor
			->expects( $this->never() )
			->method( 'execute' );

		$remoteApiActionExecutor = new RemoteApiActionExecutor( $mockRequestExecutor );
		$this->assertNull( $remoteApiActionExecutor->executeEditAction(
			$this->createMock( SourceUrl::class ),
			$this->createMock( User::class ),
			[]
		) );
	}

	public function testExecuteEditAction_success() {
		$token = mt_rand() . 'abc';
		$mockRequestExecutor = $this->createMock( RemoteApiRequestExecutor::class );
		$mockRequestExecutor
			->method( 'getCsrfToken' )
			->willReturn( $token );
		$mockRequestExecutor
			->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->isInstanceOf( SourceUrl::class ),
				$this->isInstanceOf( User::class ),
				[
					'action' => 'edit',
					'format' => 'json',
					'prepend' => 'text',
					'token' => $token,
				]
			)
			// FIXME: unrealistic result
			->willReturn( [ 'success' ] );

		$remoteApiActionExecutor = new RemoteApiActionExecutor( $mockRequestExecutor );
		$result = $remoteApiActionExecutor->executeEditAction(
			$this->createMock( SourceUrl::class ),
			$this->createMock( User::class ),
			[ 'prepend' => 'text' ]
		);
		$this->assertEquals( [ 'success' ], $result );
	}

}
