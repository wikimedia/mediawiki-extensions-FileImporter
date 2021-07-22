<?php

namespace FileImporter\Services;

use Content;
use DerivativeContext;
use FauxRequest;
use Hooks;
use RequestContext;
use Status;
use Title;
use User;
use WikiFilePage;

/**
 * Class that can be used to validate the content of a text revision
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class FileTextRevisionValidator {

	/**
	 * @var DerivativeContext
	 */
	private $context;

	public function __construct() {
		$this->context = new DerivativeContext( RequestContext::getMain() );
		$this->context->setRequest( new FauxRequest() );
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @param Content $content
	 * @param string $summary
	 * @param bool $minor
	 *
	 * @return Status isOK when validation succeeds
	 */
	public function validate(
		Title $title,
		User $user,
		Content $content,
		$summary,
		$minor
	): Status {
		if ( !$title->inNamespace( NS_FILE ) ) {
			return Status::newFatal( 'fileimporter-badnamespace' );
		}

		$status = Status::newGood();
		$this->context->setUser( $user );
		$this->context->setTitle( $title );
		$this->context->setWikiPage( new WikiFilePage( $title ) );

		Hooks::run( 'EditFilterMergedContent', [
			$this->context,
			$content,
			$status,
			$summary,
			$user,
			$minor
		] );

		return $status;
	}

}
