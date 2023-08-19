<?php

namespace FileImporter\Services;

use Content;
use DerivativeContext;
use FileImporter\HookRunner;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use RequestContext;
use Status;
use StatusValue;
use User;

/**
 * Class that can be used to validate the content of a text revision
 *
 * @license GPL-2.0-or-later
 * @author Christoph Jauera <christoph.jauera@wikimedia.de>
 */
class FileTextRevisionValidator {

	/** @var DerivativeContext */
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
	 * @return StatusValue isOK when validation succeeds
	 */
	public function validate(
		Title $title,
		User $user,
		Content $content,
		string $summary,
		$minor
	): StatusValue {
		if ( !$title->inNamespace( NS_FILE ) ) {
			return StatusValue::newFatal( 'fileimporter-badnamespace' );
		}

		$status = Status::newGood();
		$this->context->setUser( $user );
		$this->context->setTitle( $title );

		( new HookRunner( MediaWikiServices::getInstance()->getHookContainer() ) )->onEditFilterMergedContent(
			$this->context,
			$content,
			$status,
			$summary,
			$user,
			$minor
		);

		return $status;
	}

}
