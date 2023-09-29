<template>
	<div>
		{{ $i18n( 'fileimporter-previewnote' ).text() }}
	</div>

	<div class="mw-importfile-header">
		<cdx-text-input
			v-if="isEditingTitle"
			ref="titleInput"
			v-model="fileTitle"
			@update:model-value="unsavedChangesFlag = true;"
			@vue:mounted="mountedTitleEdit"
		></cdx-text-input>
		<h2
			v-else
			@click="isEditingTitle = true"
		>
			{{ fileTitle + '.' + fileExtension }}
		</h2>

		<cdx-toggle-button
			v-model="isEditingTitle"
		>
			<span v-if="isEditingTitle">{{ $i18n( 'fileimporter-previewtitle' ).text() }}</span>
			<span v-else>{{ $i18n( 'fileimporter-edittitle' ).text() }}</span>
		</cdx-toggle-button>
	</div>

	<img :src="imageUrl" :alt="filePrefixed">

	<div class="mw-importfile-header">
		<h2>{{ $i18n( 'fileimporter-heading-fileinfo' ).plain() }}</h2>
		<cdx-toggle-button
			v-model="isEditingInfo"
		>
			{{ isEditingInfo ?
				$i18n( 'fileimporter-previewinfo' ).text() : $i18n( 'fileimporter-editinfo' ).text() }}
		</cdx-toggle-button>
	</div>

	<div
		v-if="fileInfoDiffHtml"
		v-html="fileInfoDiffHtml"></div>

	<div>
		<!-- TODO find a working autosize option -->
		<cdx-text-area
			v-if="isEditingInfo"
			ref="fileInfoInput"
			v-model="fileInfoWikitext"
			rows="10"
			@update:model-value="unsavedChangesFlag = true;"
			@vue:mounted="mountedFileInfoInput"
		></cdx-text-area>
		<div
			v-else
			class="mw-importfile-parsedContent"
			:class="{ 'mw-importfile-loading': fileInfoLoading }"
			@vue:mounted="mountedFileInfoRendered"
			v-html="fileInfoHtml"
		>
		</div>
	</div>

	<categories-section
		v-if="!isEditingInfo"
		:class="{ 'mw-importfile-loading': fileInfoLoading }"
		:visible-categories="visibleCategories"
		:hidden-categories="hiddenCategories"
	></categories-section>

	<h2>{{ $i18n( 'fileimporter-heading-filehistory' ).plain() }}</h2>
	<div>
		{{ $i18n(
			'fileimporter-filerevisions',
			fileRevisionsCount,
			fileRevisionsCount
		).parse() }}
	</div>

	<div v-if="canAutomateDelete || canAutomateEdit">
		<h2>{{ $i18n( 'fileimporter-heading-cleanup' ).plain() }}</h2>
		<div v-if="canAutomateDelete">
			<p>{{ $i18n( 'fileimporter-delete-text' ).parse() }}</p>
			<cdx-checkbox v-model="automateSourceWikiDelete">
				{{ $i18n( 'fileimporter-delete-checkboxlabel' ).parse() }}
			</cdx-checkbox>
		</div>
		<div v-else-if="canAutomateEdit">
			<p>{{ $i18n( 'fileimporter-cleanup-text', cleanupTitle ).parse() }}</p>
			<cdx-checkbox v-model="automateSourceWikiCleanup">
				{{ $i18n( 'fileimporter-cleanup-checkboxlabel' ).parse() }}
			</cdx-checkbox>
		</div>
	</div>

	<div class="mw-importfile-importOptions">
		<p>{{ $i18n( 'fileimporter-editsummary' ).plain() }}</p>
		<cdx-text-input
			v-model="editSummary"
			class="mw-importfile-import-summary"
			@update:model-value="unsavedChangesFlag = true;"
		></cdx-text-input>

		<p>
			{{ $i18n(
				'fileimporter-textrevisions',
				textRevisionsCount,
				textRevisionsCount
			).parse() }}
		</p>

		<cdx-button
			action="progressive"
			class="mw-importfile-import-submit"
			weight="primary"
			@click="submitForm( 'submit' );"
		>
			{{ $i18n( 'fileimporter-import' ).plain() }}
		</cdx-button>

		<!-- TODO: show conditionally when there are unsaved changes -->
		<cdx-button
			class="mw-importfile-import-diff"
			@click="viewDiff"
		>
			{{ $i18n( 'fileimporter-viewdiff' ).plain() }}
		</cdx-button>

		<cdx-button
			class="mw-importfile-import-cancel"
			weight="primary"
			@click="cancelChanges"
		>
			<!-- TODO: button navigates to $importPlan->getRequest()->getUrl() -->
			{{ $i18n( 'fileimporter-cancel' ).plain() }}
		</cdx-button>

		<span>{{ $i18n( 'fileimporter-import-wait' ).plain() }}</span>
	</div>
