<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use MediaWiki\User\User;
use StatusValue;

/**
 * @license GPL-2.0-or-later
 */
class RemoteApiActionExecutor {

	public const CHANGE_TAG = 'fileimporter-remote';

	public function __construct(
		private readonly RemoteApiRequestExecutor $remoteApiRequestExecutor,
	) {
	}

	/**
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
				'errorformat' => 'plaintext',
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
					'formatversion' => 2,
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
	 * @return StatusValue ok if the user is allowed to delete pages
	 */
	public function executeUserRightsQuery( SourceUrl $sourceUrl, User $user ): StatusValue {
		// Expected return values:
		// { "query": { "userinfo": { "rights": [ "delete", …
		$result = $this->remoteApiRequestExecutor->execute(
			$sourceUrl,
			$user,
			[
				'action' => 'query',
				'errorformat' => 'plaintext',
				'format' => 'json',
				'formatversion' => 2,
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
				'errorformat' => 'plaintext',
				'format' => 'json',
				'formatversion' => 2,
				'title' => $title,
				'reason' => $deletionReason,
				'tags' => self::CHANGE_TAG,
				'token' => $token,
			],
			true
		);
		return $this->statusFromApiResponse( $result );
	}

	private function statusFromApiResponse( ?array $apiResponse = null ): StatusValue {
		$status = StatusValue::newGood();

		if ( !$apiResponse ) {
			$status->setOK( false );
			return $status;
		}

		// TODO: Simplify once all requests are updated.
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
