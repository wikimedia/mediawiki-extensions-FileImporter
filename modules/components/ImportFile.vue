<template>
	<cdx-message v-if="importSuccess" type="success">
		<span v-html="importOutput"></span>
	</cdx-message>
	<div v-for="warning in warningMessages" :key="warning.type + warning.message">
		<cdx-message :type="warning.type">
			<span v-html="warning.message"></span>
		</cdx-message>
	</div>

	<div>
		{{ $i18n( 'fileimporter-previewnote' ).text() }}
	</div>

	<div class="mw-importfile-header">
		<cdx-field
			:status="status"
			:messages="messages"
		>
			<cdx-text-input
				v-if="isEditingTitle"
				ref="titleInput"
				v-model="currentFileTitle"
				@update:model-value="unsavedChangesFlag = true"
				@vue:mounted="mountedTitleEdit"
				@input="status = 'default'"
				@change="normalizeTitle"
			></cdx-text-input>
			<h2
				v-else
				@click="isEditingTitle = true"
			>
				{{ currentFileTitle + '.' + fileExtension }}
			</h2>
		</cdx-field>

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
			@click="diffOutput = null"
		>
			{{ isEditingInfo ?
				$i18n( 'fileimporter-previewinfo' ).text() :
				$i18n( 'fileimporter-editinfo' ).text() }}
		</cdx-toggle-button>
	</div>

	<table v-if="!diffErrorMessage" class="diff">
		<colgroup>
			<col class="diff-marker">
			<col class="diff-content">
			<col class="diff-marker">
			<col class="diff-content">
		</colgroup>
		<tbody v-html="diffOutput">
		</tbody>
	</table>
	<div
		v-if="diffErrorMessage"
		class="cdx-message cdx-message--block cdx-message--error"
		role="alert">
		<span class="cdx-message__icon"></span>
		<div class="cdx-message__content">
			{{ diffErrorMessage }}
		</div>
	</div>

	<div>
		<!-- TODO find a working autosize option -->
		<cdx-text-area
			v-if="isEditingInfo"
			ref="fileInfoInput"
			v-model="currentFileInfoWikitext"
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
			v-model="currentEditSummary"
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

	<div v-if="progressBar" class="mw-importfile-import-overlay">
		<cdx-progress-bar aria--label="{{ $i18n( 'fileimporter-import-wait' ).plain() }}">
		</cdx-progress-bar>
	</div>
</template>

