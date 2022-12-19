<?php

namespace FileImporter\Services;

use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\User\UserIdentity;
use Parser;
use ParserOptions;
use Title;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @license GPL-2.0-or-later
 */
class CategoryExtractor {

	/** @var Parser */
	private $parser;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/**
	 * @param Parser $parser
	 * @param ILoadBalancer $loadBalancer
	 * @param LinkBatchFactory $linkBatchFactory
	 */
	public function __construct(
		Parser $parser,
		ILoadBalancer $loadBalancer,
		LinkBatchFactory $linkBatchFactory
	) {
		$this->parser = $parser;
		$this->loadBalancer = $loadBalancer;
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
	public function getCategoriesGrouped( $text, Title $title, UserIdentity $user ) {
		$categoryMap = $this->parser->parse(
			$text,
			$title,
			new ParserOptions( $user )
		)->getCategories();
		$allCategories = array_keys( $categoryMap );

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
	private function queryHiddenCategories( array $categories ) {
		if ( $categories === [] ) {
			return [];
		}

		$arr = [ NS_CATEGORY => array_flip( $categories ) ];
		$lb = $this->linkBatchFactory->newLinkBatch();
		$lb->setArray( $arr );

		# Fetch categories having the `hiddencat` property.
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
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
