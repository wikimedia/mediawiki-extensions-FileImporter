<?php

namespace FileImporter\MediaWiki;

use FileImporter\Generic\ImportAdjustments;
use FileImporter\Generic\ImportDetails;
use FileImporter\Generic\Importer;
use FileImporter\Generic\TargetUrl;
use MediaWiki\MediaWikiServices;
use NaiveForeignTitleFactory;

class MediaWikiImporter implements Importer {

	/**
	 * @param TargetUrl $targetUrl
	 *
	 * @return bool
	 */
	public function canImport( TargetUrl $targetUrl ) {
		// TODO inject
		/** @var HostBasedSiteTableLookup $lookup */
		$lookup = MediaWikiServices::getInstance()->getService( 'FileImporterUrlBasedSiteLookup' );
		return $lookup->getSite( $targetUrl->getParsedUrl()['host'] ) !== null;
	}

	/**
	 * @param TargetUrl $targetUrl
	 *
	 * @return ImportDetails
	 */
	public function getImportDetails( TargetUrl $targetUrl ) {

		$parsed = $targetUrl->getParsedUrl();
		$bits = explode( '/', $parsed['path'] );
		$fullTitle = array_pop( $bits );

		// TODO inject?
		// TODO use NamespaceAwareForeignTitleFactory using api /db of the foreign / local site
		$titleFactory = new NaiveForeignTitleFactory();
		$foreignTitle = $titleFactory->createForeignTitle( $fullTitle, NS_FILE );

		return new ImportDetails(
			$targetUrl,
			$foreignTitle->getText(),
			// TODO actually get real file url
			'https://upload.wikimedia.org/wikipedia/commons/5/52/Berlin_Montage_4.jpg'
		);
	}

	/**
	 * @param TargetUrl $targetUrl
	 * @param ImportAdjustments $importAdjustments
	 *
	 * @return bool success
	 */
	public function import( TargetUrl $targetUrl, ImportAdjustments $importAdjustments ) {
		// TODO implement
		return false;
	}

}
