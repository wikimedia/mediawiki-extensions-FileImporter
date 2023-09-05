<template>
	<div
		v-if="contentHtml"
		class="mw-importfile-help-banner-new"
	>
		<cdx-message
			type="notice"
			:dismiss-button-label="$i18n( 'fileimporter-help-banner-close-tooltip' ).text()"
			@user-dismissed="dismissHelp"
		>
			<div
				class="mw-importfile-help-banner-text"
				v-html="contentHtml"
			></div>
			<div class="mw-importfile-image-help-banner-new"></div>
		</cdx-message>
	</div>
</template>

<script>
const { CdxMessage } = require( '@wikimedia/codex' );

/**
 * Help banner with sticky dismissal.  The HTML message is complex to build so
 * must be rendered raw on the server.
 */
// @vue/component
module.exports = exports = {
	name: 'HelpBanner',
	components: {
		CdxMessage
	},
	props: {
		contentHtml: {
			type: String,
			required: true
		}
	},
	methods: {
		dismissHelp() {
			new mw.Api().saveOption( 'userjs-fileimporter-hide-help-banner', '1' );
		}
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.mw-importfile-help-banner-new {
	.cdx-message {
		margin-bottom: @spacing-250;
	}

	.cdx-message__content {
		display: flex;
		flex-flow: row;
	}

	.mw-importfile-help-banner-text {
		// FIXME: Need to wrap more on small screens.
		flex-grow: 1;

		:first-child {
			margin-top: 0;
		}

		:last-child {
			margin-bottom: 0;
		}

		ol {
			/* Default indention is 3.2em */
			margin-left: 1.2em;
		}
	}

	.mw-importfile-image-help-banner-new {
		background-image: url( ../../resources/FileImporter-help-banner-ltr.svg );
		background-position: center;
		background-repeat: no-repeat;
		background-size: @size-full;
		margin: 0 @spacing-200;
		min-height: 180px;
		min-width: 250px;
	}
}
</style>
