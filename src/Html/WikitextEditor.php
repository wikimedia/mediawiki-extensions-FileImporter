<?php

namespace FileImporter\Html;

use EditPage;
use Html;
use MediaWiki\MediaWikiServices;
use MutableContext;
use Title;

/**
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class WikitextEditor extends SpecialPageHtmlFragment {

	/**
	 * @param Title $filePage
	 * @param string $wikitext
	 *
	 * @return string
	 */
	public function getHtml( Title $filePage, string $wikitext ): string {
		$this->loadModules();
		$this->runEditFormInitialHook( $filePage );

		return EditPage::getEditToolbar() .
			$this->buildEditor( $wikitext );
	}

	/**
	 * Load modules mainly related to the toolbar functions
	 */
	private function loadModules() {
		$this->getOutput()->addModules( 'mediawiki.action.edit' );
		$this->getOutput()->addModuleStyles( 'mediawiki.action.edit.styles' );
	}

	/**
	 * Run EditPage::showEditForm:initial hook mainly for the WikiEditor toolbar
	 * @see \MediaWiki\Extension\WikiEditor\Hooks::onEditPage__showEditForm_initial
	 * Triggering the hook means we don't have special handling for any extensions.
	 *
	 * @param Title $filePage
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

		\Hooks::run( 'EditPage::showEditForm:initial',
			[ &$editPage, $this->getOutput() ]
		);
	}

	/**
	 * @see EditPage::showTextbox
	 *
	 * @param string $wikitext
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
	 *
	 * @param string $wikitext
	 *
	 * @return string
	 */
	private function addNewLineAtEnd( string $wikitext ): string {
		return $wikitext === '' ? '' : $wikitext . "\n";
	}

}
