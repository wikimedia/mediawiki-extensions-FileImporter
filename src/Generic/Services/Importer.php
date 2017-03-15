<?php

namespace FileImporter\Generic\Services;

use FileImporter\Generic\Data\ImportDetails;
use FileImporter\Generic\Data\ImportTransformations;
use FileImporter\Generic\Exceptions\ImportException;
use RepoGroup;
use UploadFromStash;
use UploadStash;

class Importer {

	/**
	 * @param ImportDetails $importDetails
	 * @param ImportTransformations $importTransformations transformations to be made to the details
	 *
	 * @return bool success
	 * @throws ImportException
	 */
	public function import(
		ImportDetails $importDetails,
		ImportTransformations $importTransformations
	) {
		global $wgUser;
		// TODO copy files directly in swift if possible?

		// TODO lookup in CentralAuth to see if users can be maintained on the import
		// This probably needs some service object to be made to keep things nice and tidy

		// TODO dont use wgUser
		$user = $wgUser;

		$localRepo = RepoGroup::singleton()->getLocalRepo();
		$stash = new UploadStash( $localRepo, $user );

		// foreach files in $importDetails
		// TODO actually download the files into tmp storage!
		// @see UploadFromUrl::reallyFetchFile
		// @see WikiRevision::importUpload???? << This might be the right way?
		$tmpFilePath = '';

		// TODO stash ALL of the files!?!!!?
		$stash->stashFile( $tmpFilePath, 'extFileImporter' );

		$uploader = new UploadFromStash( $user, $stash, $localRepo );
		// TODO how to actually upload them from the stash?

		// TODO import the text revisions?
		// @see WikiImporter::importRevision ?
		// TODO revisionIds need to be modified...

		// TODO If modifications are needed on the text we need to make 1 new revision!
		// @see RevisionModifier ?

		return false;
	}

}
