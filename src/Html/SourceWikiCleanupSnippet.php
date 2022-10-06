<?php

namespace FileImporter\Html;

use FileImporter\Data\ImportPlan;
use FileImporter\Data\ImportRequest;
use FileImporter\Data\SourceUrl;
use FileImporter\Remote\MediaWiki\RemoteApiActionExecutor;
use FileImporter\Services\WikidataTemplateLookup;
use Html;
use IContextSource;
use MediaWiki\MediaWikiServices;
use OOUI\CheckboxInputWidget;
use OOUI\FieldLayout;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RequestContext;
use User;

/**
 * @license GPL-2.0-or-later
 */
class SourceWikiCleanupSnippet {

	public const ACTION_OFFERED_SOURCE_DELETE = 'offeredSourceDelete';
	public const ACTION_OFFERED_SOURCE_EDIT = 'offeredSourceEdit';

	/** @var bool */
	private $sourceEditingEnabled;
	/** @var bool */
	private $sourceDeletionEnabled;
	/** @var WikidataTemplateLookup */
	private $lookup;
	/** @var RemoteApiActionExecutor */
	private $remoteActionApi;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param bool $sourceEditingEnabled
	 * @param bool $sourceDeletionEnabled
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(
		$sourceEditingEnabled = true,
		$sourceDeletionEnabled = true,
		LoggerInterface $logger = null
	) {
		$this->sourceEditingEnabled = $sourceEditingEnabled;
		$this->sourceDeletionEnabled = $sourceDeletionEnabled;
		$this->logger = $logger ?: new NullLogger();

		// TODO: Inject
		$this->lookup = MediaWikiServices::getInstance()->getService(
			'FileImporterTemplateLookup' );
		$this->remoteActionApi = MediaWikiServices::getInstance()->getService(
			'FileImporterMediaWikiRemoteApiActionExecutor' );
	}

	/**
	 * @param ImportPlan $importPlan
	 * @param User $user
	 * @return string
	 */
	public function getHtml( ImportPlan $importPlan, User $user ) {
		/** @var IContextSource $context */
		$context = RequestContext::getMain();
		$sourceUrl = $importPlan->getRequest()->getUrl();

		$canAutomateEdit = $this->isSourceEditAllowed(
			$sourceUrl,
			$user,
			$importPlan->getOriginalTitle()->getPrefixedText()
		);
		$canAutomateDelete = $this->isSourceDeleteAllowed( $sourceUrl, $user );

		if ( !$canAutomateEdit && !$canAutomateDelete ) {
			return '';
		}

		$html = Html::element(
			'h2',
			[],
			$context->msg( 'fileimporter-heading-cleanup' )->plain()
		);

		if ( $canAutomateDelete ) {
			$automateDeleteSelected = $importPlan->getAutomateSourceWikiDelete();
			$importPlan->setActionIsPerformed( self::ACTION_OFFERED_SOURCE_DELETE );

			$html .= Html::rawElement(
					'p',
					[],
					$context->msg( 'fileimporter-delete-text' )->parse()
				) .
				new FieldLayout(
					new CheckboxInputWidget(
						[
							'name' => 'automateSourceWikiDelete',
							'selected' => $automateDeleteSelected,
							'value' => true
						]
					),
					[
						'label' => $context->msg( 'fileimporter-delete-checkboxlabel' )->parse(),
						'align' => 'inline'
					]
				);
		} elseif ( $canAutomateEdit ) {
			$automateEditSelected = $importPlan->getAutomateSourceWikiCleanUp() ||
				$this->isFreshImport( $importPlan->getRequest() );
			$importPlan->setActionIsPerformed( self::ACTION_OFFERED_SOURCE_EDIT );

			$html .= Html::rawElement(
					'p',
					[],
					$context->msg(
						'fileimporter-cleanup-text',
						$this->lookup->fetchNowCommonsLocalTitle( $sourceUrl )
					)->parse()
				) .
				new FieldLayout(
					new CheckboxInputWidget(
						[
							'name' => 'automateSourceWikiCleanup',
							'selected' => $automateEditSelected,
							'value' => true
						]
					),
					[
						'label' => $context->msg( 'fileimporter-cleanup-checkboxlabel' )->parse(),
						'align' => 'inline'
					]
				);
		}

		return $html;
	}

	/**
	 * @param ImportRequest $importRequest
	 * @return bool
	 */
	private function isFreshImport( ImportRequest $importRequest ) {
		return $importRequest->getImportDetailsHash() === '';
	}

	/**
	 * Warning, contrary to the method name this currently doesn't check if the user is allowed to
	 * edit the page!
	 *
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @param string $title
	 *
	 * @return bool True if source wiki editing is enabled and a localized {{Now Commons}} template
	 *  can be found.
	 */
	private function isSourceEditAllowed( SourceUrl $sourceUrl, User $user, string $title ) {
		if ( !$this->sourceEditingEnabled ||
			// Note: This intentionally doesn't allow a template with the name "0".
			!$this->lookup->fetchNowCommonsLocalTitle( $sourceUrl )
		) {
			return false;
		}

		return $this->remoteActionApi->executeTestEditActionQuery( $sourceUrl, $user, $title )
			->isGood();
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 *
	 * @return bool True if source wiki deletions are enabled and the user does have the right to
	 *  delete pages. Also returns false if querying the user rights failed.
	 */
	private function isSourceDeleteAllowed( SourceUrl $sourceUrl, User $user ) {
		if ( !$this->sourceDeletionEnabled ) {
			return false;
		}

		return $this->remoteActionApi->executeUserRightsQuery( $sourceUrl, $user )
			->isGood();
	}

}
