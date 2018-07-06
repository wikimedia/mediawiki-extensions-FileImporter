<?php

namespace FileImporter\Services;

use FileImporter\Data\WikiTextConversions;

/**
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikiTextContentCleaner {

	/**
	 * @var int
	 */
	private $latestNumberOfReplacements = 0;

	/**
	 * @var WikiTextConversions
	 */
	private $wikiTextConversions;

	public function __construct( WikiTextConversions $wikiTextConversions ) {
		$this->wikiTextConversions = $wikiTextConversions;
	}

	/**
	 * @return int
	 */
	public function getLatestNumberOfReplacements() {
		return $this->latestNumberOfReplacements;
	}

	/**
	 * @param string $wikiText
	 *
	 * @return string
	 */
	public function cleanWikiText( $wikiText ) {
		$wikiText = $this->cleanHeadings( $wikiText );
		$wikiText = $this->cleanTemplates( $wikiText );
		return $wikiText;
	}

	/**
	 * @param string $wikiText
	 *
	 * @return string
	 */
	public function cleanHeadings( $wikiText ) {
		return preg_replace_callback( '/^((=+)\h*)(.*?)(?=\h*\2$)/m', function ( $matches ) {
			return $matches[1] . $this->wikiTextConversions->swapHeading( $matches[3] );
		}, $wikiText );
	}

	public function cleanTemplates( $wikiText ) {
		$this->latestNumberOfReplacements = 0;

		preg_match_all(
			// This intentionally only searches for the start of each template
			'/(?<!{){{\s*+([^{|}]+?)\s*(?=\||}})/s',
			$wikiText,
			$matches,
			PREG_OFFSET_CAPTURE
		);

		// Replacements must be applied in reverse order to not mess with the captured offsets!
		for ( $i = count( $matches[1] ); $i-- > 0; ) {
			list( $oldTemplateName, $offset ) = $matches[1][$i];

			$newTemplateName = $this->wikiTextConversions->swapTemplate( $oldTemplateName );
			if ( !$newTemplateName ) {
				continue;
			}

			$endOfTemplateName = $offset + strlen( $oldTemplateName );
			$parseResult = $this->parseTemplate( $wikiText, $endOfTemplateName );

			// TODO: Delete templates

			$wikiText = $this->renameTemplateParameters(
				$wikiText,
				$parseResult['parameters'],
				$this->wikiTextConversions->getTemplateParameters( $oldTemplateName )
			);
			$wikiText = $this->addRequiredTemplateParameters(
				$wikiText,
				$this->wikiTextConversions->getRequiredTemplateParameters( $oldTemplateName ),
				$parseResult['parameters'],
				$endOfTemplateName
			);

			$wikiText = substr_replace(
				$wikiText,
				$newTemplateName,
				$offset,
				strlen( $oldTemplateName )
			);

			$this->latestNumberOfReplacements++;
		}

		return $wikiText;
	}

	/**
	 * @param string $wikiText
	 * @param int $startPosition Must be after the opening {{, and before or exactly at the first |
	 *
	 * @return array Parse result in the following format:
	 * [
	 *     'parameters' => [
	 *         [
	 *             'offset' => absolute position of the parameter name in the wikitext, or where the
	 *                 parameter name needs to be placed for unnamed parameters,
	 *             'number' => positive integer number, only present for unnamed parameters,
	 *             'name' => optional string name of the parameter,
	 *         ],
	 *         …
	 *     ]
	 * ]
	 */
	public function parseTemplate( $wikiText, $startPosition ) {
		$max = strlen( $wikiText );
		$nesting = 0;
		$params = [];
		$p = -1;
		$number = 0;

		for ( $i = $startPosition; $i < $max; $i++ ) {
			switch ( $wikiText[$i] ) {
				case '}':
					if ( $wikiText[$i + 1] === '}' ) {
						if ( !$nesting ) {
							// Found the closing }}, abort the switch and the for-loop
							break 2;
						}
						$nesting--;
					}
					break;
				case '{':
					if ( $wikiText[$i + 1] === '{' ) {
						$nesting++;
					}
					break;
				case '|':
					if ( !$nesting ) {
						$params[++$p] = [ 'number' => ++$number, 'offset' => $i + 1 ];
						$params[$p]['format'] = $this->scanFormatSnippet( $wikiText, $i ) . '_=';
					}
					break;
				case '=':
					if ( !$nesting && $p !== -1 && !isset( $params[$p]['name'] ) ) {
						unset( $params[$p]['number'] );
						$number--;

						$offset = $params[$p]['offset'];
						$name = rtrim( substr( $wikiText, $offset, $i - $offset ) );
						$params[$p]['name'] = ltrim( $name );
						// Skip (optional) whitespace between | and the parameter name
						$params[$p]['offset'] += strlen( $name ) - strlen( $params[$p]['name'] );
						$params[$p]['format'] = rtrim( $params[$p]['format'], '=' )
							. $this->scanFormatSnippet( $wikiText, $i );
						// TODO: Value replacements are currently not supported.
					}
					break;
			}
		}

		return [
			// TODO: Add the end of the template as detected by the "}" case above, to be able to
			// delete templates
			'parameters' => $params,
		];
	}

	/**
	 * @param string $wikiText
	 * @param int $offset
	 *
	 * @return string Substring from $wikiText including the character at $offset, and all
	 *  whitespace left and right
	 */
	private function scanFormatSnippet( $wikiText, $offset ) {
		$from = $offset;
		while ( $from > 0 && ctype_space( $wikiText[$from - 1] ) ) {
			$from--;
		}

		$to = $offset + 1;
		$max = strlen( $wikiText );
		while ( $to < $max && ctype_space( $wikiText[$to] ) ) {
			$to++;
		}

		return substr( $wikiText, $from, $to - $from );
	}

	/**
	 * @param string $wikiText
	 * @param array[] $parameters "parameters" list as returned by {@see parseTemplateParameters}
	 * @param string[] $replacements Array mapping old to new parameter names
	 *
	 * @return string
	 */
	private function renameTemplateParameters( $wikiText, array $parameters, array $replacements ) {
		if ( $replacements === [] ) {
			return $wikiText;
		}

		// Replacements must be applied in reverse order to not mess with the captured offsets!
		for ( $i = count( $parameters ); $i-- > 0; ) {
			$from = isset( $parameters[$i]['name'] )
				? $parameters[$i]['name']
				: $parameters[$i]['number'];

			if ( isset( $replacements[$from] ) ) {
				$to = $replacements[$from];
				$offset = $parameters[$i]['offset'];
				if ( isset( $parameters[$i]['name'] ) ) {
					$wikiText = substr_replace( $wikiText, $to, $offset, strlen( $from ) );
				} else {
					// Insert parameter name when the source parameter was unnamed
					$wikiText = substr_replace( $wikiText, $to . '=', $offset, 0 );
				}
			}
		}

		return $wikiText;
	}

	/**
	 * @param string $wikiText
	 * @param string[] $required List of parameter name => string value pairs
	 * @param array[] $parameters "parameters" list as returned by {@see parseTemplateParameters}
	 * @param int $offset Exact position where to insert the new parameter
	 *
	 * @return string
	 */
	private function addRequiredTemplateParameters(
		$wikiText,
		array $required,
		array $parameters,
		$offset
	) {
		if ( $required === [] ) {
			return $wikiText;
		}

		foreach ( $parameters as $param ) {
			$name = isset( $param['name'] ) ? $param['name'] : $param['number'];
			unset( $required[$name] );
		}

		$format = $parameters ? $parameters[0]['format'] : '|_=';
		$newWikiText = '';
		foreach ( $required as $name => $value ) {
			$newWikiText .= str_replace( '_', $name, $format ) . $value;
		}

		return substr_replace( $wikiText, $newWikiText, $offset, 0 );
	}

}
