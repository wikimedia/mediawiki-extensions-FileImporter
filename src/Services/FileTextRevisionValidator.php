<?php

namespace FileImporter\Services;

use Content;
use DerivativeContext;
use FauxRequest;
use FileImporter\Exceptions\ImportException;
use FileImporter\Exceptions\LocalizedImportException;
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

	private const ERROR_WRONG_REVISION_NAMESPACE = 'wrongTextRevisionNamespace';

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
	 * @throws ImportException
	 */
	public function validate(
		Title $title,
		User $user,
		Content $content,
		$summary,
		$minor
	) {
		if ( $title->getNamespace() !== NS_FILE ) {
			throw new ImportException( 'Wrong text revision namespace given.',
				self::ERROR_WRONG_REVISION_NAMESPACE );
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

		if ( !$status->isGood() ) {
			throw new LocalizedImportException( $status->getMessage() );
		}
	}

}
