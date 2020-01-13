const assert = require( 'assert' ),
	ChangeFileInfoPage = require( '../pageobjects/changefileinfo.page' ),
	ImportFilePage = require( '../pageobjects/importfile.page' ),
	Util = require( 'wdio-mediawiki/Util' ),

	testFileUrl = 'https://commons.wikimedia.org/wiki/File:Phalke.jpg';

describe( 'ChangeFileInfo page', () => {
	it( 'WikiEditor toolbar visible', () => {
		ImportFilePage.openImportFile( testFileUrl );
		ImportFilePage.getEditFileInfoButton().click();

		assert( ChangeFileInfoPage.getEditToolbar(), 'WikiEditor toolbar is present.' );
	} );

	it( 'MediaWiki core modules present', () => {
		ImportFilePage.openImportFile( testFileUrl );
		ImportFilePage.getEditFileInfoButton().click();

		Util.waitForModuleState( 'mediawiki.action.edit' );
		Util.waitForModuleState( 'mediawiki.action.edit.styles' );
	} );
} );
