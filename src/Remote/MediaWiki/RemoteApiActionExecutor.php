<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use User;

/**
 * @license GPL-2.0-or-later
 */
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
	 * Possible return values:
	 * - { "query": { "pages": { "…": { "actions": { "edit": "" }, … } } } }
	 * - { "query": { "pages": { "…": { "actions": [], … } } } }
	 * - There should be no reason for this to ever return an error, but it's not impossible.
	 * - null if the API request failed
	 *
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @param string $title
	 *
	 * @return array|null
	 */
	public function executeTestEditActionQuery( SourceUrl $sourceUrl, User $user, string $title ) {
		return $this->remoteApiRequestExecutor->execute(
			$sourceUrl,
			$user,
			[
				'action' => 'query',
				'format' => 'json',
				'prop' => 'info',
				'titles' => $title,
				'intestactions' => 'edit',
			],
			true
		);
	}

	/**
	 * Possible return values:
	 * - { "edit": { "result": "Success", … } }
	 * - { "error": { "code": "protectedpage", "info": "This page has been protected …", … } }
	 * - null if the API request failed
	 *
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @param string $title
	 * @param array $params At least one of the parameters "text", "appendtext", "prependtext" and
	 *  "undo" is required.
	 * @param string $editSummary
	 *
	 * @return array|null Null in case of an error.
	 */
	public function executeEditAction(
		SourceUrl $sourceUrl,
		User $user,
		string $title,
		array $params,
		string $editSummary
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
					'title' => $title,
					'summary' => $editSummary,
				],
				$params
			),
			true
		);
	}

	/**
	 * Possible return values:
	 * - { "query": { "userinfo": { "rights": [ "…", … ], … } }, … }
	 * - There should be no reason for this to ever return an error, but it's not impossible.
	 * - null if the API request failed
	 *
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 *
	 * @return array|null Null in case of an error.
	 */
	public function executeUserRightsQuery( SourceUrl $sourceUrl, User $user ) {
		return $this->remoteApiRequestExecutor->execute(
			$sourceUrl,
			$user,
			[
				'action' => 'query',
				'format' => 'json',
				'meta' => 'userinfo',
				'uiprop' => 'rights',
			]
		);
	}

	/**
	 * Possible return values:
	 * - { "delete": { … } }
	 * - { "error": { "code": "permissiondenied", "info": "…", …  } }
	 * - null if the API request failed
	 *
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @param string $title
	 * @param string $deletionReason
	 *
	 * @return array|null Null in case of an error.
	 */
	public function executeDeleteAction(
		SourceUrl $sourceUrl,
		User $user,
		string $title,
		string $deletionReason
	) {
		$token = $this->remoteApiRequestExecutor->getCsrfToken( $sourceUrl, $user );

		if ( $token === null ) {
			return null;
		}

		return $this->remoteApiRequestExecutor->execute(
			$sourceUrl,
			$user,
			[
				'action' => 'delete',
				'format' => 'json',
				'title' => $title,
				'reason' => $deletionReason,
				'token' => $token,
			],
			true
		);
	}

}
