<?php

namespace FileImporter\Html;

use FileImporter\Exceptions\RecoverableTitleException;
use Html;

/**
 * Html showing an error and the ChangeTitleForm
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class RecoverableTitleExceptionPage extends SpecialPageHtmlFragment {

	/**
	 * @param RecoverableTitleException $exception
	 *
	 * @return string
	 */
	public function getHtml( RecoverableTitleException $exception ) {
		return Html::rawElement(
			'div',
			[ 'class' => 'warningbox' ],
			Html::rawElement( 'p', [], $exception->getMessageObject()->parse() )
		) .
		( new ChangeFileNameForm( $this ) )->getHtml( $exception->getImportPlan() );
	}

}
