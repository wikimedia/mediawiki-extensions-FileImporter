<?php

namespace FileImporter\Html;

use FileImporter\HookRunner;
use MediaWiki\Context\MutableContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class WikitextEditor extends SpecialPageHtmlFragment {

	public function getHtml( Title $filePage, string $wikitext ): string {
		$outputPage = $this->getOutput();
		$outputPage->addModules( 'mediawiki.action.edit' );
		$outputPage->addModuleStyles( 'mediawiki.action.edit.styles' );
		$this->runEditFormInitialHook( $filePage );

		return EditPage::getEditToolbar() .
			$this->buildEditor( $wikitext );
	}

	/**
	 * Run EditPage::showEditForm:initial hook mainly for the WikiEditor toolbar
	 * @see \MediaWiki\Extension\WikiEditor\Hooks::onEditPage__showEditForm_initial
	 * Triggering the hook means we don't have special handling for any extensions.
	 */
	private function runEditFormInitialHook( Title $filePage ) {
		// We need to fake the context to make extensions like CodeMirror believe they are editing
		// the actual file page.
		$context = $this->getContext();
		$context->getRequest()->setVal( 'action', 'edit' );
		if ( $context instanceof MutableContext ) {
			$context->setTitle( $filePage );
		}

		$editPage = new EditPage(
			\Article::newFromTitle( $filePage, $context )
		);
		$editPage->setContextTitle( $filePage );

		( new HookRunner( MediaWikiServices::getInstance()->getHookContainer() ) )->onEditPage__showEditForm_initial(
			$editPage, $this->getOutput()
		);
	}

	/**
	 * @see EditPage::showTextbox
	 *
	 * @return string HTML
	 */
	private function buildEditor( string $wikitext ): string {
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		$class = 'mw-editfont-' . $userOptionsLookup->getOption( $this->getUser(), 'editfont' );
		$pageLang = $this->getLanguage();

		$wikitext = $this->addNewLineAtEnd( $wikitext );

		$attributes = [
			'aria-label' => $this->msg( 'edit-textarea-aria-label' )->text(),
			'id' => 'wpTextbox1',
			'class' => $class,
			'cols' => 80,
			'rows' => 25,
			'accesskey' => ',',
			'tabindex' => 1,
			'lang' => $pageLang->getHtmlCode(),
			'dir' => $pageLang->getDir(),
			'autofocus' => 'autofocus',
		];

		return Html::textarea( 'intendedWikitext', $wikitext, $attributes );
	}

	/**
	 * @see EditPage::addNewLineAtEnd
	 */
	private function addNewLineAtEnd( string $wikitext ): string {
		return $wikitext === '' ? '' : $wikitext . "\n";
	}

}
