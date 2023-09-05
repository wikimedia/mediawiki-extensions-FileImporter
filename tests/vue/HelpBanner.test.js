'use strict';

const { mount } = require( '@vue/test-utils' );
const HelpBanner = require( '../../modules/components/HelpBanner.vue' );

const saveOption = jest.fn();
global.mw = {
	Api: jest.fn().mockReturnValue( { saveOption } )
};

describe( 'Basic usage', () => {
	// TODO: Middleware is amazingly enough doing the RTL image selection
	// correctly.  How to test this here, though?
	// expect( wrapper.html() ).toContain( 'FileImporter-help-banner-ltr.svg' );

	it( 'close button sets user option', async () => {
		const wrapper = mount( HelpBanner, {
			props: {
				contentHtml: 'Foo'
			}
		} );

		await wrapper.find( 'button' ).trigger( 'click' );

		expect( saveOption ).toHaveBeenCalledWith( 'userjs-fileimporter-hide-help-banner', '1' );
	} );
} );
