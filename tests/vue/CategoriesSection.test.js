'use strict';

const { mount } = require( '@vue/test-utils' );

// Mock the mw object
global.mw = {
	config: {
		get: jest.fn().mockReturnValue( {
			special: 1
		} )
	},
	Title: {
		makeTitle: jest.fn().mockReturnValue( {} )
	}
};

// Mock  CategoriesList
jest.mock( '../../modules/components/CategoriesList.vue', () => ( {
	name: 'CategoriesList',
	template: '<div></div>'
} ) );

const CategoriesSection = require( '../../modules/components/CategoriesSection.vue' );

describe( 'CategoriesSection', () => {
	it( 'displays notice message when there are no categories', () => {
		const wrapper = mount( CategoriesSection, {
			props: {
				visibleCategories: [],
				hiddenCategories: []
			}
		} );

		expect( wrapper.find( '.cdx-message--notice' ).isVisible() ).toBeTruthy();

		// Assert that the notice message is rendered with correct message key
		const messageContent = wrapper.find( '.cdx-message--notice' ).text();
		expect( messageContent ).toBe( 'fileimporter-category-encouragement' );
	} );

	it( 'does not render category catlinks when there are no categories', () => {
		const wrapper = mount( CategoriesSection, {
			props: {
				visibleCategories: [],
				hiddenCategories: []
			}
		} );

		expect( wrapper.find( '.catlinks' ).exists() ).toBeFalsy();
	} );

	it( 'displays category catlinks when present', async () => {
		const wrapper = mount( CategoriesSection, {
			props: {
				visibleCategories: [ 'Animals', 'Nature' ],
				hiddenCategories: [ 'Hiddencategory' ]
			}
		} );

		expect( wrapper.find( '.catlinks' ).isVisible() ).toBeTruthy();
	} );
} );
