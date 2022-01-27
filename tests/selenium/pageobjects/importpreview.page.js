'use strict';

const Page = require( 'wdio-mediawiki/Page' ),
	Util = require( 'wdio-mediawiki/Util' );

class ImportPreviewPage extends Page {
	openImportPreview( clientUrl ) {
		super.openTitle( 'Special:ImportFile', { clientUrl: clientUrl } );
	}

	get helpBanner() { return $( '.mw-importfile-help-banner .oo-ui-messageWidget' ); }
	get helpBannerCloseButton() { return $( '.mw-importfile-help-banner .oo-ui-icon-close' ); }

	waitForJS() {
		Util.waitForModuleState( 'ext.FileImporter.SpecialJs' );
	}

	resetHelpBannerVisibility() {
		Util.waitForModuleState( 'mediawiki.base' );
		return browser.execute( function () {
			return mw.loader.using( 'mediawiki.api' ).then( function () {
				return new mw.Api().saveOption( 'userjs-fileimporter-hide-help-banner', null );
			} );
		} );
	}
}

module.exports = new ImportPreviewPage();
