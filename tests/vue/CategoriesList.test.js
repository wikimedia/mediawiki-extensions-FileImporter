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
		makeTitle( namespace, name ) {
			return {
				getUrl() {
					return `/wiki/Category${ name }`;
				},
				getMainText() {
					return name;
				}
			};
		}
	}
};

const CategoriesList = require( '../../modules/components/CategoriesList.vue' );

describe( 'CategoriesList', () => {

	const categories = [
		{ name: 'Fruits', missing: false },
		{ name: 'Animals', missing: false },
		{ name: 'Hidden', missing: true }
	];

	it( 'renders categories with correct structure and href attributes', () => {

		const wrapper = mount( CategoriesList, {
			props: {
				categories
			}
		} );

		const listItems = wrapper.findAll( 'li' );

		expect( listItems ).toHaveLength( categories.length );
		expect( wrapper.findAll( 'a' ) ).toHaveLength( categories.length );

		categories.forEach( ( category, index ) => {
			const expectedHref = `/wiki/Category${ category.name }`;
			expect( listItems[ index ].find( `a[href="${ expectedHref }"]` ).exists() ).toBeTruthy();
		} );
	} );
} );
