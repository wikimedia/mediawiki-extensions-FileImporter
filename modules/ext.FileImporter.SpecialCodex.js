'use strict';

const launchVueApp = function () {
	const Vue = require( 'vue' );
	const App = require( './components/App.vue' );

	Vue.configureCompat( {
		MODE: 3
	} );

	Vue.createMwApp( App )
		.mount( '#ext-fileimporter-vue-root' );
};
launchVueApp();
