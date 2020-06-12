'use strict';

var $summary = $( '.mw-importfile-import-summary' );
if ( $summary.length ) {
	OO.ui.TextInputWidget.static.infuse( $summary ).on( 'enter', function ( e ) {
		e.preventDefault();
		OO.ui.ButtonWidget.static.infuse( $( '.mw-importfile-import-submit' ) ).$button.click();
	} );
}
