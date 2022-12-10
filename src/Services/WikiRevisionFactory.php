<?php

namespace FileImporter\Services;

use ContentHandler;
use ExternalUserNames;
use FileImporter\Data\FileRevision;
use FileImporter\Data\TextRevision;
use MediaWiki\Revision\SlotRecord;
use Title;
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
	public const DEFAULT_USERNAME_PREFIX = 'Imported';

	public function __construct() {
		$this->externalUserNames = new ExternalUserNames( self::DEFAULT_USERNAME_PREFIX, true );
	}

	/**
	 * @param string $title
	 * @param string $timestamp
	 * @param string|null $sha1
	 * @return WikiRevision
	 */
	private function newWikiRevision(
		string $title,
		string $timestamp,
		?string $sha1
	): WikiRevision {
		$titleParts = explode( ':', $title );
		$filename = end( $titleParts );

		// The 3 fields rev_page, rev_timestamp, and rev_sha1 make a revision unique, see
		// ImportableOldRevisionImporter::import()
		$revision = new WikiRevision();
		$revision->setTitle( Title::makeTitleSafe( NS_FILE, $filename ) );
		$revision->setTimestamp( $timestamp );
		// File revisions older than 2012 might not have a hash yet. Import as is.
		if ( $sha1 ) {
			$revision->setSha1Base36( $sha1 );
		}

		return $revision;
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
		$revision = $this->newWikiRevision(
			$fileRevision->getField( 'name' ),
			$fileRevision->getField( 'timestamp' ),
			$fileRevision->getField( 'sha1' )
		);
		$revision->setFileSrc( $src, true );
		$revision->setComment( $fileRevision->getField( 'description' ) );

		$importedUser = $this->createCentralAuthUser( $fileRevision->getField( 'user' ) );
		$revision->setUsername( $importedUser );

		// Mark old file revisions as such
		$archiveName = $fileRevision->getField( 'archivename' );
		if ( $archiveName ) {
			$revision->setArchiveName( $archiveName );
		}

		return $revision;
	}

	/**
	 * @param TextRevision $textRevision
	 *
	 * @return WikiRevision
	 */
	public function newFromTextRevision( TextRevision $textRevision ) {
		$content = ContentHandler::makeContent(
			$textRevision->getField( '*' ),
			null,
			$textRevision->getField( 'contentmodel' ),
			$textRevision->getField( 'contentformat' )
		);

		$revision = $this->newWikiRevision(
			$textRevision->getField( 'title' ),
			$textRevision->getField( 'timestamp' ),
			$textRevision->getField( 'sha1' )
		);
		$revision->setUsername( $this->createCentralAuthUser( $textRevision->getField( 'user' ) ) );
		$revision->setComment( $this->prefixCommentLinks( $textRevision->getField( 'comment' ) ) );
		$revision->setMinor( $textRevision->getField( 'minor' ) );
		$revision->setContent( SlotRecord::MAIN, $content );
		$revision->setTags(
			array_merge( $textRevision->getField( 'tags' ), [ 'fileimporter-imported' ] )
		);

		return $revision;
	}

	/**
	 * @param string $username
	 *
	 * @return string Either the unchanged username if it's a known local or valid CentralAuth/SUL
	 *  user, otherwise the name with the DEFAULT_USERNAME_PREFIX prefix prepended.
	 */
	private function createCentralAuthUser( string $username ): string {
		// This uses the prefix only as fallback
		return $this->externalUserNames->applyPrefix( $username );
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

		/** Mostly taken from @see MediaWiki\CommentFormatter\CommentParser::doWikiLinks */
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
