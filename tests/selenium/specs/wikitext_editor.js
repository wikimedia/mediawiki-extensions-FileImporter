const assert = require( 'assert' ),
	ChangeFileInfoPage = require( '../pageobjects/changefileinfo.page' ),
	ImportPreviewPage = require( '../pageobjects/importpreview.page' ),
	Util = require( 'wdio-mediawiki/Util' ),

	testFileUrl = 'https://commons.wikimedia.org/wiki/File:Phalke.jpg';

describe( 'ChangeFileInfo page', () => {
	// Disable due to broken/flakiness T248956
	it.skip( 'WikiEditor toolbar visible', () => {
		ImportPreviewPage.openImportPreview( testFileUrl );
		ImportPreviewPage.editFileInfoButton.click();

		assert( ChangeFileInfoPage.getEditToolbar(), 'WikiEditor toolbar is present.' );
	} );

	it( 'MediaWiki core modules present', () => {
		ImportPreviewPage.openImportPreview( testFileUrl );
		ImportPreviewPage.editFileInfoButton.click();

		Util.waitForModuleState( 'mediawiki.action.edit' );
		Util.waitForModuleState( 'mediawiki.action.edit.styles' );
	} );
} );
