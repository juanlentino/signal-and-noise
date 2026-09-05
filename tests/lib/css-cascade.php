<?php
/**
 * A cascade resolver, small and deliberately conservative.
 *
 * NOT a suite - tests/run.sh globs tests/*.php non-recursively, so nothing in
 * tests/lib/ is swept. tests/dead-css-declarations.php requires this and owns
 * the assertions, including the vacuity floor that keeps its silence honest.
 *
 * @since 12.18.8
 */

if ( ! function_exists( 'sn_css_specificity' ) ) {
	/**
	 * (ids, classes, elements) for one simple selector.
	 *
	 * @param string $sel
	 * @return int[]
	 */
	function sn_css_specificity( $sel ) {
		$ids = preg_match_all( '/#[\w-]+/', $sel );
		$cls = preg_match_all( '/\.[\w-]+/', $sel ) + preg_match_all( '/:(?!:)[\w-]+/', $sel );
		$els = preg_match_all( '/(?:^|[\s>+~])([a-z][\w-]*)/', $sel );

		return array( (int) $ids, (int) $cls, (int) $els );
	}
}

if ( ! function_exists( 'sn_css_expand' ) ) {
	/**
	 * Shorthands expand to longhands AT THE SHORTHAND'S specificity.
	 *
	 * Without this, `margin: 0 0 .5rem 0` and a lower-specificity `margin-top`
	 * look like unrelated properties and the shorthand appears to lose.
	 *
	 * @param string $prop
	 * @param string $value
	 * @return array<string,string>
	 */
	function sn_css_expand( $prop, $value ) {
		if ( 'margin' !== $prop && 'padding' !== $prop ) {
			return array( $prop => $value );
		}
		$imp   = ( false !== strpos( $value, '!important' ) ) ? ' !important' : '';
		$parts = preg_split( '/\s+/', trim( str_replace( '!important', '', $value ) ) );
		$n     = count( $parts );
		if ( 1 === $n ) { $t = $r = $b = $l = $parts[0]; }
		elseif ( 2 === $n ) { $t = $b = $parts[0]; $r = $l = $parts[1]; }
		elseif ( 3 === $n ) { list( $t, $r, $b ) = $parts; $l = $r; }
		elseif ( $n >= 4 ) { list( $t, $r, $b, $l ) = array_slice( $parts, 0, 4 ); }
		else { return array( $prop => $value ); }

		return array(
			$prop . '-top'    => $t . $imp,
			$prop . '-right'  => $r . $imp,
			$prop . '-bottom' => $b . $imp,
			$prop . '-left'   => $l . $imp,
		);
	}
}

if ( ! function_exists( 'sn_css_selector_matches' ) ) {
	/**
	 * Whether a descendant selector matches an element's ancestor chain.
	 *
	 * Returns null - "cannot say" - for anything with an id, attribute,
	 * pseudo-class or combinator. Guessing there is how a resolver invents
	 * findings; the caller skips these rather than assuming either answer.
	 *
	 * @param string $sel
	 * @param array  $chain [ [tag, classes[]], ... ] ending at the element.
	 * @return bool|null
	 */
	function sn_css_selector_matches( $sel, $chain ) {
		if ( preg_match( '/[>+~]/', $sel ) ) {
			return null;
		}
		$parts = preg_split( '/\s+/', trim( $sel ) );
		$i     = count( $chain ) - 1;
		$j     = count( $parts ) - 1;

		$compound = function ( $c, $node ) {
			if ( preg_match( '/[#\[:]/', $c ) ) {
				return null;
			}
			$el = preg_match( '/^([a-z][\w-]*)/', $c, $m ) ? $m[1] : '';
			if ( '' !== $el && $el !== $node[0] ) {
				return false;
			}
			preg_match_all( '/\.([\w-]+)/', $c, $cm );
			return array() === array_diff( $cm[1], $node[1] );
		};

		$r = $compound( $parts[ $j ], $chain[ $i ] );
		if ( true !== $r ) {
			return $r;
		}
		--$j; --$i;
		while ( $j >= 0 ) {
			$found = false;
			while ( $i >= 0 ) {
				$r = $compound( $parts[ $j ], $chain[ $i ] );
				if ( null === $r ) { return null; }
				if ( $r ) { $found = true; --$i; break; }
				--$i;
			}
			if ( ! $found ) { return false; }
			--$j;
		}

		return true;
	}
}

