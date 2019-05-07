<?php

namespace FileImporter\Services;

use FileImporter\Services\Wikitext\WikiLinkCleaner;
use Language;
use NamespaceInfo;

/**
 * @license GPL-2.0-or-later
 */
class NamespaceUnlocalizer implements WikiLinkCleaner {

	/**
	 * @var Language|null
	 */
	private $sourceLanguage;

	/**
	 * @var NamespaceInfo|null
	 */
	private $namespaceInfo;

	public function __construct( Language $sourceLanguage, NamespaceInfo $namespaceInfo ) {
		if ( $sourceLanguage->getCode() === 'en' ) {
			return;
		}

		$this->sourceLanguage = $sourceLanguage;
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * @param string $link
	 *
	 * @return string
	 */
	public function process( $link ) {
		if ( !$this->sourceLanguage ) {
			return $link;
		}

		return preg_replace_callback(
			'/^
				# Group 1 captures an optional leading colon, the extra + avoid backtracking
				(\h*+:?\h*+)
				# Ungreedy group 2 captures the first prefix
				([^\v:]+?)
				# Must be followed by a colon and something plausible
				(?=\h*+:[^\v:])
			/x',
			function ( $matches ) {
				list( $unchanged, $colon, $name ) = $matches;
				// Normalize to use underscores, as this is what the services require
				$name = trim( preg_replace( '/[\s\xA0_]+/u', '_', $name ), '_' );

				$index = $this->sourceLanguage->getLocalNsIndex( $name );
				if ( $index === false || $index === NS_MAIN ) {
					return $unchanged;
				}

				$canonicalName = $this->namespaceInfo->getCanonicalName( $index );
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
