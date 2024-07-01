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

	public function getHtml( RecoverableTitleException $exception ): string {
		$msg = $exception->getMessageObject()->inLanguage( $this->getLanguage() );
		return new MessageWidget( [
			'label' => new HtmlSnippet( $msg->parse() ),
			'type' => 'warning',
		] ) .
		'<br>' .
		( new ChangeFileNameForm( $this ) )->getHtml( $exception->getImportPlan() );
	}

}
