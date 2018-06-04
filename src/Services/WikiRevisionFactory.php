<?php

namespace FileImporter\Services;

use Config;
use ExternalUserNames;
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

	/**
	 * @var ExternalUserNames
	 */
	private $externalUserNames;

	const DEFAULT_USERNAME_PREFIX = 'imported';

	public function __construct( Config $config ) {
		$this->config = $config;
		$this->externalUserNames = new ExternalUserNames( self::DEFAULT_USERNAME_PREFIX, true );
	}

	private function getWikiRevision() {
		return new WikiRevision( $this->config );
	}

	/**
	 * @param string $prefix
	 */
	public function setUserNamePrefix( $prefix ) {
		if ( !$prefix ) {
			$prefix = self::DEFAULT_USERNAME_PREFIX;
		}
		$this->externalUserNames = new ExternalUserNames( $prefix, true );
	}

	/**
	 * @param FileRevision $fileRevision
	 * @param string $src
	 *
	 * @return WikiRevision
	 */
	public function newFromFileRevision( FileRevision $fileRevision, $src ) {
		$revision = $this->getWikiRevision();
		$revision->setTitle( Title::newFromText(
			$this->removeNamespaceFromString( $fileRevision->getField( 'name' ) ),
			NS_FILE )
		);
		$revision->setTimestamp( $fileRevision->getField( 'timestamp' ) );
		$revision->setFileSrc( $src, true );
		$revision->setSha1Base36( $fileRevision->getField( 'sha1' ) );
		$revision->setUsername(
			$this->externalUserNames->applyPrefix( $fileRevision->getField( 'user' ) )
		);
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
		$revision->setUsername(
			$this->externalUserNames->addPrefix( $textRevision->getField( 'user' ) )
		);
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
