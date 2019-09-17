<?php

namespace FileImporter\Services;

use ExternalUserNames;
use FileImporter\Data\FileRevision;
use FileImporter\Data\TextRevision;
use HashConfig;
use Title;
use User;
use WikiRevision;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class WikiRevisionFactory {

	/**
	 * @var string
	 */
	private $interwikiPrefix;

	/**
	 * @var ExternalUserNames
	 */
	private $externalUserNames;

	// TODO: should be changed back to lowercase when T221235 is fixed.
	const DEFAULT_USERNAME_PREFIX = 'Imported';

	public function __construct() {
		$this->externalUserNames = new ExternalUserNames( self::DEFAULT_USERNAME_PREFIX, true );
	}

	private function getWikiRevision() {
		return new WikiRevision( new HashConfig() );
	}

	/**
	 * @param string $prefix
	 */
	public function setInterWikiPrefix( $prefix ) {
		$this->interwikiPrefix = $prefix;
		$this->externalUserNames = new ExternalUserNames(
			$prefix ?: self::DEFAULT_USERNAME_PREFIX,
			true
		);
	}

	/**
	 * @param FileRevision $fileRevision
	 * @param string $src
	 *
	 * @return WikiRevision
	 */
	public function newFromFileRevision( FileRevision $fileRevision, $src ) {
		$revision = $this->getWikiRevision();
		$revision->setTitle( $this->makeTitle( $fileRevision->getField( 'name' ) ) );
		$revision->setTimestamp( $fileRevision->getField( 'timestamp' ) );
		$revision->setFileSrc( $src, true );
		$revision->setSha1Base36( $fileRevision->getField( 'sha1' ) );
		$revision->setComment( $fileRevision->getField( 'description' ) );

		// create user with CentralAuth/SUL if nonexistent
		$importedUser = $this->externalUserNames->applyPrefix( $fileRevision->getField( 'user' ) );
		$revision->setUsername( $importedUser );
		$revision->setUserObj( User::newFromName( $importedUser ) );

		return $revision;
	}

	/**
	 * @param TextRevision $textRevision
	 *
	 * @return WikiRevision
	 */
	public function newFromTextRevision( TextRevision $textRevision ) {
		$revision = $this->getWikiRevision();
		$revision->setTitle( $this->makeTitle( $textRevision->getField( 'title' ) ) );
		$revision->setTimestamp( $textRevision->getField( 'timestamp' ) );
		$revision->setSha1Base36( $textRevision->getField( 'sha1' ) );
		// create user with CentralAuth/SUL if nonexistent and use the prefix only as fallback
		$revision->setUsername(
			$this->externalUserNames->applyPrefix( $textRevision->getField( 'user' ) )
		);
		$revision->setComment(
			$this->prefixCommentLinks( $textRevision->getField( 'comment' ) )
		);
		$revision->setModel( $textRevision->getField( 'contentmodel' ) );
		$revision->setFormat( $textRevision->getField( 'contentformat' ) );
		$revision->setMinor( $textRevision->getField( 'minor' ) );
		$revision->setText( $textRevision->getField( '*' ) );

		return $revision;
	}

	/**
	 * @param string $title
	 *
	 * @return Title|null
	 */
	private function makeTitle( $title ) {
		$splitTitle = explode( ':', $title );
		return Title::makeTitleSafe( NS_FILE, end( $splitTitle ) );
	}

	/**
	 * TODO: We can almost certainly replace this with WikiLinkCleaners.
	 *
	 * @param string $summaryText
	 *
	 * @return string
	 */
	private function prefixCommentLinks( $summaryText ) {
		if ( !$this->interwikiPrefix ) {
			return $summaryText;
		}

		/** Mostly taken from @see Linker::formatLinksInComment */
		return preg_replace(
			'/
				\[\[
				\s*+ # ignore leading whitespace, the *+ quantifier disallows backtracking
				:?
				(?=
					[^\[\]|]+
					(?:\|
						# The "possessive" *+ quantifier disallows backtracking
						(?:]?[^\]])*+
					)?
					\]\]
				)
			/x',
			'[[' . $this->interwikiPrefix . ':',
			$summaryText
		);
	}

}
