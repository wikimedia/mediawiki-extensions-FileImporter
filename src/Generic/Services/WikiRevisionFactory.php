<?php

namespace FileImporter\Generic\Services;

use Config;
use FileImporter\Generic\Data\FileRevision;
use FileImporter\Generic\Data\TextRevision;
use InvalidArgumentException;
use Title;
use WikiRevision;

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
		$revision->setTitle( Title::newFromText( $fileRevision->getField( 'name' ), NS_FILE ) );
		$revision->setTimestamp( $fileRevision->getField( 'timestamp' ) );
		$revision->setFileSrc( $src, $isTemp );
		$revision->setSha1Base36( $fileRevision->getField( 'sha1' ) );
		$revision->setUsername( $fileRevision->getField( 'user_text' ) );
		$revision->setComment( $fileRevision->getField( 'description' ) );

		return $revision;
	}

	public function newFromTextRevision( TextRevision $textRevision ) {
		$revision = $this->getWikiRevision();
		$revision->setTitle( Title::newFromText( $textRevision->getField( 'title' ), NS_FILE ) );
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

}
