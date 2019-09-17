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
use RequestContext;
use User;

class SourceWikiCleanupSnippet {

	/** @var bool */
	private $sourceEditingEnabled;
	/** @var bool */
	private $sourceDeletionEnabled;
	/** @var WikidataTemplateLookup $lookup */
	private $lookup;
	/** @var RemoteApiActionExecutor $remoteActionApi */
	private $remoteActionApi;

	/**
	 * @param bool $sourceEditingEnabled
	 * @param bool $sourceDeletionEnabled
	 */
	public function __construct(
		$sourceEditingEnabled = true,
		$sourceDeletionEnabled = true
	) {
		$this->sourceEditingEnabled = $sourceEditingEnabled;
		$this->sourceDeletionEnabled = $sourceDeletionEnabled;

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
		/** @var SourceUrl $sourceUrl */
		$sourceUrl = $importPlan->getRequest()->getUrl();

		$canAutomateEdit = $this->isSourceEditAllowed( $sourceUrl );
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
			$importPlan->setActionIsPerformed( 'offeredSourceDelete' );

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
			$importPlan->setActionIsPerformed( 'offeredSourceEdit' );

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
	 * @param SourceUrl $sourceUrl
	 * @return bool
	 */
	private function isSourceEditAllowed( SourceUrl $sourceUrl ) {
		return $this->sourceEditingEnabled &&
			( $this->lookup->fetchNowCommonsLocalTitle( $sourceUrl ) !== null );
	}

	/**
	 * @param SourceUrl $sourceUrl
	 * @param User $user
	 * @return bool
	 */
	private function isSourceDeleteAllowed( SourceUrl $sourceUrl, User $user ) {
		return $this->sourceDeletionEnabled &&
			in_array(
				'delete',
				( $this->remoteActionApi->executeUserRightsAction(
					$sourceUrl, $user )
				)['query']['userinfo']['rights'] ?? [] );
	}

}
