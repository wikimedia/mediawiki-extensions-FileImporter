'use strict';

$( function () {
	var $summary = $( '.mw-importfile-import-summary' );
	if ( !$summary.length ) {
		return;
	}

	OO.ui.TextInputWidget.static.infuse( $summary ).on( 'enter', function ( e ) {
		e.preventDefault();
		OO.ui.ButtonWidget.static.infuse( $( '.mw-importfile-import-submit' ) ).$button.click();
	} );
} );