</template>

<script>
const { ref } = require( 'vue' );
const {
	CdxButton, CdxCheckbox, CdxTextArea, CdxTextInput, CdxToggleButton
} = require( '@wikimedia/codex' );
const CategoriesSection = require( './CategoriesSection.vue' );

const NS_FILE = mw.config.get( 'wgNamespaceIds' ).file;

const parseCategories = ( rawCategories ) => {
	return rawCategories.reduce( ( [ hiddenCategories, visibleCategories ], record ) => {
		const formatted = {
			missing: 'missing' in record,
			name: record.category
		};
		if ( 'hidden' in record ) {
			hiddenCategories.push( formatted );
		} else {
			visibleCategories.push( formatted );
		}
		return [ hiddenCategories, visibleCategories ];
	}, [ [], [] ] );
};

const postRedirect = ( params ) => {
	const $form = $( '<form>' );
	$form.attr( 'method', 'post' );
	Object.keys( params ).forEach( ( key ) => {
		const $input = $( '<input>' );
		$input.attr( 'type', 'hidden' );
		$input.attr( 'name', key );
		$input.attr( 'value', params[ key ] );
		$form.append( $input );
	} );
	$( 'body' ).append( $form );
	$form.submit();
};

// @vue/component
module.exports = {
	name: 'ImportFile',
	components: {
		CategoriesSection,
		CdxButton,
		CdxCheckbox,
		CdxTextArea,
		CdxTextInput,
		CdxToggleButton
	},
	props: {
		// See SpecialImportFile::getAutomatedCapabilities
		automatedCapabilities: { type: Object, required: true },
		clientUrl: { type: String, required: true },
		detailsHash: { type: String, required: true },
		editToken: { type: String, required: true },
		fileExtension: { type: String, required: true },
		fileInfoDiffHtml: { type: String, required: true },
		filePrefixed: { type: String, required: true },
		fileRevisionsCount: { type: Number, required: true },
		imageUrl: { type: String, required: true },
		initialEditSummary: { type: String, required: true },
		initialFileInfoWikitext: { type: String, required: true },
		initialFileTitle: { type: String, required: true },
		textRevisionsCount: { type: Number, required: true }
	},
	setup( props ) {
		return {
			automateSourceWikiCleanup: ref( props.automatedCapabilities.automateSourceWikiCleanup ),
			automateSourceWikiDelete: ref( props.automatedCapabilities.automateSourceWikiDelete ),
			canAutomateDelete: props.automatedCapabilities.canAutomateDelete,
			canAutomateEdit: props.automatedCapabilities.canAutomateEdit,
			isEditingInfo: ref( false ),
			isEditingTitle: ref( false ),
			fileInfoHtml: ref( null ),
			fileInfoLoading: ref( false ),
			hiddenCategories: ref( [] ),
			editSummary: ref( props.initialEditSummary ),
			fileInfoWikitext: ref( props.initialFileInfoWikitext ),
			fileTitle: ref( props.initialFileTitle ),
			visibleCategories: ref( [] )
		};
	},
	data() {
		return {
			unsavedChangesFlag: false
		};
	},
	methods: {
		cancelChanges() {
			window.location.assign( window.location );
		},
		mountedFileInfoInput() {
			// TODO: CdxTextArea should support the "focus" method.  This is
			// fragile because it assumes the component's DOM structure.
			$( this.$refs.fileInfoInput.$el ).find( 'textarea' ).focus();
		},
		mountedFileInfoRendered() {
			// TODO: don't refresh when unchanged.  Could require an advanced
			// "customRef" style of watching to combine debounce and other needs.
			this.fileInfoLoading = true;
			this.parseFileInfo();
		},
		mountedTitleEdit() {
			this.$refs.titleInput.focus();
		},
		parseFileInfo() {
			new mw.Api().post( {
				action: 'parse',
				contentmodel: 'wikitext',
				disableeditsection: true,
				formatversion: 2,
				prop: [ 'text', 'categories' ],
				text: this.fileInfoWikitext,
				title: mw.Title.makeTitle( NS_FILE, this.fileTitle ).getPrefixedText()
			} ).then( ( response ) => {
				this.fileInfoHtml = response.parse.text;
				[ this.hiddenCategories, this.visibleCategories ] =
					parseCategories( response.parse.categories );
				this.fileInfoLoading = false;
			} );
		},
		submitForm( action ) {
			this.unsavedChangesFlag = false;
			postRedirect( {
				action,
				automateSourceWikiCleanup: this.automateSourceWikiCleanup,
				automateSourceWikiDelete: this.automateSourceWikiDelete,
				clientUrl: this.clientUrl,
				importDetailsHash: this.detailsHash,
				intendedFileName: this.fileTitle,
				intendedRevisionSummary: this.editSummary,
				intendedWikitext: this.fileInfoWikitext,
				token: this.editToken

				// TODO:
				// actionStats: {},
				// validationWarnings: {}
			} );
		},
		viewDiff() {
			// TODO: replace this POST with an API request.
			this.submitForm( 'viewdiff' );
		}
	},
	watch: {
		unsavedChangesFlag( newValue ) {
			if ( newValue ) {
				window.onbeforeunload = function () {
					return '';
				};
			} else {
				window.onbeforeunload = null;
			}
		}
	}
};
</script>

