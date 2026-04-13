<?php
/**
 * Minimal .pot generator for GLOBUS Debug Control.
 * Extracts __(), _e(), _n(), esc_html__(), esc_html_e(), esc_attr__(), esc_attr_e()
 * calls with 'globus-debug-control' text domain.
 *
 * Usage: php tools/gen-pot.php
 */

$base   = dirname( __DIR__ ) . '/globus-debug-control';
$domain = 'globus-debug-control';

// Collect all PHP files.
$files = [];
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base ) ) as $f ) {
	if ( $f->isFile() && $f->getExtension() === 'php' && strpos( $f->getPathname(), 'index.php' ) === false ) {
		$files[] = $f->getPathname();
	}
}
sort( $files );

// Patterns for i18n functions.
$single_funcs = '__\(|_e\(|esc_html__\(|esc_html_e\(|esc_attr__\(|esc_attr_e\(';

$entries = []; // key => ['refs' => [...], 'comments' => [...], 'plural' => string|null]

foreach ( $files as $filepath ) {
	$code    = file_get_contents( $filepath );
	$lines   = explode( "\n", $code );
	$relpath = str_replace( '\\', '/', str_replace( str_replace( '\\', '/', dirname( __DIR__ ) ) . '/', '', str_replace( '\\', '/', $filepath ) ) );
	$relpath = preg_replace( '#^globus-debug-control/#', '', $relpath );

	// Find translator comments above i18n calls.
	$translator_comments = [];
	foreach ( $lines as $i => $line ) {
		if ( preg_match( '#/\*\s*translators:\s*(.+?)\s*\*/#i', $line, $m ) ) {
			$translator_comments[ $i + 1 ] = trim( $m[1] );
			$translator_comments[ $i + 2 ] = trim( $m[1] );
		}
	}

	// Single-string functions (single-quoted).
	if ( preg_match_all(
		'/(?:' . $single_funcs . ')\s*\'((?:[^\'\\\\]|\\\\.)*)\'(?:\s*,\s*\'([^\']*)\')?\s*\)/s',
		$code,
		$matches,
		PREG_SET_ORDER | PREG_OFFSET_CAPTURE
	) ) {
		foreach ( $matches as $m ) {
			$str = stripcslashes( $m[1][0] );
			$td  = isset( $m[2] ) ? $m[2][0] : '';
			if ( $td !== $domain ) {
				continue;
			}
			$offset  = $m[0][1];
			$linenum = substr_count( $code, "\n", 0, $offset ) + 1;
			$ref     = $relpath . ':' . $linenum;

			$key = $str;
			if ( ! isset( $entries[ $key ] ) ) {
				$entries[ $key ] = [ 'refs' => [], 'comments' => [], 'plural' => null ];
			}
			if ( ! in_array( $ref, $entries[ $key ]['refs'], true ) ) {
				$entries[ $key ]['refs'][] = $ref;
			}
			if ( isset( $translator_comments[ $linenum ] ) && ! in_array( $translator_comments[ $linenum ], $entries[ $key ]['comments'], true ) ) {
				$entries[ $key ]['comments'][] = $translator_comments[ $linenum ];
			}
		}
	}

	// Single-string functions (double-quoted).
	if ( preg_match_all(
		'/(?:' . $single_funcs . ')\s*"((?:[^"\\\\]|\\\\.)*)"\s*,\s*\'([^\']*)\'\s*\)/s',
		$code,
		$matches,
		PREG_SET_ORDER | PREG_OFFSET_CAPTURE
	) ) {
		foreach ( $matches as $m ) {
			$str = stripcslashes( $m[1][0] );
			$td  = $m[2][0];
			if ( $td !== $domain ) {
				continue;
			}
			$offset  = $m[0][1];
			$linenum = substr_count( $code, "\n", 0, $offset ) + 1;
			$ref     = $relpath . ':' . $linenum;

			$key = $str;
			if ( ! isset( $entries[ $key ] ) ) {
				$entries[ $key ] = [ 'refs' => [], 'comments' => [], 'plural' => null ];
			}
			if ( ! in_array( $ref, $entries[ $key ]['refs'], true ) ) {
				$entries[ $key ]['refs'][] = $ref;
			}
			if ( isset( $translator_comments[ $linenum ] ) && ! in_array( $translator_comments[ $linenum ], $entries[ $key ]['comments'], true ) ) {
				$entries[ $key ]['comments'][] = $translator_comments[ $linenum ];
			}
		}
	}

	// _n() plural forms: _n( 'singular', 'plural', $count, 'domain' ).
	if ( preg_match_all(
		'/_n\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'(?:\s*,\s*)\'((?:[^\'\\\\]|\\\\.)*)\'(?:\s*,\s*)[^,]+,\s*\'([^\']*)\'\s*\)/s',
		$code,
		$matches,
		PREG_SET_ORDER | PREG_OFFSET_CAPTURE
	) ) {
		foreach ( $matches as $m ) {
			$singular = stripcslashes( $m[1][0] );
			$plural   = stripcslashes( $m[2][0] );
			$td       = $m[3][0];
			if ( $td !== $domain ) {
				continue;
			}
			$offset  = $m[0][1];
			$linenum = substr_count( $code, "\n", 0, $offset ) + 1;
			$ref     = $relpath . ':' . $linenum;

			$key = $singular . "\x04" . $plural;
			if ( ! isset( $entries[ $key ] ) ) {
				$entries[ $key ] = [ 'refs' => [], 'comments' => [], 'singular' => $singular, 'plural' => $plural ];
			}
			if ( ! in_array( $ref, $entries[ $key ]['refs'], true ) ) {
				$entries[ $key ]['refs'][] = $ref;
			}
			if ( isset( $translator_comments[ $linenum ] ) && ! in_array( $translator_comments[ $linenum ], $entries[ $key ]['comments'], true ) ) {
				$entries[ $key ]['comments'][] = $translator_comments[ $linenum ];
			}
		}
	}
}

