'use strict';

const { config } = require( '@vue/test-utils' );

// Mock Vue plugins in test suites
config.global.mocks = {
	$i18n: ( str ) => {
		return {
			text: () => str,
			parse: () => str,
			toString: () => str,
			escaped: () => str
		};
	}
};

config.global.directives = {
	'i18n-html': ( el, binding ) => {
		el.innerHTML = `${binding.arg} (${binding.value})`;
	}
};
