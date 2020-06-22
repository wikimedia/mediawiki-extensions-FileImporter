<?php

namespace FileImporter\Html;

use FileImporter\Exceptions\RecoverableTitleException;
use OOUI\HtmlSnippet;
use OOUI\MessageWidget;

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
		return new MessageWidget( [
			'label' => new HtmlSnippet( $exception->getMessageObject()->parse() ),
			'type' => 'warning',
		] ) .
		'<br>' .
		( new ChangeFileNameForm( $this ) )->getHtml( $exception->getImportPlan() );
	}

}
