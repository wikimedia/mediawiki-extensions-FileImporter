<?php

namespace FileImporter\Services;

use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use ParserFactory;
use ParserOptions;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @license GPL-2.0-or-later
 */
class CategoryExtractor {

	/** @var ParserFactory */
	private $parserFactory;
	/** @var IConnectionProvider */
	private $connectionProvider;
	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	public function __construct(
		ParserFactory $parserFactory,
		IConnectionProvider $connectionProvider,
		LinkBatchFactory $linkBatchFactory
	) {
		$this->parserFactory = $parserFactory;
		$this->connectionProvider = $connectionProvider;
		$this->linkBatchFactory = $linkBatchFactory;
	}

	/**
	 * Find categories for a given page.
	 *
	 * @param string $text Body of the page to scan.
	 * @param Title $title Page title for context, because parsing might depend on this
	 * @param UserIdentity $user User for context, because parsing might depend on this
	 *
	 * @return array Two lists of category names, grouped by local visibility.
	 * 		[ $visibleCategories, $hiddenCategories ]
	 */
	public function getCategoriesGrouped( string $text, Title $title, UserIdentity $user ): array {
		$allCategories = $this->parserFactory->getInstance()->parse(
			$text,
			$title,
			new ParserOptions( $user )
		)->getCategoryNames();

		$hiddenCategories = $this->queryHiddenCategories( $allCategories );
		$visibleCategories = array_diff( $allCategories, $hiddenCategories );

		return [ $visibleCategories, $hiddenCategories ];
	}

	/**
	 * Query categories to find which are hidden.
	 *
	 * @param string[] $categories List of all category names.
	 *
	 * @return string[] List of hidden categories.
	 */
	private function queryHiddenCategories( array $categories ): array {
		if ( $categories === [] ) {
			return [];
		}

		$arr = [ NS_CATEGORY => array_flip( $categories ) ];
		$lb = $this->linkBatchFactory->newLinkBatch();
		$lb->setArray( $arr );

		# Fetch categories having the `hiddencat` property.
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$hiddenCategories = $dbr->selectFieldValues(
			[ 'page', 'page_props' ],
			'page_title',
			$lb->constructSet( 'page', $dbr ),
			__METHOD__,
			[],
			[ 'page_props' => [ 'INNER JOIN', [
				'pp_propname' => 'hiddencat',
				'pp_page = page_id'
			] ] ]
		);

		return $hiddenCategories;
	}

}
