<template>
	<cdx-message
		v-if="visibleCategories.length === 0 && hiddenCategories.length === 0"
		type="notice"
		inline
	>
		{{ $i18n( 'fileimporter-category-encouragement' ).parse() }}
	</cdx-message>
	<div v-else class="catlinks">
		<categories-list
			:categories="visibleCategories"
			class="mw-normal-catlinks"
		>
			<a :href="categoriesSpecialPageTitle.getUrl()">
				{{ $i18n( 'pagecategories', visibleCategories.length ).text() }}
			</a>
		</categories-list>

		<categories-list
			:categories="hiddenCategories"
			class="mw-hidden-catlinks"
		>
			{{ $i18n( 'hidden-categories', hiddenCategories.length ).text() }}
		</categories-list>
	</div>
</template>

<script>
const CategoriesList = require( './CategoriesList.vue' );
const { CdxMessage } = require( '@wikimedia/codex' );
const NS_SPECIAL = mw.config.get( 'wgNamespaceIds' ).special;

// @vue/component
module.exports = {
	name: 'CategoriesSection',
	components: {
		CategoriesList,
		CdxMessage
	},
	props: {
		visibleCategories: { type: Array, required: true },
		hiddenCategories: { type: Array, required: true }
	},
	setup() {
		return {
			categoriesSpecialPageTitle: mw.Title.makeTitle( NS_SPECIAL, 'Categories' )
		};
	}
};
</script>

<style lang="less">
.cdx-message--inline {
	margin-top: 1em;
	// Prevent bold
	font-weight: revert;
}
</style>
