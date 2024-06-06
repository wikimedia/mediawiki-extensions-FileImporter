'use strict';

const assert = require( 'assert' ),
	ImportPreviewPage = require( '../pageobjects/importpreview.page' ),
	UserLoginPage = require( 'wdio-mediawiki/LoginPage' ),

	testFileUrl = 'https://commons.wikimedia.org/wiki/File:Wikimedia_Commons_favicon.png';

describe( 'ImportPreview page', () => {
	it( 'shows dismissible help banner', async () => {
		await UserLoginPage.loginAdmin();
		await ImportPreviewPage.resetHelpBannerVisibility();

		await ImportPreviewPage.openImportPreview( testFileUrl );

		assert(
			await ImportPreviewPage.helpBanner.isDisplayed(),
			'the help banner is visible'
		);

		await ImportPreviewPage.helpBannerCloseButton.click();

		assert(
			!( await ImportPreviewPage.helpBanner.isDisplayed() ),
			'the help banner is no longer visible'
		);

		await ImportPreviewPage.waitForUserSettingsUpdated();

		await ImportPreviewPage.openImportPreview( testFileUrl );

		assert(
			!( await ImportPreviewPage.helpBanner.isDisplayed() ),
			'the help banner is no longer visible on future visits'
		);
	} );
} );