<style lang="less">
// To access Codex design tokens and mixins inside Vue files, import MediaWiki skin variables.
@import 'mediawiki.skin.variables.less';

.mw-importfile-loading {
	opacity: 0.5;
}

.mw-importfile-header {
	border-bottom: @border-width-base @border-style-base @border-color-base;
	margin: 1em 0 0.25em 0;
	overflow: auto;
	padding-bottom: 0.2em;
	display: flex;
	flex-flow: row wrap;
	justify-content: space-between;

	h2 {
		border: 0;
		margin: 0;
	}

	.cdx-text-input {
		flex-grow: 1;
	}
}

// Mostly copied from the .editOptions styles, see mediawiki.action.edit.styles.less
.mw-importfile-parsedContent,
.mw-importfile-importOptions {
	background-color: #eaecf0;
	border: @border-width-base @border-style-base @border-color-subtle;
	border-radius: @border-radius-base;
	margin-top: 1em;
	padding: 1em 1em 1.5em 1em;
}

.mw-importfile-parsedContent {
	// Same as .catlinks
	background-color: #f8f9fa;

	.mw-parser-output {
		> :first-child {
			margin-top: 0;
		}

		> :last-child {
			margin-bottom: 0;
		}
	}
}

// TODO: applies to InputFormPage which is not ported yet.
.mw-fileimporter-url-submit {
	margin-top: 14px;
}

.mw-importfile-import-summary {
	margin-bottom: 14px;
}
</style>
