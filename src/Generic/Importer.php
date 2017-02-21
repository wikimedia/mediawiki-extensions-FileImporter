<?php

namespace FileImporter\Generic;

use FileImporter\Generic\Exceptions\ImportException;

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
		// TODO implement
		// TODO copy files directly in swift if possible?

		return false;
	}

}