if ( ! function_exists( 'sn_css_harvest_nodes' ) ) {
	/**
	 * Every element carrying an `sn-` class, with its ancestor chain.
	 *
	 * A pattern's chain is seeded with `.wp-block-post-content`: patterns are
	 * inserted into post content and never carry that wrapper themselves, so
	 * without it every post-content-scoped rule is invisible.
	 *
	 * @param string $root Theme root.
	 * @return array[]
	 */
	function sn_css_harvest_nodes( $root ) {
		$out  = array();
		$sets = array(
			array( glob( $root . '/patterns/*.php' ), true,  array( array( 'body', array( 'single-post' ) ), array( 'div', array( 'wp-block-post-content' ) ) ) ),
			array( glob( $root . '/templates/*.html' ), false, array( array( 'body', array() ) ) ),
			array( glob( $root . '/parts/*.html' ), false, array( array( 'body', array() ) ) ),
		);
		$void = array( 'br', 'img', 'hr', 'input', 'meta', 'source', 'path', 'use', 'circle', 'line' );

		foreach ( $sets as $set ) {
			list( $files, $is_php, $seed ) = $set;
			foreach ( (array) $files as $file ) {
				$html = (string) file_get_contents( $file );
				if ( $is_php && false !== strpos( $html, '?>' ) ) {
					$html = substr( $html, strpos( $html, '?>' ) + 2 );
				}
				$html  = preg_replace( '/<!--.*?-->/s', '', $html );
				$stack = array();
				if ( preg_match_all( '/<(\/?)([a-z][\w]*)([^>]*)>/', $html, $tags, PREG_SET_ORDER ) ) {
					foreach ( $tags as $t ) {
						list( , $closing, $tag, $attrs ) = $t;
						if ( '' !== $closing ) {
							if ( $stack && end( $stack )[0] === $tag ) { array_pop( $stack ); }
							continue;
						}
						preg_match_all( '/class="([^"]*)"/', $attrs, $cm );
						$classes = array_values( array_filter( preg_split( '/\s+/', implode( ' ', $cm[1] ) ) ) );
						$out[]   = array(
							'src'     => basename( $file ),
							'tag'     => $tag,
							'classes' => $classes,
							'chain'   => array_merge( $seed, $stack ),
						);
						if ( ! in_array( $tag, $void, true ) && '/' !== substr( rtrim( $attrs ), -1 ) ) {
							$stack[] = array( $tag, $classes );
						}
					}
				}
			}
		}

		return $out;
	}
}

