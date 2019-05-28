<?php


namespace FileImporter\Services;

use LinkBatch;
use Parser;
use ParserOptions;
use Title;
use Wikimedia\Rdbms\LoadBalancer;

class CategoryExtractor {

	/** @var Parser */
	private $parser;

	/** @var LoadBalancer */
	private $loadBalancer;

	public function __construct( Parser $parser, LoadBalancer $loadBalancer ) {
		$this->parser = $parser;
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * Find categories for a given page.
	 *
	 * @param string $text Body of the page to scan.
	 * @param Title $title Title of the page to scan.
	 *
	 * @return array Two lists of category names, grouped by local visibility.
	 * 		[ $visibleCategories, $hiddenCategories ]
	 */
	public function getCategoriesGrouped( $text, Title $title ) {
		$categoryMap = $this->parser->parse( $text, $title, new ParserOptions() )->getCategories();
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