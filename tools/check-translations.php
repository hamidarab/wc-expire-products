<?php
/**
 * Translation coverage and freshness checks.
 *
 * Used by tests/run.php and tools/build-release.sh. Run directly with:
 * php tools/check-translations.php
 *
 * @package ProductExpirationEasyPeasy
 */

const PEEP_I18N_DOMAIN = 'product-expiration-easy-peasy';

/**
 * Gettext functions to scan: name => argument positions of msgid, plural, context.
 */
const PEEP_I18N_FUNCTIONS = array(
	'__'          => array( 0, null, null ),
	'_e'          => array( 0, null, null ),
	'esc_html__'  => array( 0, null, null ),
	'esc_html_e'  => array( 0, null, null ),
	'esc_attr__'  => array( 0, null, null ),
	'esc_attr_e'  => array( 0, null, null ),
	'_x'          => array( 0, null, 1 ),
	'_ex'         => array( 0, null, 1 ),
	'esc_html_x'  => array( 0, null, 1 ),
	'esc_attr_x'  => array( 0, null, 1 ),
	'_n'          => array( 0, 1, null ),
	'_nx'         => array( 0, 1, 3 ),
	'_n_noop'     => array( 0, 1, null ),
	'_nx_noop'    => array( 0, 1, 2 ),
);

/**
 * Decode a PHP string literal token.
 *
 * @param string $literal Token text including quotes.
 * @return string
 */
function peep_i18n_decode_literal( $literal ) {
	$body = substr( $literal, 1, -1 );

	if ( "'" === $literal[0] ) {
		return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body );
	}

	return stripcslashes( $body );
}

/**
 * Collect translatable strings for this plugin's text domain.
 *
 * @param string $root Plugin root.
 * @return array<string, string> Gettext key (context EOT msgid) => "file:line".
 */
function peep_i18n_source_strings( $root ) {
	$files = array( $root . '/product-expiration-easy-peasy.php' );

	foreach ( array( 'includes', 'views' ) as $dir ) {
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}
	}

	sort( $files );
	$strings = array();

	foreach ( $files as $file ) {
		$tokens = array_values(
			array_filter(
				token_get_all( (string) file_get_contents( $file ) ),
				static function ( $token ) {
					return ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );
				}
			)
		);
		$count  = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];

			if ( ! is_array( $token ) || T_STRING !== $token[0] || ! isset( PEEP_I18N_FUNCTIONS[ $token[1] ] ) ) {
				continue;
			}

			$previous = $tokens[ $i - 1 ] ?? null;

			if ( is_array( $previous ) && in_array( $previous[0], array( T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON ), true ) ) {
				continue;
			}

			if ( '(' !== ( $tokens[ $i + 1 ] ?? null ) ) {
				continue;
			}

			// Split the call's arguments at top-level commas.
			$args  = array( array() );
			$depth = 0;

			for ( $j = $i + 2; $j < $count; $j++ ) {
				$part = $tokens[ $j ];

				if ( in_array( $part, array( '(', '[', '{' ), true ) ) {
					++$depth;
				} elseif ( in_array( $part, array( ')', ']', '}' ), true ) ) {
					if ( 0 === $depth ) {
						break;
					}
					--$depth;
				} elseif ( ',' === $part && 0 === $depth ) {
					$args[] = array();
					continue;
				}

				$args[ count( $args ) - 1 ][] = $part;
			}

			$literals = array_map(
				static function ( $arg ) {
					return 1 === count( $arg ) && is_array( $arg[0] ) && T_CONSTANT_ENCAPSED_STRING === $arg[0][0]
						? peep_i18n_decode_literal( $arg[0][1] )
						: null;
				},
				$args
			);

			if ( PEEP_I18N_DOMAIN !== end( $literals ) ) {
				continue;
			}

			list( $msgid_at, , $context_at ) = PEEP_I18N_FUNCTIONS[ $token[1] ];
			$msgid                           = $literals[ $msgid_at ] ?? null;

			if ( null === $msgid ) {
				continue;
			}

			$context                                = null === $context_at ? null : ( $literals[ $context_at ] ?? null );
			$key                                    = null === $context ? $msgid : $context . "\4" . $msgid;
			$strings[ $key ] = $strings[ $key ] ?? substr( $file, strlen( $root ) + 1 ) . ':' . $token[2];
		}
	}

	return $strings;
}

