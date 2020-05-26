<?php

namespace FileImporter\Services;

use LinkBatch;
use Parser;
use ParserOptions;
use Title;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @license GPL-2.0-or-later
 */
class CategoryExtractor {

	/** @var Parser */
	private $parser;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/**
	 * @param Parser $parser
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct( Parser $parser, ILoadBalancer $loadBalancer ) {
		$this->parser = $parser;
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * Find categories for a given page.
	 *
	 * @param string $text Body of the page to scan.
	 * @param Title $title Page title for context, because parsing might depend on this
	 * @param User $user User for context, because parsing might depend on this
	 *
	 * @return array Two lists of category names, grouped by local visibility.
	 * 		[ $visibleCategories, $hiddenCategories ]
	 */
	public function getCategoriesGrouped( $text, Title $title, User $user ) {
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
		$lb = new LinkBatch;
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
