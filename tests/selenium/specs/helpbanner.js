'use strict';

const assert = require( 'assert' ),
	ImportPreviewPage = require( '../pageobjects/importpreview.page' ),
	UserLoginPage = require( 'wdio-mediawiki/LoginPage' ),
	Util = require( 'wdio-mediawiki/Util' ),

	testFileUrl = 'https://commons.wikimedia.org/wiki/File:Phalke.jpg';

describe( 'ImportPreview page', () => {
	it( 'shows dismissible help banner', () => {
		UserLoginPage.loginAdmin();
		ImportPreviewPage.resetHelpBannerVisibility();

		ImportPreviewPage.openImportPreview( testFileUrl );
		ImportPreviewPage.waitForJS();

		assert(
			ImportPreviewPage.helpBanner.isDisplayed(),
			'the help banner is visible'
		);

		ImportPreviewPage.helpBannerCloseButton.click();

		assert(
			!ImportPreviewPage.helpBanner.isDisplayed(),
			'the help banner is no longer visible'
		);

		// ensure that the user options had time to update
		browser.waitUntil( () => {
			UserLoginPage.open();
			Util.waitForModuleState( 'mediawiki.user' );
			return browser.execute( () => {
				return mw.user.options.get( 'userjs-fileimporter-hide-help-banner' ) !== null;
			} );
		} );

		ImportPreviewPage.openImportPreview( testFileUrl );
		ImportPreviewPage.waitForJS();

		assert(
			!ImportPreviewPage.helpBanner.isDisplayed(),
			'the help banner is no longer visible on future visits'
		);
	} );
} );
