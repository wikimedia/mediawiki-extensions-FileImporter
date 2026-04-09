<?php

namespace FileImporter\Remote\MediaWiki;

use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Session\SessionManager;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

/**
 * @license GPL-2.0-or-later
 */
class CentralAuthTokenProvider {

	/**
	 * @return string CentralAuth token.
	 */
	public function getToken( User $user ) {
		// @phan-suppress-next-line PhanUndeclaredClassMethod This method and service exists in CentralAuth.
		return CentralAuthServices::getApiTokenManager()->getToken(
			$user,
			SessionManager::getGlobalSession()->getId(),
			WikiMap::getCurrentWikiId()
		);
	}

}
