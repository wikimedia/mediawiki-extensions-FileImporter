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
		$status = $remoteApiActionExecutor->executeEditAction(
			$this->createMock( SourceUrl::class ),
			$this->createMock( User::class ),
			'',
			[],
			''
		);
		$this->assertFalse( $status->isOK() );
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
					'title' => 'TestTitle',
					'summary' => 'TestSummary',
					'prepend' => 'text',
					'token' => $token,
					'tags' => RemoteApiActionExecutor::CHANGE_TAG,
				]
			)
			// FIXME: unrealistic result
			->willReturn( [ 'success' ] );

		$remoteApiActionExecutor = new RemoteApiActionExecutor( $mockRequestExecutor );
		$status = $remoteApiActionExecutor->executeEditAction(
			$this->createMock( SourceUrl::class ),
			$this->createMock( User::class ),
			'TestTitle',
			[ 'prepend' => 'text' ],
			'TestSummary'
		);
		$this->assertTrue( $status->isGood() );
	}

}