/**
 * Parse a .po file.
 *
 * @param string $file Path.
 * @return array<string, array{msgstr: string[], fuzzy: bool}> Keyed like peep_i18n_source_strings().
 */
function peep_i18n_parse_po( $file ) {
	$entries = array();
	$entry   = null;
	$field   = null;
	$fuzzy   = false;

	$flush = static function () use ( &$entries, &$entry, &$fuzzy ) {
		if ( null !== $entry && isset( $entry['msgid'] ) && '' !== $entry['msgid'] ) {
			$key             = isset( $entry['msgctxt'] ) ? $entry['msgctxt'] . "\4" . $entry['msgid'] : $entry['msgid'];
			$entries[ $key ] = array(
				'msgstr' => $entry['msgstr'] ?? array(),
				'fuzzy'  => $fuzzy,
			);
		}

		$entry = null;
		$fuzzy = false;
	};

	foreach ( (array) file( $file, FILE_IGNORE_NEW_LINES ) as $line ) {
		$line = rtrim( $line, "\r" );

		if ( '' === trim( $line ) ) {
			$flush();
			$field = null;
			continue;
		}

		if ( str_starts_with( $line, '#~' ) ) {
			continue;
		}

		if ( str_starts_with( $line, '#,' ) ) {
			$fuzzy = $fuzzy || str_contains( $line, 'fuzzy' );
			continue;
		}

		if ( str_starts_with( $line, '#' ) ) {
			continue;
		}

		if ( preg_match( '/^(msgctxt|msgid|msgid_plural|msgstr)(?:\[(\d+)\])?\s+"(.*)"$/', $line, $match ) ) {
			if ( in_array( $match[1], array( 'msgctxt', 'msgid' ), true ) && null !== $entry && isset( $entry['msgstr'] ) ) {
				$keep_fuzzy = $fuzzy;
				$flush();
				$fuzzy = $keep_fuzzy;
			}

			$entry = $entry ?? array();
			$value = stripcslashes( $match[3] );

			if ( 'msgstr' === $match[1] ) {
				$index                     = '' === $match[2] ? 0 : (int) $match[2];
				$entry['msgstr'][ $index ] = $value;
				$field                     = array( 'msgstr', $index );
			} else {
				$entry[ $match[1] ] = $value;
				$field              = array( $match[1], null );
			}
			continue;
		}

		if ( null !== $field && null !== $entry && preg_match( '/^"(.*)"$/', $line, $match ) ) {
			if ( 'msgstr' === $field[0] ) {
				$entry['msgstr'][ $field[1] ] .= stripcslashes( $match[1] );
			} else {
				$entry[ $field[0] ] .= stripcslashes( $match[1] );
			}
		}
	}

	$flush();

	return $entries;
}

/**
 * Parse a compiled .mo file.
 *
 * @param string $file Path.
 * @return array<string, string[]> Key => translations (plural forms split).
 */
function peep_i18n_parse_mo( $file ) {
	$data  = (string) file_get_contents( $file );
	$magic = unpack( 'V', substr( $data, 0, 4 ) )[1];

	if ( 0x950412de === $magic ) {
		$format = 'V';
	} elseif ( 0x950412de === unpack( 'N', substr( $data, 0, 4 ) )[1] ) {
		$format = 'N';
	} else {
		throw new RuntimeException( basename( $file ) . ' is not a valid .mo file.' );
	}

	$header = unpack( $format . 'revision/' . $format . 'count/' . $format . 'originals/' . $format . 'translations', substr( $data, 4, 16 ) );
	$result = array();

	for ( $i = 0; $i < $header['count']; $i++ ) {
		$original    = unpack( $format . 'length/' . $format . 'offset', substr( $data, $header['originals'] + $i * 8, 8 ) );
		$translation = unpack( $format . 'length/' . $format . 'offset', substr( $data, $header['translations'] + $i * 8, 8 ) );
		$key         = explode( "\0", substr( $data, $original['offset'], $original['length'] ) )[0];

		if ( '' !== $key ) {
			$result[ $key ] = explode( "\0", substr( $data, $translation['offset'], $translation['length'] ) );
		}
	}

	return $result;
}

