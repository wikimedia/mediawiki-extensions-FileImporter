<template>
	<div class="mw-importfile-header">
		<cdx-field
			:status="status"
			:messages="messages"
		>
			<cdx-text-input
				v-if="isEditingTitle"
				ref="titleInput"
				v-model="currentFileTitle"
				@update:model-value="$emit( 'update:modelValue', currentFileTitle )"
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

		<div>
			<cdx-toggle-button
				v-model="isEditingTitle"
			>
				<span v-if="isEditingTitle">{{ $i18n( 'fileimporter-previewtitle' ).text() }}</span>
				<span v-else>{{ $i18n( 'fileimporter-edittitle' ).text() }}</span>
			</cdx-toggle-button>
		</div>
	</div>
</template>

<script>
const { ref } = require( 'vue' );
const {
	CdxField, CdxTextInput, CdxToggleButton
} = require( '@wikimedia/codex' );

// @vue/component
module.exports = exports = {
	name: 'FileTitle',
	components: {
		CdxField,
		CdxTextInput,
		CdxToggleButton
	},
	props: {
		fileExtension: { type: String, required: true },
		fileTitle: { type: String, required: true }
	},
	emits: [
		/**
		 * When the input value changes
		 *
		 * @property {string} modelValue The new model value
		 */
		'update:modelValue'
	],
	setup( props ) {
		return {
			currentFileTitle: ref( props.fileTitle )
		};
	},
	data() {
		return {
			isEditingTitle: false,
			messages: { warning: '' },
			status: 'default'
		};
	},
	methods: {
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
						'fileimporter-filenameerror-automaticchanges', this.currentFileTitle, cleanTitle
					).text();
					this.currentFileTitle = cleanTitle;
				}
			}
		}
	}
};
</script>
