<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use User;

class RemoteApiActionExecutor {

	/**
	 * @var RemoteApiRequestExecutor
	 */
	private $remoteApiRequestExecutor;

	/**
	 * @param RemoteApiRequestExecutor $remoteApiRequestExecutor
	 */
	public function __construct( RemoteApiRequestExecutor $remoteApiRequestExecutor ) {
		$this->remoteApiRequestExecutor = $remoteApiRequestExecutor;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @param array $params
	 * @return array|null
	 */
	public function executeEditAction(
		SourceUrl $sourceUrl,
		User $user,
		array $params
	) {
		$token = $this->remoteApiRequestExecutor->getCsrfToken( $sourceUrl, $user );

		if ( $token === null ) {
			return null;
		}

		return $this->remoteApiRequestExecutor->execute(
			$sourceUrl,
			$user,
			array_merge(
				[
					'action' => 'edit',
					'token' => $token,
					'format' => 'json',
				],
				$params
			),
			true
		);
	}

}
