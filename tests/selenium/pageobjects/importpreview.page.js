'use strict';

const Page = require( 'wdio-mediawiki/Page' ),
	UserLoginPage = require( 'wdio-mediawiki/LoginPage' ),
	Util = require( 'wdio-mediawiki/Util' );

class ImportPreviewPage extends Page {
	openImportPreview( clientUrl, login = true ) {
		if ( login ) {
			UserLoginPage.loginAdmin();
		}
		super.openTitle( 'Special:ImportFile', { clientUrl: clientUrl } );
	}

	get editFileInfoButton() { return $( 'button[value=editinfo]' ); }
	get helpBanner() { return $( '.mw-importfile-help-banner .oo-ui-messageWidget' ); }
	get helpBannerCloseButton() { return $( '.mw-importfile-help-banner .oo-ui-icon-close' ); }

	waitForJS() {
		Util.waitForModuleState( 'ext.FileImporter.SpecialJs' );
	}
}

module.exports = new ImportPreviewPage();
