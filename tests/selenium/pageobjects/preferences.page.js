'use strict';

const Page = require( 'wdio-mediawiki/Page' ),
	Util = require( 'wdio-mediawiki/Util' );

class PreferencesPage extends Page {
	resetHelpBannerVisibility() {
		Util.waitForModuleState( 'mediawiki.base' );
		return browser.execute( function () {
			return mw.loader.using( 'mediawiki.api' ).then( function () {
				return new mw.Api().saveOption( 'userjs-fileimporter-hide-help-banner', null );
			} );
		} );
	}
}

module.exports = new PreferencesPage();
