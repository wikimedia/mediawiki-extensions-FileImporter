<template>
	<div class="ext-fileimporter-vue-app">
		<help-banner
			:content-html="helpBannerContentHtml"
		></help-banner>

		<import-file
			:automated-capabilities="automatedCapabilities"
			:client-url="clientUrl"
			:details-hash="detailsHash"
			:edit-summary="editSummary"
			:edit-token="editToken"
			:file-extension="fileExtension"
			:file-info-wikitext="fileInfoWikitext"
			:file-prefixed="filePrefixed"
			:file-revisions-count="fileRevisionsCount"
			:file-title="fileTitle"
			:image-url="imageUrl"
			:initial-file-info-wikitext="initialFileInfoWikitext"
			:text-revisions-count="textRevisionsCount"
		></import-file>
	</div>
</template>

<script>
const HelpBanner = require( './HelpBanner.vue' );
const ImportFile = require( './ImportFile.vue' );

// @vue/component
module.exports = {
	name: 'App',
	components: {
		HelpBanner,
		ImportFile
	},
	setup() {
		const replacementCount = mw.config.get( 'wgFileImporterTemplateReplacementCount' );
		const defaultEditSummary = mw.config.get( 'wgFileImporterEditSummary' ) ||
			( replacementCount > 0 ?
				mw.message(
					'fileimporter-auto-replacements-summary',
					replacementCount
				).text() :
				'' );

		return {
			// See SpecialImportFile::getAutomatedCapabilities
			automatedCapabilities: mw.config.get( 'wgFileImporterAutomatedCapabilities' ),
			clientUrl: mw.config.get( 'wgFileImporterClientUrl' ),
			detailsHash: mw.config.get( 'wgFileImporterDetailsHash' ),
			editSummary: defaultEditSummary,
			editToken: mw.config.get( 'wgFileImporterEditToken' ),
			fileExtension: mw.config.get( 'wgFileImporterFileExtension' ),
			fileInfoWikitext: mw.config.get( 'wgFileImporterFileInfoWikitext' ),
			filePrefixed: mw.config.get( 'wgFileImporterPrefixedTitle' ),
			fileTitle: mw.config.get( 'wgFileImporterTitle' ),
			fileRevisionsCount: mw.config.get( 'wgFileImporterFileRevisionsCount' ),
			helpBannerContentHtml: mw.config.get( 'wgFileImporterHelpBannerContentHtml' ),
			imageUrl: mw.config.get( 'wgFileImporterImageUrl' ),
			initialFileInfoWikitext: mw.config.get( 'wgFileImporterInitialFileInfoWikitext' ),
			textRevisionsCount: mw.config.get( 'wgFileImporterTextRevisionsCount' )
		};
	}
};
</script>