// Build .pot output.
$date   = gmdate( 'Y-m-d H:i+0000' );
$output = <<<POT
# Copyright (C) 2026 Yevhen Leonidov
# This file is distributed under the same license as the GLOBUS Debug Control plugin.
msgid ""
msgstr ""
"Project-Id-Version: GLOBUS Debug Control 2.2.5\\n"
"Report-Msgid-Bugs-To: https://globus.studio\\n"
"POT-Creation-Date: {$date}\\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"Language: \\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"

POT;

function pot_escape( string $s ): string {
	return str_replace(
		[ '"', "\n", "\t" ],
		[ '\\"', '\\n', '\\t' ],
		$s
	);
}

function format_msgid( string $prefix, string $str ): string {
	$escaped = pot_escape( $str );
	if ( strlen( $escaped ) > 72 || strpos( $escaped, '\\n' ) !== false ) {
		return $prefix . ' ""' . "\n" . '"' . $escaped . '"';
	}
	return $prefix . ' "' . $escaped . '"';
}

foreach ( $entries as $key => $entry ) {
	$output .= "\n";

	foreach ( $entry['comments'] as $comment ) {
		$output .= '#. translators: ' . $comment . "\n";
	}

	foreach ( $entry['refs'] as $ref ) {
		$output .= '#: ' . $ref . "\n";
	}

	if ( isset( $entry['plural'] ) && $entry['plural'] !== null ) {
		$output .= format_msgid( 'msgid', $entry['singular'] ) . "\n";
		$output .= format_msgid( 'msgid_plural', $entry['plural'] ) . "\n";
		$output .= 'msgstr[0] ""' . "\n";
		$output .= 'msgstr[1] ""' . "\n";
	} else {
		$output .= format_msgid( 'msgid', $key ) . "\n";
		$output .= 'msgstr ""' . "\n";
	}
}

$pot_path = dirname( __DIR__ ) . '/globus-debug-control/languages/globus-debug-control.pot';
file_put_contents( $pot_path, $output );
echo 'Generated ' . count( $entries ) . " entries -> {$pot_path}\n";
