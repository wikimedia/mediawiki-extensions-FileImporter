'use strict';

const ImportPreviewPage = require( '../pageobjects/importpreview.page' ),
	Util = require( 'wdio-mediawiki/Util' ),
	testFileUrl = 'https://commons.wikimedia.org/wiki/File:Phalke.jpg';

describe( 'ChangeFileInfo page', () => {
	// Disable due to more flakiness T256137
	it.skip( 'MediaWiki core modules present', () => {
		ImportPreviewPage.openImportPreview( testFileUrl );
		ImportPreviewPage.editFileInfoButton.click();

		Util.waitForModuleState( 'mediawiki.action.edit' );
		Util.waitForModuleState( 'mediawiki.action.edit.styles' );
	} );
} );
