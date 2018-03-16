<?php

namespace FileImporter\Services;

use Config;
use FileImporter\Data\FileRevision;
use FileImporter\Data\TextRevision;
use Title;
use WikiRevision;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class WikiRevisionFactory {

	/**
	 * @var Config
	 */
	private $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	private function getWikiRevision() {
		return new WikiRevision( $this->config );
	}

	/**
	 * @param FileRevision $fileRevision
	 * @param string $src
	 * @param bool $isTemp
	 *
	 * @return WikiRevision
	 */
	public function newFromFileRevision( FileRevision $fileRevision, $src, $isTemp ) {
		$revision = $this->getWikiRevision();
		$revision->setTitle( Title::newFromText(
			$this->removeNamespaceFromString( $fileRevision->getField( 'name' ) ),
			NS_FILE )
		);
		$revision->setTimestamp( $fileRevision->getField( 'timestamp' ) );
		$revision->setFileSrc( $src, $isTemp );
		$revision->setSha1Base36( $fileRevision->getField( 'sha1' ) );
		$revision->setUsername( $fileRevision->getField( 'user' ) );
		$revision->setComment( $fileRevision->getField( 'description' ) );

		return $revision;
	}

	/**
	 * @param TextRevision $textRevision
	 *
	 * @return WikiRevision
	 */
	public function newFromTextRevision( TextRevision $textRevision ) {
		$revision = $this->getWikiRevision();
		$revision->setTitle( Title::newFromText(
			$this->removeNamespaceFromString( $textRevision->getField( 'title' ) ),
			NS_FILE )
		);
		$revision->setTimestamp( $textRevision->getField( 'timestamp' ) );
		$revision->setSha1Base36( $textRevision->getField( 'sha1' ) );
		$revision->setUsername( $textRevision->getField( 'user' ) );
		$revision->setComment( $textRevision->getField( 'comment' ) );
		$revision->setModel( $textRevision->getField( 'contentmodel' ) );
		$revision->setFormat( $textRevision->getField( 'contentformat' ) );
		$revision->setMinor( $textRevision->getField( 'minor' ) );
		$revision->setText( $textRevision->getField( '*' ) );

		return $revision;
	}

	/**
	 * @param string $title
	 *
	 * @return string
	 */
	private function removeNamespaceFromString( $title ) {
		$splitTitle = explode( ':', $title );
		return array_pop( $splitTitle );
	}

}
