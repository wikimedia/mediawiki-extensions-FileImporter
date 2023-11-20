'use strict';

const { mount } = require( '@vue/test-utils' );
const FileTitle = require( '../../modules/components/FileTitle.vue' );

describe( 'Basic usage', () => {

	it( 'click on title enters editing mode', async () => {
		const wrapper = mount( FileTitle, {
			props: {
				fileExtension: '.png',
				fileTitle: 'Test'
			}
		} );

		await wrapper.find( 'h2' ).trigger( 'click' );

		expect( wrapper.find( 'h2' ).exists() ).toBeFalsy();
		expect( wrapper.find( 'input' ).exists() ).toBeTruthy();
	} );
} );
