<?php

namespace FileImporter\Remote\MediaWiki;

use ApiMain;
use Exception;
use FauxRequest;
use RequestContext;
use User;

class CentralAuthTokenProvider {

	/**
	 * Returns CentralAuth token, or throws an exception on failure
	 *
	 * @param User $user
	 * @return string
	 * @throws Exception
	 */
	public function getToken( User $user ) {
		$context = new RequestContext;
		$context->setRequest( new FauxRequest( [ 'action' => 'centralauthtoken' ] ) );
		$context->setUser( $user );

		$api = new ApiMain( $context );

		$api->execute();
		$token = $api->getResult()->getResultData( [ 'centralauthtoken', 'centralauthtoken' ] );
		if ( $token === null ) {
			throw new Exception( 'Failed to get CentralAuth token' );
		}

		return $token;
	}

}
