<template>
	<div class="ext-fileimporter-vue-app">
		<help-banner
			:content-html="helpBannerContentHtml"
		></help-banner>

		<import-file
			:automated-capabilities="automatedCapabilities"
			:client-url="clientUrl"
			:details-hash="detailsHash"
			:edit-token="editToken"
			:file-extension="fileExtension"
			:file-info-diff-html="fileInfoDiffHtml"
			:file-prefixed="filePrefixed"
			:file-revisions-count="fileRevisionsCount"
			:image-url="imageUrl"
			:initial-edit-summary="editSummary"
			:initial-file-info-wikitext="fileInfoWikitext"
			:initial-file-title="fileTitle"
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
			fileInfoDiffHtml: mw.config.get( 'wgFileImporterFileInfoDiffHtml' ),
			fileInfoWikitext: mw.config.get( 'wgFileImporterFileInfoWikitext' ),
			filePrefixed: mw.config.get( 'wgFileImporterPrefixedTitle' ),
			fileTitle: mw.config.get( 'wgFileImporterTitle' ),
			fileRevisionsCount: mw.config.get( 'wgFileImporterFileRevisionsCount' ),
			helpBannerContentHtml: mw.config.get( 'wgFileImporterHelpBannerContentHtml' ),
			imageUrl: mw.config.get( 'wgFileImporterImageUrl' ),
			textRevisionsCount: mw.config.get( 'wgFileImporterTextRevisionsCount' )
		};
	}
};
</script>
