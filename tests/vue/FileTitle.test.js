'use strict';

const { mount } = require( '@vue/test-utils' );
const FileTitle = require( '../../modules/components/FileTitle.vue' );

const messageOutput = 'Text';
const getNameTextOutput = 'Bar';

global.mw = {
	message: jest.fn().mockReturnValue( {
		text: jest.fn().mockReturnValue( messageOutput )
	} ),
	Title: {
		newFromUserInput: jest.fn().mockReturnValue( {
			getNameText: jest.fn().mockReturnValue( getNameTextOutput )
		} )
	}
};

describe( 'Basic usage', () => {

	it( 'click on title enters editing mode', async () => {
		const wrapper = mount( FileTitle, {
			props: {
				fileExtension: 'png',
				fileTitle: 'Test'
			}
		} );

		await wrapper.find( 'h2' ).trigger( 'click' );

		expect( wrapper.find( 'h2' ).exists() ).toBeFalsy();
		expect( wrapper.find( 'input' ).exists() ).toBeTruthy();
	} );

	it( 'changing title triggers normalization', async () => {
		const wrapper = mount( FileTitle, {
			props: {
				fileExtension: 'png',
				fileTitle: 'Foo'
			}
		} );

		await wrapper.find( 'h2' ).trigger( 'click' );
		wrapper.find( 'input' ).setValue( 'bar' );
		await wrapper.find( 'button' ).trigger( 'click' );

		expect( wrapper.find( '.cdx-message--warning' ).text() ).toBe( messageOutput );
		expect( wrapper.find( 'h2' ).text() ).toBe( getNameTextOutput + '.png' );
	} );
} );
