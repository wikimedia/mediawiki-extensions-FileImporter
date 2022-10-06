<?php

namespace FileImporter\Services\Wikitext;

use NamespaceInfo;

/**
 * A small parser for wiki links that is able to understand namespace prefixes in a specific
 * language, e.g. [[Kategorie:…]] from a German wiki, and unlocalize them to their canonical English
 * form, e.g. [[Category:…]].
 *
 * As of now, we intentionally do not use MediaWiki's TitleParser infrastructure for a few reasons:
 * - It does to many things (most notably extracting known interwiki prefixes) we really don't care
 *   about here.
 * - We don't want to do any normalization on link elements we don't care about (basicaly everything
 *   except the namespace) as these would show up as unrelated changes in the diff.
 *
 * @license GPL-2.0-or-later
 */
class NamespaceUnlocalizer implements WikiLinkCleaner {

	/**
	 * @var NamespaceNameLookup
	 */
	private $namespaceNameLookup;

	/**
	 * @var NamespaceInfo|null
	 */
	private $namespaceInfo;

	/**
	 * @param NamespaceNameLookup $namespaceNameLookup
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct(
		NamespaceNameLookup $namespaceNameLookup,
		NamespaceInfo $namespaceInfo
	) {
		$this->namespaceNameLookup = $namespaceNameLookup;
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * @param string $link
	 *
	 * @return string
	 */
	public function process( string $link ): string {
		return preg_replace_callback(
			'/^
				# Group 1 captures an optional leading colon, the extra + avoid backtracking
				(\h*+:?\h*+)
				# Ungreedy group 2 captures the first prefix
				([^\v:]+?)
				# Must be followed by a colon and something plausible
				(?=\h*+:[^\v:][^\v]*$)
			/xu',
			function ( $matches ) {
				[ $unchanged, $colon, $name ] = $matches;
				// Normalize to use underscores, as this is what the services require
				$name = trim( preg_replace( '/[\s\xA0_]+/u', '_', $name ), '_' );

				$namespaceId = $this->namespaceNameLookup->getIndex( $name );
				if ( $namespaceId === false
					|| $namespaceId === NS_MAIN
					// The Project namespace shouldn't be "unlocalized" because it is not localized,
					// but configured via $wgMetaNamespace or $wgSitename.
					|| $namespaceId === NS_PROJECT
				) {
					return $unchanged;
				}

				$canonicalName = $this->namespaceInfo->getCanonicalName( $namespaceId );
				if ( $canonicalName === false || $canonicalName === $name ) {
					return $unchanged;
				}

				return $colon . str_replace( '_', ' ', $canonicalName );
			},
			$link,
			1
		);
	}

}