<script>
const { ref } = require( 'vue' );
const {
	CdxButton, CdxCheckbox, CdxField, CdxMessage, CdxProgressBar, CdxTextArea,
	CdxTextInput, CdxToggleButton
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

const scrollToTop = () => {
	$( 'html, body' ).animate( { scrollTop: $( '#content' ).offset().top }, 500 );
};

// @vue/component
module.exports = {
	name: 'ImportFile',
	components: {
		CategoriesSection,
		CdxButton,
		CdxCheckbox,
		CdxField,
		CdxMessage,
		CdxProgressBar,
		CdxTextArea,
		CdxTextInput,
		CdxToggleButton
	},
	props: {
		// See SpecialImportFile::getAutomatedCapabilities
		automatedCapabilities: { type: Object, required: true },
		clientUrl: { type: String, required: true },
		detailsHash: { type: String, required: true },
		editSummary: { type: String, required: true },
		editToken: { type: String, required: true },
		fileExtension: { type: String, required: true },
		fileInfoWikitext: { type: String, required: true },
		filePrefixed: { type: String, required: true },
		fileRevisionsCount: { type: Number, required: true },
		fileTitle: { type: String, required: true },
		imageUrl: { type: String, required: true },
		initialFileInfoWikitext: { type: String, required: true },
		textRevisionsCount: { type: Number, required: true }
	},
	setup( props ) {
		return {
			automateSourceWikiCleanup: ref( props.automatedCapabilities.automateSourceWikiCleanup ),
			automateSourceWikiDelete: ref( props.automatedCapabilities.automateSourceWikiDelete ),
			canAutomateDelete: props.automatedCapabilities.canAutomateDelete,
			canAutomateEdit: props.automatedCapabilities.canAutomateEdit,
			currentEditSummary: ref( props.editSummary ),
			currentFileInfoWikitext: ref( props.fileInfoWikitext ),
			currentFileTitle: ref( props.fileTitle )
		};
	},
	data() {
		return {
			diffErrorMessage: '',
			diffOutput: null,
			fileInfoHtml: null,
			fileInfoLoading: false,
			hiddenCategories: [],
			importError: null,
			importSuccess: null,
			importOutput: null,
			warningMessages: [],
			validationWarnings: [],
			isEditingInfo: false,
			isEditingTitle: false,
			messages: { warning: '' },
			progressBar: false,
			status: 'default',
			unsavedChangesFlag: false,
			visibleCategories: []
		};
	},
	methods: {
		cancelChanges() {
			window.location.assign( window.location );
		},
		mountedFileInfoInput() {
			const $textarea = $( this.$refs.fileInfoInput.$el ).find( 'textarea' );
			mw.loader.using( [ 'ext.wikiEditor' ], function () {
				mw.addWikiEditor( $textarea );
			} );
			// TODO: CdxTextArea should support the "focus" method.  This is
			// fragile because it assumes the component's DOM structure.
			$textarea.focus();
		},
		mountedFileInfoRendered() {
			// TODO: don't refresh when unchanged. Could require an advanced
			// "customRef" style of watching to combine debounce and other needs.
			this.fileInfoLoading = true;
			this.parseFileInfo();
		},
		mountedTitleEdit() {
			this.$refs.titleInput.focus();
		},
		normalizeTitle() {
			const inputTitle = mw.Title.newFromUserInput( this.currentFileTitle );
			if ( inputTitle ) {
				const cleanTitle = inputTitle.getNameText();
				if ( inputTitle.title !== cleanTitle ) {
					this.status = 'warning';
					this.messages.warning = mw.message(
						'fileimporter-filenameerror-automaticchanges', inputTitle.title, cleanTitle
					).text();
					this.currentFileTitle = cleanTitle;
				}
			}
		},
		parseFileInfo() {
			new mw.Api().post( {
				action: 'parse',
				contentmodel: 'wikitext',
				disableeditsection: true,
				formatversion: 2,
				prop: [ 'text', 'categories' ],
				text: this.currentFileInfoWikitext,
				title: mw.Title.makeTitle( NS_FILE, this.currentFileTitle ).getPrefixedText()
			} ).then( ( response ) => {
				this.fileInfoHtml = response.parse.text;
				[ this.hiddenCategories, this.visibleCategories ] =
					parseCategories( response.parse.categories );
				this.fileInfoLoading = false;
			} );
		},
		submitForm( action ) {
			this.unsavedChangesFlag = false;
			this.importSuccess = false;
			this.importError = false;

			const params = {
				action,
				automateSourceWikiCleanup: this.automateSourceWikiCleanup,
				automateSourceWikiDelete: this.automateSourceWikiDelete,
				clientUrl: this.clientUrl,
				importDetailsHash: this.detailsHash,
				intendedFileName: this.currentFileTitle,
				intendedRevisionSummary: this.currentEditSummary,
				intendedWikitext: this.currentFileInfoWikitext,
				token: this.editToken,
				validationWarnings: JSON.stringify( this.validationWarnings )
			};

			this.progressBar = true;
			$.ajax( {
				type: 'POST',
				data: params,
				dataType: 'json',
				success: ( data ) => {
					this.progressBar = false;
					this.importSuccess = !!data.success;
					this.importError = !!data.error;
					this.importOutput = data.output;
					this.warningMessages = data.warningMessages;
					this.validationWarnings = data.validationWarnings;
					scrollToTop();
				},
				error: () => {
					this.progressBar = false;
					this.importError = true;
					this.warningMessages = [ {
						type: 'error',
						// TODO: Proper i18n feedback message for submission errors
						message: 'Something went wrong with the request.'
					} ];
					scrollToTop();
				}
			} );
		},
		viewDiff() {
			const params = {
				action: 'compare',
				format: 'json',
				formatversion: 2,
				fromtext: this.initialFileInfoWikitext,
				totext: this.currentFileInfoWikitext,
				prop: 'diff'
			};
			new mw.Api().get( params ).done( ( data ) => {
				this.diffOutput = data.compare.body;
			} ).fail( () => {
				this.diffErrorMessage = this.$i18n( 'fileimporter-cdx-diff-failed' ).text();
			} );
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

.mw-importfile-import-overlay {
	position: absolute;
	display: block;
	left: 0;
	right: 0;
	bottom: 0;
	width: 100%;
	height: 100%;
	background-color: @background-color-backdrop-light;

	.cdx-progress-bar {
		top: 50%;
		margin: 50px;
	}
}

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

.diff {
	font-family: monospace;
}
</style>
