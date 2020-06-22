<?php

namespace FileImporter\Remote\MediaWiki;

use FileImporter\Data\SourceUrl;
use StatusValue;
use User;

/**
 * @license GPL-2.0-or-later
 *
 * @phan-file-suppress PhanTypeArraySuspiciousNullable multiple false positives
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
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @param string $title
	 *
	 * @return StatusValue with a boolean value, true if the user can edit the page
	 */
	public function executeTestEditActionQuery( SourceUrl $sourceUrl, User $user, string $title ) : StatusValue {
		// Expected return values in the legacy format:
		// { "query": { "pages": { "123": { "actions": { "edit": "" }, …
		// { "query": { "pages": { "123": { "actions": [], …
		// But with formatversion=2:
		// { "query": { "pages": [ { "actions": { "edit": true }, …
		// { "query": { "pages": [ { "actions": { "edit": false }, …
		$result = $this->remoteApiRequestExecutor->execute(
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

		$isOk = isset( $result['query']['pages'] );
		$page = $isOk ? reset( $result['query']['pages'] ) : [];
		$actions = $page['actions'] ?: [];

		$status = $this->statusFromApiResponse( $result );
		$status->setResult(
			$isOk,
			array_key_exists( 'edit', $actions ) && $actions['edit'] !== false
		);
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
	) : StatusValue {
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
	 * @return StatusValue with a boolean value, true if the user is allowed to delete pages
	 */
	public function executeUserRightsQuery( SourceUrl $sourceUrl, User $user ) : StatusValue {
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

		$isOk = isset( $result['query']['userinfo']['rights'] );
		$rights = $isOk ? $result['query']['userinfo']['rights'] : [];

		$status = $this->statusFromApiResponse( $result );
		$status->setResult(
			$isOk,
			in_array( 'delete', $rights )
		);
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
	) : StatusValue {
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
				'token' => $token,
			],
			true
		);
		return $this->statusFromApiResponse( $result );
	}

	private function statusFromApiResponse( array $apiResponse = null ) : StatusValue {
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