/**
 * Run every check for one locale.
 *
 * @param string $root   Plugin root.
 * @param string $locale Locale.
 * @return string[] Problems; empty when the translation is complete and fresh.
 */
function peep_i18n_problems( $root, $locale = 'fa_IR' ) {
	$base     = $root . '/languages/' . PEEP_I18N_DOMAIN . '-' . $locale;
	$problems = array();

	foreach ( array( '.po', '.mo' ) as $extension ) {
		if ( ! is_file( $base . $extension ) ) {
			return array( 'Missing ' . basename( $base . $extension ) . '.' );
		}
	}

	$po = peep_i18n_parse_po( $base . '.po' );

	// Coverage: every source string has a usable translation.
	foreach ( peep_i18n_source_strings( $root ) as $key => $where ) {
		$label = str_replace( "\4", ' | ', $key );

		if ( ! isset( $po[ $key ] ) ) {
			$problems[] = sprintf( 'Not in .po: "%s" (%s)', $label, $where );
		} elseif ( $po[ $key ]['fuzzy'] ) {
			$problems[] = sprintf( 'Fuzzy in .po: "%s" (%s)', $label, $where );
		} elseif ( in_array( '', $po[ $key ]['msgstr'], true ) || array() === $po[ $key ]['msgstr'] ) {
			$problems[] = sprintf( 'Untranslated in .po: "%s" (%s)', $label, $where );
		}
	}

	// Freshness: compiled files hold exactly the translated .po entries.
	$expected = array();

	foreach ( $po as $key => $entry ) {
		if ( ! $entry['fuzzy'] && array() !== $entry['msgstr'] && ! in_array( '', $entry['msgstr'], true ) ) {
			ksort( $entry['msgstr'] );
			$expected[ $key ] = array_values( $entry['msgstr'] );
		}
	}

	$compiled = array( '.mo' => peep_i18n_parse_mo( $base . '.mo' ) );

	if ( is_file( $base . '.l10n.php' ) ) {
		$l10n                    = include $base . '.l10n.php';
		$compiled['.l10n.php'] = array_map(
			static function ( $value ) {
				return explode( "\0", (string) $value );
			},
			is_array( $l10n ) && isset( $l10n['messages'] ) ? $l10n['messages'] : array()
		);
	}

	foreach ( $compiled as $extension => $messages ) {
		ksort( $messages );
		ksort( $expected );

		if ( $messages !== $expected ) {
			$stale      = array_keys( array_diff_key( $expected, $messages ) + array_diff_key( $messages, $expected ) );
			$changed    = array_keys( array_filter( array_intersect_key( $expected, $messages ), static fn( $value, $key ) => $messages[ $key ] !== $value, ARRAY_FILTER_USE_BOTH ) );
			$problems[] = sprintf(
				'%s is stale; recompile it from the .po (%d missing or extra, %d changed%s).',
				basename( $base . $extension ),
				count( $stale ),
				count( $changed ),
				array() === $stale && array() === $changed ? '' : ': "' . str_replace( "\4", ' | ', (string) ( $stale[0] ?? $changed[0] ) ) . '"'
			);
		}
	}

	return $problems;
}

if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === __FILE__ ) {
	$peep_i18n_problems = peep_i18n_problems( dirname( __DIR__ ), $argv[1] ?? 'fa_IR' );

	if ( array() !== $peep_i18n_problems ) {
		fwrite( STDERR, "Translation check failed:\n  " . implode( "\n  ", $peep_i18n_problems ) . "\n" );
		exit( 1 );
	}

	printf( "translations=ok (%d source strings)\n", count( peep_i18n_source_strings( dirname( __DIR__ ) ) ) );
}
