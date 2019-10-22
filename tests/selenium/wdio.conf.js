const fs = require( 'fs' ),
	path = require( 'path' ),
	saveScreenshot = require( 'wdio-mediawiki' ).saveScreenshot,
	logPath = process.env.LOG_DIR || `${__dirname }/log`;

// relative path
function relPath( foo ) {
	return path.resolve( __dirname, '../..', foo );
}

// get current test title and clean it, to use it as file name
function fileName( title ) {
	return encodeURIComponent( title.replace( /\s+/g, '-' ) );
}

// build file path
function filePath( test, screenshotPath, extension ) {
	return path.join( screenshotPath, `${fileName( test.parent )}-${fileName( test.title )}.${extension}`
);
}

exports.config = {
	// ======
	// Custom WDIO config specific to MediaWiki
	// ======
	// Use in a test as `browser.options.<key>`.
	// Defaults are for convenience with MediaWiki-Vagrant

	// Wiki admin
	username: process.env.MEDIAWIKI_USER || 'Admin',
	password: process.env.MEDIAWIKI_PASSWORD || 'vagrant',

	runner: 'local',

	// ==================
	// Test Files
	// ==================
	specs: [
		relPath( './tests/selenium/specs/**/*.js' )
	],

	// ============
	// Capabilities
	// ============

	// How many instances of the same capability (browser) may be started at the same time.
	maxInstances: 1,

	capabilities: [ {
		// For Chrome/Chromium https://sites.google.com/a/chromium.org/chromedriver/capabilities
		browserName: 'chrome',
		maxInstances: 1,
		'chromeOptions': {
			// If DISPLAY is set, assume developer asked non-headless or CI with Xvfb.
			// Otherwise, use --headless (added in Chrome 59)
			// https://chromium.googlesource.com/chromium/src/+/59.0.3030.0/headless/README.md
			args: [
				...( process.env.DISPLAY ? [] : [ '--headless' ] ),
				// Chrome sandbox does not work in Docker
				...( fs.existsSync( '/.dockerenv' ) ? [ '--no-sandbox' ] : [] )
			]
		}
	} ],

	// ===================
	// Test Configurations
	// ===================

	// Enabling synchronous mode (via the wdio-sync package), means specs don't have to
	// use Promise#then() or await for browser commands, such as like `brower.element()`.
	// Instead, it will automatically pause JavaScript execution until th command finishes.
	//
	// For non-browser commands (such as MWBot and other promises), this means you
	// have to use `browser.call()` to make sure WDIO waits for it before the next
	// browser command.
	sync: true,

	// Level of logging verbosity: silent | verbose | command | data | result | error
	logLevel: 'error',

	// Enables colors for log output.
	coloredLogs: true,

	// Warns when a deprecated command is used
	deprecationWarnings: true,

	// Stop the tests once a certain number of failed tests have been recorded.
	// Default is 0 - don't bail, run all tests.
	bail: 0,

	// Setting this enables automatic screenshots for when a browser command fails
	// It is also used by afterTest for capturig failed assertions.
	screenshotPath: logPath,

	// Default timeout for each waitFor* command.
	waitforTimeout: 10 * 1000,

	baseUrl: ( process.env.MW_SERVER || 'http://127.0.0.1:8080' ) + (
		process.env.MW_SCRIPT_PATH || '/w'
	),
	services: [
		...( process.env.SAUCE_ACCESS_KEY ? [ 'sauce' ] : [] )
	],

	// Framework you want to run your specs with.
	// See also: http://webdriver.io/guide/testrunner/frameworks.html
	framework: 'mocha',

	// Test reporter for stdout.
	// See also: http://webdriver.io/guide/testrunner/reporters.html
	reporters: [ 'dot', 'junit' ],
	reporterOptions: {
		outputDir: logPath
	},

	// Options to be passed to Mocha.
	// See the full list at http://mochajs.org/
	mochaOpts: {
		ui: 'bdd',
		timeout: 60 * 1000
	},

	// =====
	// Hooks
	// =====
	// See also: http://webdriver.io/guide/testrunner/configurationfile.html

	/**
	 * Save a screenshot when test fails.
	 *
	 * @param {Object} test Mocha Test object
	 * @returns {void}
	 *
	 */
	afterTest: function ( test ) {
		if ( !test.passed ) {
			const screenshotFile = filePath( test, logPath, 'png' );
			browser.saveScreenshot( screenshotFile );
			console.log( '\n\tScreenshot:', screenshotFile, '\n' );
		}
	}
};
