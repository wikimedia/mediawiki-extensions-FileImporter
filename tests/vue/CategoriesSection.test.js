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
	template: '<div><ul><li v-for="category in categories">{{ category }}</li></ul></div>',
	props: [ 'categories' ]
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

		// Notice message is rendered with correct message key
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

	it( 'renders visible and hidden categories in distinct sections with correct content when present', async () => {
		const wrapper = mount( CategoriesSection, {
			props: {
				visibleCategories: [ 'Animals', 'Nature' ],
				hiddenCategories: [ 'Hiddencategory' ]
			}
		} );

		const catlinksWrapper = wrapper.find( '.catlinks' );
		expect( catlinksWrapper.isVisible() ).toBeTruthy();

		// Normal and hidden categories are located inside the catlinksWrapper
		const normalCategoriesWrapper = catlinksWrapper.find( '.mw-normal-catlinks' );
		expect( normalCategoriesWrapper.isVisible() ).toBeTruthy();

		const hiddenCategoriesWrapper = catlinksWrapper.find( '.mw-hidden-catlinks' );
		expect( hiddenCategoriesWrapper.isVisible() ).toBeTruthy();

		// Correct number of list items and text values for visible categories
		const visibleCategoriesListItems = normalCategoriesWrapper.findAll( 'li' );

		expect( visibleCategoriesListItems ).toHaveLength( 2 );
		expect( visibleCategoriesListItems[ 0 ].text() ).toBe( 'Animals' );
		expect( visibleCategoriesListItems[ 1 ].text() ).toBe( 'Nature' );

		// Correct number of list items and text values for hidden categories
		const hiddenCategoriesListItems = hiddenCategoriesWrapper.findAll( 'li' );

		expect( hiddenCategoriesListItems ).toHaveLength( 1 );
		expect( hiddenCategoriesListItems[ 0 ].text() ).toBe( 'Hiddencategory' );
	} );
} );