if ( ! function_exists( 'sn_css_harvest_rules' ) ) {
	/**
	 * Every rule in the SCREEN stylesheets, tagged with its media context.
	 *
	 * print.css is excluded: it is enqueued `media='print'`, so it never
	 * competes on screen. Including it reported fifteen false conflicts, every
	 * one of them "beaten by" a print-only 11pt.
	 *
	 * @param string $root
	 * @return array[]
	 */
	function sn_css_harvest_rules( $root ) {
		$rules = array();
		foreach ( (array) glob( $root . '/assets/css/*.css' ) as $sheet ) {
			if ( 'print.css' === basename( $sheet ) ) {
				continue;
			}
			$css = (string) file_get_contents( $sheet );
			$css = preg_replace_callback( '/\/\*.*?\*\//s', function ( $m ) {
				return str_repeat( "\n", substr_count( $m[0], "\n" ) );
			}, $css );

			// Media context by brace depth.
			$stack = array();
			$marks = array();
			if ( preg_match_all( '/@media([^{]*)\{|\{|\}/', $css, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
				foreach ( $mm as $m ) {
					$tok = $m[0][0];
					if ( 0 === strpos( $tok, '@media' ) ) {
						$stack[] = trim( preg_replace( '/\s+/', ' ', $m[1][0] ) );
					} elseif ( '{' === $tok ) {
						$stack[] = null;
					} elseif ( $stack ) {
						array_pop( $stack );
					}
					$marks[] = array( $m[0][1], array_values( array_filter( $stack ) ) );
				}
			}
			$media_at = function ( $pos ) use ( $marks ) {
				$cur = array();
				foreach ( $marks as $mk ) {
					if ( $mk[0] > $pos ) { break; }
					$cur = $mk[1];
				}
				return $cur ? end( $cur ) : 'screen';
			};

			if ( preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $rm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
				foreach ( $rm as $r ) {
					$group = array_values( array_filter( array_map( 'trim', explode( ',', $r[1][0] ) ) ) );
					$decls = array();
					foreach ( explode( ';', $r[2][0] ) as $d ) {
						if ( false === strpos( $d, ':' ) ) { continue; }
						list( $p, $v ) = explode( ':', $d, 2 );
						$decls[ trim( $p ) ] = trim( $v );
					}
					if ( ! $decls ) { continue; }
					$line = substr_count( substr( $css, 0, $r[0][1] ), "\n" ) + 1;
					foreach ( $group as $sel ) {
						$rules[] = array(
							'sel'   => $sel,
							'group' => $group,
							'decls' => $decls,
							'media' => $media_at( $r[0][1] ),
							'sheet' => basename( $sheet ),
							'line'  => $line,
						);
					}
				}
			}
		}

		return $rules;
	}
}

if ( ! function_exists( 'sn_css_dead_declarations' ) ) {
	/**
	 * Declarations on `sn-` components that no element can ever receive.
	 *
	 * @param array[] $nodes
	 * @param array[] $rules
	 * @return array[]
	 */
	function sn_css_dead_declarations( $nodes, $rules ) {
		global $sn_css_examined;
		$sn_css_examined = 0;
		$dead            = array();
		$seen            = array();

		foreach ( $nodes as $node ) {
			$comp = array_values( array_filter( $node['classes'], function ( $c ) { return 0 === strpos( $c, 'sn-' ); } ) );
			if ( ! $comp ) { continue; }
			$chain = array_merge( $node['chain'], array( array( $node['tag'], $node['classes'] ) ) );

			foreach ( $rules as $rule ) {
				if ( true !== sn_css_selector_matches( $rule['sel'], $chain ) ) { continue; }
				preg_match_all( '/\.([\w-]+)/', $rule['sel'], $sm );
				if ( ! array_intersect( $comp, $sm[1] ) ) { continue; }
				$spec = sn_css_specificity( $rule['sel'] );

				foreach ( $rule['decls'] as $prop => $value ) {
					foreach ( sn_css_expand( $prop, $value ) as $pp => $vv ) {
						++$sn_css_examined;
						$mine = array( ( false !== strpos( $vv, '!important' ) ) ? 1 : 0, $spec );
						$best = null;

						foreach ( $rules as $other ) {
							if ( $other['media'] !== $rule['media'] ) { continue; }
							if ( true !== sn_css_selector_matches( $other['sel'], $chain ) ) { continue; }
							$ospec = sn_css_specificity( $other['sel'] );
							foreach ( $other['decls'] as $op => $ov ) {
								foreach ( sn_css_expand( $op, $ov ) as $opp => $ovv ) {
									if ( $opp !== $pp ) { continue; }
									$rank = array( ( false !== strpos( $ovv, '!important' ) ) ? 1 : 0, $ospec );
									if ( null === $best || $rank >= $best[0] ) {
										$best = array( $rank, $ovv, $other['sel'], $other['group'] );
									}
								}
							}
						}
						if ( null === $best ) { continue; }
						if ( $best[0] <= $mine ) { continue; }
						if ( trim( $best[1] ) === trim( $vv ) ) { continue; }
						if ( in_array( $rule['sel'], $best[3], true ) ) { continue; }

						$key = $comp[0] . '|' . $pp . '|' . $rule['media'];
						if ( isset( $seen[ $key ] ) ) { continue; }
						$seen[ $key ] = true;
						$dead[]       = array(
							'class'        => $comp[0],
							'prop'         => $pp,
							'value'        => $vv,
							'media'        => $rule['media'],
							'sheet'        => $rule['sheet'],
							'line'         => $rule['line'],
							'winner'       => $best[2],
							'winner_value' => $best[1],
						);
					}
				}
			}
		}

		return $dead;
	}
}

if ( ! function_exists( 'sn_css_last_examined' ) ) {
	/** How many declarations the last resolve actually compared. */
	function sn_css_last_examined() {
		global $sn_css_examined;
		return (int) $sn_css_examined;
	}
}
