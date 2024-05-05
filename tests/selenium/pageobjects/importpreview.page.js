'use strict';

const Page = require( 'wdio-mediawiki/Page' ),
	Util = require( 'wdio-mediawiki/Util' );

class ImportPreviewPage extends Page {
	async openImportPreview( clientUrl ) {
		await super.openTitle( 'Special:ImportFile', { clientUrl: clientUrl } );
	}

	get helpBanner() { return $( '.mw-importfile-help-banner .oo-ui-messageWidget' ); }
	get helpBannerCloseButton() { return $( '.mw-importfile-help-banner .oo-ui-icon-close' ); }

	async resetHelpBannerVisibility() {
		await Util.waitForModuleState( 'mediawiki.base' );
		return browser.execute( async () => {
			await mw.loader.using( 'mediawiki.api' );
			return new mw.Api().saveOption( 'userjs-fileimporter-hide-help-banner', null );
		} );
	}

	async waitForUserSettingsUpdated() {
		return browser.waitUntil( async () => {
			await this.openImportPreview();
			await Util.waitForModuleState( 'mediawiki.base' );
			return await browser.execute( async () => {
				await mw.loader.using( 'mediawiki.user' );
				return mw.user.options.get( 'userjs-fileimporter-hide-help-banner' ) !== null;
			} );
		} );
	}
}

module.exports = new ImportPreviewPage();
