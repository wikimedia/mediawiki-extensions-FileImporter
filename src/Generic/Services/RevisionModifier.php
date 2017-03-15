<?php

namespace FileImporter\Generic\Services;

use FileImporter\Generic\Data\ImportTransformations;
use Revision;

/**
 * Make modification to the text of a File page, for example, switching and or removing templates
 * and categories.
 */
class RevisionModifier {

	/**
	 * @param Revision $baseRevision
	 * @param ImportTransformations $transformations
	 *
	 * @return Revision a new revision. $baseRevision with the $adjustments applied
	 */
	public function modify( Revision $baseRevision, ImportTransformations $transformations ) {
		// TODO implement!
	}

}
