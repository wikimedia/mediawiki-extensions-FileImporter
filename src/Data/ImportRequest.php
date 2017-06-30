<?php

namespace FileImporter\Data;

use FileImporter\Exceptions\LocalizedImportException;
use InvalidArgumentException;
use Message;
use Wikimedia\Assert\Assert;

/**
 * Alterations to be made usually provided by a user.
 */
class ImportRequest {

	/**
	 * @var SourceUrl
	 */
	private $url;

	/**
	 * @var null|string
	 */
	private $intendedName;

	/**
	 * @var null|string
	 */
	private $intendedText;

	/**
	 * ImportRequest constructor.
	 *
	 * @param string $url
	 * @param string|null $intendedName null for no intended change
	 * @param string|null $intendedText null for no intended change
	 *
	 * @throws InvalidArgumentException|LocalizedImportException
	 */
	public function __construct(
		$url,
		$intendedName = null,
		$intendedText = null
	) {
		Assert::parameterType( 'string', $url, '$url' );
		Assert::parameterType( 'string|null', $intendedName, '$intendedName' );
		Assert::parameterType( 'string|null', $intendedText, '$intendedText' );

		try {
			$this->url = new SourceUrl( urldecode( $url ) );
		} catch ( InvalidArgumentException $e ) {
			throw new LocalizedImportException( new Message( 'fileimporter-cantparseurl', [ $url ] ) );
		}

		$this->intendedName = $intendedName;
		$this->intendedText = $intendedText;
	}

	/**
	 * @return SourceUrl
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * @return null|string
	 */
	public function getIntendedName() {
		return $this->intendedName;
	}

	/**
	 * @return null|string
	 */
	public function getIntendedText() {
		return $this->intendedText;
	}

}
