<?php

namespace FileImporter\Remote\MediaWiki;

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\User;
use RuntimeException;

/**
 * @license GPL-2.0-or-later
 */
class CentralAuthTokenProvider {

	/**
	 * @return string
	 * @throws ApiUsageException e.g. when CentralAuth is not available locally
	 * @throws RuntimeException when there is an unexpected API result
	 */
	public function getToken( User $user ) {
		$context = new RequestContext;
		$context->setRequest( new FauxRequest( [ 'action' => 'centralauthtoken' ] ) );
		$context->setUser( $user );

		$api = new ApiMain( $context );

		$api->execute();
		$token = $api->getResult()->getResultData( [ 'centralauthtoken', 'centralauthtoken' ] );
		if ( !$token ) {
			// This should be unreachable, because execute() takes care of all error handling
			throw new RuntimeException( 'Unexpected return value from the centralauthtoken API' );
		}

		return $token;
	}

}
