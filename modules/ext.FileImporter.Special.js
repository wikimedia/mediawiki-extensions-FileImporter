'use strict';

const $summary = $( '.mw-importfile-import-summary' );
if ( $summary.length ) {
	// Submit the import form when hitting return in the edit summary
	OO.ui.TextInputWidget.static.infuse( $summary ).on( 'enter', ( e ) => {
		e.preventDefault();
		OO.ui.ButtonWidget.static.infuse( $( '.mw-importfile-import-submit' ) ).$button.click();
	} );
}

$( '.mw-importfile-help-banner input[ type="checkbox" ]' ).change( function () {
	// When the help banner is dismissed, set a user option indicating that it should not be shown
	// again in the future
	if ( this.checked && mw.user.isNamed() ) {
		( new mw.Api() ).saveOption( 'userjs-fileimporter-hide-help-banner', '1' );
	}
} );

mw.hook( 'centralauth-p-personal-reset' ).add( () => {
	window.location.reload();
} );
