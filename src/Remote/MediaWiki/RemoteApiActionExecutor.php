<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use StatusValue;
use User;

/**
 * @license GPL-2.0-or-later
 */
class RemoteApiActionExecutor {

	public const CHANGE_TAG = 'fileimporter-remote';

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
	 * @param string $title
	 *
	 * @return StatusValue ok if the user can edit the page
	 */
	public function executeTestEditActionQuery( SourceUrl $sourceUrl, User $user, string $title ): StatusValue {
		// Expected return values:
		// { "query": { "pages": [ { "actions": { "edit": true }, …
		// { "query": { "pages": [ { "actions": { "edit": false }, …
		$result = $this->remoteApiRequestExecutor->execute(
			$sourceUrl,
			$user,
			[
				'action' => 'query',
				'format' => 'json',
				'formatversion' => 2,
				'prop' => 'info',
				'titles' => $title,
				'intestactions' => 'edit',
			],
			true
		);

		$status = $this->statusFromApiResponse( $result );
		$canEdit = $result['query']['pages'][0]['actions']['edit'] ?? false;
		$status->setOK( $canEdit );

		return $status;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @param string $title
	 * @param array $params At least one of the parameters "text", "appendtext", "prependtext" and
	 *  "undo" is required.
	 * @param string $editSummary
	 *
	 * @return StatusValue
	 */
	public function executeEditAction(
		SourceUrl $sourceUrl,
		User $user,
		string $title,
		array $params,
		string $editSummary
	): StatusValue {
		$token = $this->remoteApiRequestExecutor->getCsrfToken( $sourceUrl, $user );
		if ( $token === null ) {
			return $this->statusFromApiResponse();
		}

		$result = $this->remoteApiRequestExecutor->execute(
			$sourceUrl,
			$user,
			array_merge(
				[
					'action' => 'edit',
					'token' => $token,
					'format' => 'json',
					'title' => $title,
					'summary' => $editSummary,
					'tags' => self::CHANGE_TAG,
				],
				$params
			),
			true
		);
		return $this->statusFromApiResponse( $result );
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 *
	 * @return StatusValue ok if the user is allowed to delete pages
	 */
	public function executeUserRightsQuery( SourceUrl $sourceUrl, User $user ): StatusValue {
		// Expected return values:
		// { "query": { "userinfo": { "rights": [ "delete", …
		// Same with formatversion=2
		$result = $this->remoteApiRequestExecutor->execute(
			$sourceUrl,
			$user,
			[
				'action' => 'query',
				'format' => 'json',
				'meta' => 'userinfo',
				'uiprop' => 'rights',
			]
		);

		$status = $this->statusFromApiResponse( $result );
		$rights = $result['query']['userinfo']['rights'] ?? [];
		$canDelete = in_array( 'delete', $rights );
		$status->setOK( $canDelete );

		return $status;
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @param string $title
	 * @param string $deletionReason
	 *
	 * @return StatusValue
	 */
	public function executeDeleteAction(
		SourceUrl $sourceUrl,
		User $user,
		string $title,
		string $deletionReason
	): StatusValue {
		$token = $this->remoteApiRequestExecutor->getCsrfToken( $sourceUrl, $user );
		if ( $token === null ) {
			return $this->statusFromApiResponse();
		}

		$result = $this->remoteApiRequestExecutor->execute(
			$sourceUrl,
			$user,
			[
				'action' => 'delete',
				'format' => 'json',
				'title' => $title,
				'reason' => $deletionReason,
				'tags' => self::CHANGE_TAG,
				'token' => $token,
			],
			true
		);
		return $this->statusFromApiResponse( $result );
	}

	/**
	 * @param array|null $apiResponse
	 * @return StatusValue
	 */
	private function statusFromApiResponse( array $apiResponse = null ): StatusValue {
		$status = StatusValue::newGood();

		if ( !$apiResponse ) {
			$status->setOK( false );
			return $status;
		}

		// It's an array of "errors" with errorformat=plaintext, but a single "error" without.
		$errors = $apiResponse['errors'] ?? [];
		if ( isset( $apiResponse['error'] ) ) {
			$errors[] = $apiResponse['error'];
		}
		foreach ( $errors as $error ) {
			// Errors contain "code" and "info" with formatversion=2, but "code" and "*" without.
			$status->error( $error['code'] );
		}

		return $status;
	}

}
