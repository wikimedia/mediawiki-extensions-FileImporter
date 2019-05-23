<?php

namespace FileImporter\Services\Wikitext;

use FileImporter\Data\WikitextConversions;

/**
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class WikitextContentCleaner {

	/**
	 * @var int
	 */
	private $latestNumberOfReplacements = 0;

	/**
	 * @var WikitextConversions
	 */
	private $wikitextConversions;

	public function __construct( WikitextConversions $conversions ) {
		$this->wikitextConversions = $conversions;
	}

	/**
	 * @return int
	 */
	public function getLatestNumberOfReplacements() {
		return $this->latestNumberOfReplacements;
	}

	/**
	 * @param string $wikitext
	 *
	 * @return string
	 */
	public function cleanWikitext( $wikitext ) {
		$wikitext = $this->cleanHeadings( $wikitext );
		$wikitext = $this->cleanTemplates( $wikitext );
		return trim( $wikitext );
	}

	/**
	 * @param string $wikitext
	 *
	 * @return string
	 */
	private function cleanHeadings( $wikitext ) {
		return preg_replace_callback(
			'/^
				# Group 1
				(
					# Group 2 captures any opening equal signs, the extra + avoids backtracking
					(=++)
					# Consume horizontal whitespace
					\h*+
				)
				# The ungreedy group 3 will capture the trimmed heading
				(.*?)
				# Look-ahead for what group 2 captured
				(?=\h*\2\h*$)
			/mx',
			function ( $matches ) {
				return $matches[1] . $this->wikitextConversions->swapHeading( $matches[3] );
			},
			$wikitext
		);
	}

	private function cleanTemplates( $wikitext ) {
		$this->latestNumberOfReplacements = 0;

		preg_match_all(
			// This intentionally only searches for the start of each template
			'/(?<!{){{\s*+([^{|}]+?)\s*(?=\||}})/s',
			$wikitext,
			$matches,
			PREG_OFFSET_CAPTURE
		);

		// Replacements must be applied in reverse order to not mess with the captured offsets!
		for ( $i = count( $matches[1] ); $i-- > 0; ) {
			list( $oldTemplateName, $offset ) = $matches[1][$i];

			$isObsolete = $this->wikitextConversions->isObsoleteTemplate( $oldTemplateName );
			$newTemplateName = $this->wikitextConversions->swapTemplate( $oldTemplateName );
			if ( !$isObsolete && !$newTemplateName ) {
				continue;
			}

			$endOfTemplateName = $offset + strlen( $oldTemplateName );
			$parseResult = $this->parseTemplate( $wikitext, $endOfTemplateName );

			$this->latestNumberOfReplacements++;

			if ( $isObsolete ) {
				$start = $matches[0][$i][1];
				$wikitext = substr_replace( $wikitext, '', $start, $parseResult['end'] - $start );
				continue;
			}

			$wikitext = $this->renameTemplateParameters(
				$wikitext,
				$parseResult['parameters'],
				$this->wikitextConversions->getTemplateParameters( $oldTemplateName )
			);
			$wikitext = $this->addRequiredTemplateParameters(
				$wikitext,
				$this->wikitextConversions->getRequiredTemplateParameters( $oldTemplateName ),
				$parseResult['parameters'],
				$endOfTemplateName
			);

			$wikitext = substr_replace(
				$wikitext,
				$newTemplateName,
				$offset,
				strlen( $oldTemplateName )
			);
		}

		return preg_replace( '/\n\s*\n\s*\n/', "\n\n", $wikitext );
	}

	/**
	 * @suppress PhanTypeInvalidDimOffset false positive with $p being -1
	 * @param string $wikitext
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
	private function parseTemplate( $wikitext, $startPosition ) {
		$max = strlen( $wikitext );
		$nesting = 0;
		$params = [];
		$p = -1;
		$number = 0;

		for ( $i = $startPosition; $i < $max; $i++ ) {
			switch ( $wikitext[$i] ) {
				case '}':
					if ( $wikitext[$i + 1] === '}' ) {
						if ( !$nesting ) {
							$max = $i + 2;
							// Found the closing }}, abort the switch and the for-loop
							break 2;
						}
						$nesting--;
						// Skip the second bracket, it can't be the start of another pair
						$i++;
					}
					break;
				case '{':
					if ( $wikitext[$i + 1] === '{' ) {
						$nesting++;
						// Skip the second bracket, it can't be the start of another pair
						$i++;
					}
					break;
				case '|':
					if ( !$nesting ) {
						$params[++$p] = [ 'number' => ++$number, 'offset' => $i + 1 ];
						$params[$p]['format'] = $this->scanFormatSnippet( $wikitext, $i ) . '_=';
					}
					break;
				case '=':
					if ( !$nesting && $p !== -1 && !isset( $params[$p]['name'] ) ) {
						unset( $params[$p]['number'] );
						$number--;

						$offset = $params[$p]['offset'];
						$name = rtrim( substr( $wikitext, $offset, $i - $offset ) );
						$params[$p]['name'] = ltrim( $name );
						// Skip (optional) whitespace between | and the parameter name
						$params[$p]['offset'] += strlen( $name ) - strlen( $params[$p]['name'] );
						$params[$p]['format'] = rtrim( $params[$p]['format'], '=' )
							. $this->scanFormatSnippet( $wikitext, $i );
						// TODO: Value replacements are currently not supported.
					}
					break;
			}
		}

		return [
			'end' => $max,
			'parameters' => $params,
		];
	}

	/**
	 * @param string $wikitext
	 * @param int $offset
	 *
	 * @return string Substring from $wikitext including the character at $offset, and all
	 *  whitespace left and right
	 */
	private function scanFormatSnippet( $wikitext, $offset ) {
		$from = $offset;
		while ( $from > 0 && ctype_space( $wikitext[$from - 1] ) ) {
			$from--;
		}

		$to = $offset + 1;
		$max = strlen( $wikitext );
		while ( $to < $max && ctype_space( $wikitext[$to] ) ) {
			$to++;
		}

		return substr( $wikitext, $from, $to - $from );
	}

	/**
	 * @param string $wikitext
	 * @param array[] $parameters "parameters" list as returned by {@see parseTemplateParameters}
	 * @param string[] $replacements Array mapping old to new parameter names
	 *
	 * @return string
	 */
	private function renameTemplateParameters( $wikitext, array $parameters, array $replacements ) {
		if ( $replacements === [] ) {
			return $wikitext;
		}

		// Replacements must be applied in reverse order to not mess with the captured offsets!
		for ( $i = count( $parameters ); $i-- > 0; ) {
			$from = $parameters[$i]['name'] ?? $parameters[$i]['number'];

			if ( isset( $replacements[$from] ) ) {
				$to = $replacements[$from];
				$offset = $parameters[$i]['offset'];
				if ( isset( $parameters[$i]['name'] ) ) {
					$wikitext = substr_replace( $wikitext, $to, $offset, strlen( $from ) );
				} else {
					// Insert parameter name when the source parameter was unnamed
					$wikitext = substr_replace( $wikitext, $to . '=', $offset, 0 );
				}
			}
		}

		return $wikitext;
	}

	/**
	 * @param string $wikitext
	 * @param string[] $required List of parameter name => string value pairs
	 * @param array[] $parameters "parameters" list as returned by {@see parseTemplateParameters}
	 * @param int $offset Exact position where to insert the new parameter
	 *
	 * @return string
	 */
	private function addRequiredTemplateParameters(
		$wikitext,
		array $required,
		array $parameters,
		$offset
	) {
		if ( $required === [] ) {
			return $wikitext;
		}

		foreach ( $parameters as $param ) {
			$name = $param['name'] ?? $param['number'];
			unset( $required[$name] );
		}

		$format = $parameters ? $parameters[0]['format'] : '|_=';
		$newWikitext = '';
		foreach ( $required as $name => $value ) {
			$newWikitext .= str_replace( '_', $name, $format ) . $value;
		}

		return substr_replace( $wikitext, $newWikitext, $offset, 0 );
	}

}
