const assert = require( 'assert' ),
	ImportPreviewPage = require( '../pageobjects/importpreview.page' ),
	PreferencesPage = require( '../pageobjects/preferences.page' ),
	UserLoginPage = require( 'wdio-mediawiki/LoginPage' ),

	testFileUrl = 'https://commons.wikimedia.org/wiki/File:Phalke.jpg';

describe( 'ImportPreview page', () => {
	it( 'shows dismissible help banner', () => {
		UserLoginPage.loginAdmin();
		PreferencesPage.resetHelpBannerVisibility();

		ImportPreviewPage.openImportPreview( testFileUrl, false );
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
		browser.pause( 500 );

		ImportPreviewPage.openImportPreview( testFileUrl, false );
		ImportPreviewPage.waitForJS();

		assert(
			!ImportPreviewPage.helpBanner.isDisplayed(),
			'the help banner is no longer visible on future visits'
		);
	} );
} );
