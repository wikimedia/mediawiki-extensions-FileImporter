<?php

namespace FileImporter\Remote\MediaWiki;

use ApiMain;
use ApiUsageException;
use FauxRequest;
use RequestContext;
use RuntimeException;
use User;

/**
 * @license GPL-2.0-or-later
 */
class CentralAuthTokenProvider {

	/**
	 * @param User $user
	 *
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
