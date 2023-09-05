<template>
	<div
		v-if="categories.length > 0"
	>
		<slot></slot>{{ $i18n( 'colon-separator' ).escaped() }}
		<ul>
			<li
				v-for="category in formattedCategories"
				:key="category.text"
			>
				<a
					:class="{ new: category.missing }"
					:href="category.url"
				>{{ category.text }}</a>
			</li>
		</ul>
	</div>
</template>

<script>
const { computed } = require( 'vue' );

const NS_CATEGORY = mw.config.get( 'wgNamespaceIds' ).category;

const formatCategories = ( categories ) => {
	return categories.map( ( category ) => {
		const categoryTitle = mw.Title.makeTitle( NS_CATEGORY, category.name );
		return {
			missing: category.missing,
			url: categoryTitle.getUrl(),
			text: categoryTitle.getMainText()
		};
	} );
};

// @vue/component
module.exports = {
	name: 'CategoriesList',
	props: {
		categories: { type: Array, required: true }
	},
	setup( props ) {
		return {
			formattedCategories: computed( () => formatCategories( props.categories ) )
		};
	}
};
</script>
