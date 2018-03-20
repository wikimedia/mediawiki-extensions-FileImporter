<?php

namespace FileImporter\Html;

use EditPage;
use Html;
use SpecialPage;

/**
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class WikiTextEditor {

	/**
	 * @var SpecialPage
	 */
	private $specialPage;

	public function __construct( SpecialPage $specialPage ) {
		$this->specialPage = $specialPage;
	}

	/**
	 * @param string $wikitext
	 *
	 * @return string
	 */
	public function getHtml( $wikitext ) {
		$this->loadModules();
		$this->runEditFormInitialHook();

		return EditPage::getEditToolbar() .
			$this->buildEditor( $wikitext );
	}

	/**
	 * Load modules mainly related to the toolbar functions
	 */
	private function loadModules() {
		$this->specialPage->getOutput()->addModules( 'mediawiki.action.edit' );
		$this->specialPage->getOutput()->addModuleStyles( 'mediawiki.action.edit.styles' );
	}

	/**
	 * Run EditPage::showEditForm:initial hook mainly for the WikiEditor toolbar
	 * @see WikiEditorHooks::editPageShowEditFormInitial
	 * Triggering the hook means we don't have special handling for any extensions.
	 */
	private function runEditFormInitialHook() {
		$editPage = new EditPage(
			\Article::newFromTitle(
				$this->specialPage->getPageTitle(),
				$this->specialPage->getContext()
			)
		);

		\Hooks::run( 'EditPage::showEditForm:initial',
			[ &$editPage, $this->specialPage->getOutput() ]
		);
	}

	/**
	 * @see EditPage::showTextbox
	 *
	 * @param string $wikitext
	 *
	 * @return string HTML
	 */
	private function buildEditor( $wikitext ) {
		$class = 'mw-editfont-' . $this->specialPage->getUser()->getOption( 'editfont' );
		$pageLang = $this->specialPage->getLanguage();

		/**
		 * The below could be turned on with refactoring @ https://gerrit.wikimedia.org/r/#/c/373867/
		 * But a patch also exists to remove this code https://gerrit.wikimedia.org/r/#/c/138840/
		 */
		// if ( !$this->specialPage->getRequest()->isUnicodeCompliantBrowser() ) {
		// $wikitext = StringUtils::makeSafeForUtf8Editing( $wikitext );
		// }
		$wikitext = $this->addNewLineAtEnd( $wikitext );

		$attributes = [
			'id' => 'wpTextbox1',
			'class' => $class,
			'cols' => 80,
			'rows' => 25,
			'accesskey' => ',',
			'tabindex' => 1,
			'lang' => $pageLang->getHtmlCode(),
			'dir' => $pageLang->getDir(),
		];

		return Html::textarea( 'intendedWikiText', $wikitext, $attributes );
	}

	/**
	 * @see EditPage::addNewLineAtEnd
	 *
	 * @param string $wikitext
	 *
	 * @return string
	 */
	private function addNewLineAtEnd( $wikitext ) {
		if ( strval( $wikitext ) !== '' ) {
			// Ensure there's a newline at the end, otherwise adding lines
			// is awkward.
			// But don't add a newline if the text is empty, or Firefox in XHTML
			// mode will show an extra newline. A bit annoying.
			$wikitext .= "\n";
			return $wikitext;
		}
		return $wikitext;
	}

}
