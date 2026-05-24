<?php
/**
 * Standalone fixture tests for inc/abilities-registration.php (theme v9.1.0).
 *
 * Covers all 12 WP 7.0 abilities the theme registers:
 *   - 7 read abilities (design tokens, patterns, template, version,
 *     /notes pillars, reading time, design-system summary)
 *   - 5 generative abilities (page-note summary, pattern suggest,
 *     brand validate, pattern content, voice rewrite)
 *
 * Pattern: matches plugin's tests/health-checks.php — minimal WP +
 * AI-helper stubs, ha_eq / ha_true harness. Assertions continue on
 * failure so each block's PASS/FAIL output is visible.
 *
 * Run from theme root:
 *   php tests/abilities-registration.php
 *
 * Task 1 (this commit) scaffolds the harness only — stubs, helpers,
 * counters. The require_once for the SUT and the registration baseline
 * tests are added in Task 2 once inc/abilities-registration.php exists.
 *
 * @since theme v9.1.0
 */

define( 'ABSPATH', '/' );

// --- WP function stubs -------------------------------------------------
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { return $value; }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $u ) { return (string) $u; }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
}
if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() { return true; }
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap = '' ) { return true; }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '/' ) { return 'https://juanlentino.com' . $path; }
}
if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $id ) { return "https://juanlentino.com/?p=$id"; }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $v ) { return json_encode( $v ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $s ) ); }
}

// --- WP_Error + is_wp_error -------------------------------------------
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public $data;
		public function __construct( $c = '', $m = '', $d = array() ) {
			$this->code    = $c;
			$this->message = $m;
			$this->data    = $d;
		}
		public function get_error_code()    { return $this->code; }
		public function get_error_message() { return $this->message; }
		public function get_error_data()    { return $this->data; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $v ) { return $v instanceof WP_Error; }
}

// --- Theme-specific WP stubs ------------------------------------------
// Design tokens.
$GLOBALS['__test_global_settings'] = array(
	'color'      => array(
		'palette' => array(
			array( 'slug' => 'void',     'color' => '#ffffff', 'name' => 'Void' ),
			array( 'slug' => 'asphalt',  'color' => '#f5f5f5', 'name' => 'Asphalt' ),
			array( 'slug' => 'bone',     'color' => '#000000', 'name' => 'Bone' ),
			array( 'slug' => 'blood',    'color' => '#e00404', 'name' => 'Blood' ),
		),
	),
	'typography' => array(
		'fontFamilies' => array(
			array( 'slug' => 'heading', 'name' => 'Heading', 'fontFamily' => 'Bebas Neue' ),
			array( 'slug' => 'body',    'name' => 'Body',    'fontFamily' => 'DM Mono' ),
		),
		'fontSizes'    => array(
			array( 'slug' => 'small',  'size' => '0.8rem',  'name' => 'Small' ),
			array( 'slug' => 'medium', 'size' => '1rem',    'name' => 'Medium' ),
			array( 'slug' => 'large',  'size' => '1.25rem', 'name' => 'Large' ),
		),
	),
	'spacing'    => array(
		'spacingScale' => array( 'steps' => 7 ),
		'spacingSizes' => array(
			array( 'slug' => '40', 'size' => '1rem',   'name' => 'Small' ),
			array( 'slug' => '50', 'size' => '1.5rem', 'name' => 'Medium' ),
		),
	),
);
if ( ! function_exists( 'wp_get_global_settings' ) ) {
	function wp_get_global_settings() { return $GLOBALS['__test_global_settings']; }
}

// Theme metadata.
if ( ! class_exists( 'SN_Test_Theme' ) ) {
	class SN_Test_Theme {
		public function get( $key ) {
			$map = array(
				'Name'    => 'Signal & Noise',
				'Version' => '9.1.0',
			);
			return isset( $map[ $key ] ) ? $map[ $key ] : '';
		}
		public function get_stylesheet() { return 'signal-and-noise'; }
		public function get_template()   { return 'signal-and-noise'; }
	}
}
if ( ! function_exists( 'wp_get_theme' ) ) {
	function wp_get_theme() { return new SN_Test_Theme(); }
}
if ( ! function_exists( 'wp_is_block_theme' ) ) {
	function wp_is_block_theme() { return true; }
}
if ( ! function_exists( 'wp_get_wp_version' ) ) {
	function wp_get_wp_version() { return '7.0.0'; }
}
$GLOBALS['wp_version'] = '7.0.0';

// Block patterns registry stub.
if ( ! class_exists( 'SN_Test_Block_Patterns_Registry' ) ) {
	class SN_Test_Block_Patterns_Registry {
		private static $instance = null;
		public $patterns = array();
		public static function get_instance() {
			if ( null === self::$instance ) { self::$instance = new self(); }
			return self::$instance;
		}
		public function get_all_registered() { return $this->patterns; }
		public function get_registered( $name ) {
			foreach ( $this->patterns as $p ) {
				if ( $p['name'] === $name ) { return $p; }
			}
			return null;
		}
	}
}
if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
	class_alias( 'SN_Test_Block_Patterns_Registry', 'WP_Block_Patterns_Registry' );
}
if ( ! class_exists( 'SN_Test_Block_Pattern_Categories_Registry' ) ) {
	class SN_Test_Block_Pattern_Categories_Registry {
		private static $instance = null;
		public $categories = array();
		public static function get_instance() {
			if ( null === self::$instance ) { self::$instance = new self(); }
			return self::$instance;
		}
		public function get_all_registered() { return $this->categories; }
	}
}
if ( ! class_exists( 'WP_Block_Pattern_Categories_Registry' ) ) {
	class_alias( 'SN_Test_Block_Pattern_Categories_Registry', 'WP_Block_Pattern_Categories_Registry' );
}

// Seed the pattern fixtures used in list-block-patterns + suggest-pattern + generate-pattern-content tests.
WP_Block_Patterns_Registry::get_instance()->patterns = array(
	array(
		'name'           => 'signal-and-noise/hero-dossier',
		'title'          => 'Hero — Dossier',
		'description'    => 'Title block with industrial spec-sheet header.',
		'categories'     => array( 'signal-noise' ),
		'keywords'       => array( 'hero', 'header' ),
		'viewport_width' => 1200,
		'content'        => '<!-- wp:heading -->{{TITLE}}<!-- /wp:heading -->',
	),
	array(
		'name'           => 'signal-and-noise/section-constrained',
		'title'          => 'Section — Constrained',
		'description'    => 'Constrained-width section block.',
		'categories'     => array( 'signal-noise' ),
		'keywords'       => array( 'section' ),
		'viewport_width' => 1200,
		'content'        => '<!-- wp:paragraph -->{{COPY}}<!-- /wp:paragraph -->',
	),
);
WP_Block_Pattern_Categories_Registry::get_instance()->categories = array(
	array( 'name' => 'signal-noise', 'label' => 'Signal & Noise' ),
);

// Posts stub for get-page-notes-pillars + reading-time abilities.
$GLOBALS['__test_posts'] = array();
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id ) {
		$id = (int) $id;
		return isset( $GLOBALS['__test_posts'][ $id ] ) ? (object) $GLOBALS['__test_posts'][ $id ] : null;
	}
}
if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $id ) {
		$p = get_post( $id );
		return $p && isset( $p->$field ) ? $p->$field : '';
	}
}
if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $slug, $output = OBJECT, $post_type = 'page' ) {
		// The theme's pillars use post_type=post; ignore $post_type filter for stub.
		foreach ( $GLOBALS['__test_posts'] as $id => $p ) {
			if ( isset( $p['post_name'] ) && $p['post_name'] === $slug ) {
				return (object) $p;
			}
		}
		return null;
	}
}

// Theme-side reading-time helper stub (the real one lives in inc/page-notes-render.php).
$GLOBALS['__test_reading_times'] = array(
	'provenance/over-detection' => '7 min',
	'provenance/as-substrate'   => '6 min',
);
if ( ! function_exists( 'sn_notes_reading_time_for_slug' ) ) {
	function sn_notes_reading_time_for_slug( $slug ) {
		if ( isset( $GLOBALS['__test_reading_times'][ $slug ] ) ) {
			return $GLOBALS['__test_reading_times'][ $slug ];
		}
		return '5 min';
	}
}

// Template-resolution stubs for get-active-template-structure.
$GLOBALS['__test_block_templates'] = array();
if ( ! function_exists( 'get_block_template' ) ) {
	function get_block_template( $id ) {
		if ( isset( $GLOBALS['__test_block_templates'][ $id ] ) ) {
			return (object) $GLOBALS['__test_block_templates'][ $id ];
		}
		return null;
	}
}
if ( ! function_exists( 'parse_blocks' ) ) {
	function parse_blocks( $content ) {
		// Minimal parser sufficient for tests — looks for `wp:NAME` markers.
		if ( '' === trim( (string) $content ) ) { return array(); }
		preg_match_all( '/<!--\s*wp:([a-z0-9_\-\/]+)/i', (string) $content, $m );
		$blocks = array();
		foreach ( $m[1] as $name ) {
			$blocks[] = array(
				'blockName'   => 'core/' === substr( $name, 0, 5 ) ? $name : ( false === strpos( $name, '/' ) ? 'core/' . $name : $name ),
				'attrs'       => array(),
				'innerBlocks' => array(),
			);
		}
		return $blocks;
	}
}

// AI helper stubs (mirror plugin's tests/health-checks.php pattern).
$GLOBALS['__test_ai_call_count']      = 0;
$GLOBALS['__test_ai_last_prompt']     = '';
$GLOBALS['__test_ai_last_system']     = '';
$GLOBALS['__test_ai_response']        = null; // string | WP_Error | null
$GLOBALS['__test_ai_available']       = true;
$GLOBALS['__test_ai_helper_disabled'] = false;

if ( ! function_exists( 'snt_ai_generate_with_constraints' ) ) {
	function snt_ai_generate_with_constraints( $prompt, $system, $max_tokens = 256 ) {
		$GLOBALS['__test_ai_call_count']++;
		$GLOBALS['__test_ai_last_prompt'] = $prompt;
		$GLOBALS['__test_ai_last_system'] = $system;
		if ( null !== $GLOBALS['__test_ai_response'] ) {
			return $GLOBALS['__test_ai_response'];
		}
		return new WP_Error( 'snt_ai_unavailable', 'no fixture' );
	}
}
if ( ! function_exists( 'snt_ai_extract_post_text' ) ) {
	function snt_ai_extract_post_text( $post_id, $words = 1000 ) {
		$p = get_post( $post_id );
		return $p && isset( $p->post_content ) ? (string) $p->post_content : '';
	}
}

// Abilities API stubs - capture registrations so tests can introspect them.
$GLOBALS['__test_registered_categories'] = array();
$GLOBALS['__test_registered_abilities']  = array();

if ( ! function_exists( 'wp_register_ability_category' ) ) {
	function wp_register_ability_category( $slug, $args ) {
		$GLOBALS['__test_registered_categories'][ $slug ] = $args;
		return true;
	}
}
if ( ! function_exists( 'wp_has_ability_category' ) ) {
	function wp_has_ability_category( $slug ) {
		return isset( $GLOBALS['__test_registered_categories'][ $slug ] );
	}
}
if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( $slug, $args ) {
		$GLOBALS['__test_registered_abilities'][ $slug ] = $args;
		return true;
	}
}

// --- Load the system under test ---------------------------------------
// Placed AFTER all WP + AI stubs so add_action / wp_register_ability
// calls inside the SUT land on the no-op stubs above. Placed BEFORE the
// harness helpers so test blocks below can introspect $GLOBALS state
// the SUT may have seeded at load time.
require_once __DIR__ . '/../inc/abilities-registration.php';

// --- Harness -----------------------------------------------------------
$pass = 0; $fail = 0;
function ha_eq( $expected, $actual, $msg ) {
	global $pass, $fail;
	if ( $expected === $actual ) {
		$pass++;
		echo "  PASS: $msg\n";
	} else {
		$fail++;
		echo "  FAIL: $msg\n    Expected: " . var_export( $expected, true ) . "\n    Actual:   " . var_export( $actual, true ) . "\n";
	}
}
function ha_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  PASS: $msg\n"; } else { $fail++; echo "  FAIL: $msg\n"; }
}

/**
 * Resets all fixture globals between test blocks. Call at top of each
 * test block in Tasks 3-14 to prevent state bleed (e.g. AI call counter,
 * registered-ability cache, post fixtures from a prior block).
 */
function ha_reset() {
	$GLOBALS['__test_global_settings']       = array();
	$GLOBALS['__test_posts']                  = array();
	$GLOBALS['__test_reading_times']          = array();
	$GLOBALS['__test_block_templates']        = array();
	$GLOBALS['__test_ai_response']            = null;
	$GLOBALS['__test_ai_call_count']          = 0;
	$GLOBALS['__test_ai_last_prompt']         = '';
	$GLOBALS['__test_ai_last_system']         = '';
	$GLOBALS['__test_ai_available']           = true;
	$GLOBALS['__test_ai_helper_disabled']     = false;
	$GLOBALS['__test_registered_categories']  = array();
	$GLOBALS['__test_registered_abilities']   = array();
}

// --- Baseline smoke tests (Task 2) -------------------------------------
// Verifies the SUT loads cleanly, categories register defensively (no
// _doing_it_wrong on double-call), and the abilities-registration
// entry point is callable even before Tasks 3-14 populate it.

echo "\nTest: sn_theme_register_ability_categories registers 3 categories\n";
ha_reset();
sn_theme_register_ability_categories();
ha_eq( 3, count( $GLOBALS['__test_registered_categories'] ), 'three categories registered' );
ha_true( isset( $GLOBALS['__test_registered_categories']['diagnostics'] ), 'diagnostics category present' );
ha_true( isset( $GLOBALS['__test_registered_categories']['content'] ), 'content category present' );
ha_true( isset( $GLOBALS['__test_registered_categories']['ai-generation'] ), 'ai-generation category present' );

echo "\nTest: sn_theme_register_ability_categories is idempotent (no double-registration)\n";
ha_reset();
sn_theme_register_ability_categories();
$first_call_categories = $GLOBALS['__test_registered_categories'];
sn_theme_register_ability_categories();
ha_eq( count( $first_call_categories ), count( $GLOBALS['__test_registered_categories'] ), 'second call does not re-register' );
ha_eq( 3, count( $GLOBALS['__test_registered_categories'] ), 'still exactly three categories after second call' );

echo "\nTest: sn_theme_register_abilities is callable\n";
ha_reset();
ha_true( function_exists( 'sn_theme_register_abilities' ), 'sn_theme_register_abilities function defined' );
sn_theme_register_abilities();
ha_true( count( $GLOBALS['__test_registered_abilities'] ) >= 1, 'at least one ability registered (populated incrementally in Tasks 3-14)' );

echo "\nTest: brand-voice constants defined\n";
ha_true( defined( 'SN_THEME_BRAND_VOICE_SYSTEM' ), 'SN_THEME_BRAND_VOICE_SYSTEM defined' );
ha_true( defined( 'SN_THEME_NOTES_VOICE_SYSTEM' ), 'SN_THEME_NOTES_VOICE_SYSTEM defined' );
ha_true( strlen( SN_THEME_BRAND_VOICE_SYSTEM ) > 200, 'brand voice constant has substantive content' );
ha_true( strlen( SN_THEME_NOTES_VOICE_SYSTEM ) > 200, 'notes voice constant has substantive content' );

// --- Per-ability test blocks added in Tasks 3-14 ----------------------

// ─── Test: get-design-tokens ─────────────────────────────────────
echo "\nTest signal-noise/get-design-tokens\n";
ha_reset();
// Re-seed the global settings fixture (ha_reset emptied it).
$GLOBALS['__test_global_settings'] = array(
	'color'      => array(
		'palette' => array(
			array( 'slug' => 'void',    'color' => '#ffffff', 'name' => 'Void' ),
			array( 'slug' => 'asphalt', 'color' => '#f5f5f5', 'name' => 'Asphalt' ),
			array( 'slug' => 'bone',    'color' => '#000000', 'name' => 'Bone' ),
			array( 'slug' => 'blood',   'color' => '#e00404', 'name' => 'Blood' ),
		),
	),
	'typography' => array(
		'fontFamilies' => array(
			array( 'slug' => 'heading', 'name' => 'Heading', 'fontFamily' => 'Bebas Neue' ),
			array( 'slug' => 'body',    'name' => 'Body',    'fontFamily' => 'DM Mono' ),
		),
		'fontSizes'    => array(
			array( 'slug' => 'small',  'size' => '0.8rem',  'name' => 'Small' ),
			array( 'slug' => 'medium', 'size' => '1rem',    'name' => 'Medium' ),
			array( 'slug' => 'large',  'size' => '1.25rem', 'name' => 'Large' ),
		),
	),
	'spacing'    => array(
		'spacingScale' => array( 'steps' => 7 ),
		'spacingSizes' => array(
			array( 'slug' => '40', 'size' => '1rem',   'name' => 'Small' ),
			array( 'slug' => '50', 'size' => '1.5rem', 'name' => 'Medium' ),
		),
	),
);
sn_theme_register_abilities();
ha_true(
	isset( $GLOBALS['__test_registered_abilities']['signal-noise/get-design-tokens'] ),
	'get-design-tokens is registered'
);
$ability = $GLOBALS['__test_registered_abilities']['signal-noise/get-design-tokens'];
ha_eq( 'diagnostics', $ability['category'], 'category is diagnostics' );
ha_true( is_callable( $ability['execute_callback'] ), 'execute_callback is callable' );

$result = call_user_func( $ability['execute_callback'], array() );
ha_true( is_array( $result ), 'returns array (not WP_Error) in happy path' );
ha_true( isset( $result['colors'] ),     'output has colors key' );
ha_true( isset( $result['typography'] ), 'output has typography key' );
ha_true( isset( $result['spacing'] ),    'output has spacing key' );
ha_true( isset( $result['version'] ),    'output has version key' );
ha_eq( '#ffffff', $result['colors']['void'],    'void color flattened from palette' );
ha_eq( '#e00404', $result['colors']['blood'],   'blood color flattened from palette' );
ha_eq( 2, count( $result['typography']['fontFamilies'] ), 'typography.fontFamilies passthrough' );

// ─── Test: list-block-patterns ───────────────────────────────────
echo "\nTest signal-noise/list-block-patterns\n";
ha_reset();
// Re-seed the patterns + categories registry (they live as static
// singletons, so we have to clear + repopulate to isolate this block).
WP_Block_Patterns_Registry::get_instance()->patterns = array(
	array(
		'name'           => 'signal-and-noise/hero-dossier',
		'title'          => 'Hero — Dossier',
		'description'    => 'Title block with industrial spec-sheet header.',
		'categories'     => array( 'signal-noise' ),
		'keywords'       => array( 'hero', 'header' ),
		'viewport_width' => 1200,
		'content'        => '<!-- wp:heading -->{{TITLE}}<!-- /wp:heading -->',
	),
	array(
		'name'           => 'signal-and-noise/section-constrained',
		'title'          => 'Section — Constrained',
		'description'    => 'Constrained-width section block.',
		'categories'     => array( 'signal-noise' ),
		'keywords'       => array( 'section' ),
		'viewport_width' => 1200,
		'content'        => '<!-- wp:paragraph -->{{COPY}}<!-- /wp:paragraph -->',
	),
);
WP_Block_Pattern_Categories_Registry::get_instance()->categories = array(
	array( 'name' => 'signal-noise', 'label' => 'Signal & Noise' ),
);
sn_theme_register_abilities();
ha_true(
	isset( $GLOBALS['__test_registered_abilities']['signal-noise/list-block-patterns'] ),
	'list-block-patterns is registered'
);
$ability = $GLOBALS['__test_registered_abilities']['signal-noise/list-block-patterns'];
ha_eq( 'content', $ability['category'], 'category is content' );

$result = call_user_func( $ability['execute_callback'], array() );
ha_true( is_array( $result ), 'returns array' );
ha_true( isset( $result['patterns'] ) && is_array( $result['patterns'] ), 'has patterns array' );
ha_true( isset( $result['categories'] ) && is_array( $result['categories'] ), 'has categories array' );
ha_eq( 2, count( $result['patterns'] ), 'returns 2 patterns from fixture' );
ha_eq( 'signal-and-noise/hero-dossier', $result['patterns'][0]['name'], 'first pattern name passthrough' );
ha_eq( 'Hero — Dossier', $result['patterns'][0]['title'], 'title preserved' );
ha_eq( 1, count( $result['categories'] ), 'returns 1 category from fixture' );

// Category filter input
$filtered = call_user_func( $ability['execute_callback'], array( 'category' => 'signal-noise' ) );
ha_eq( 2, count( $filtered['patterns'] ), 'category=signal-noise returns both fixture patterns' );

$empty = call_user_func( $ability['execute_callback'], array( 'category' => 'nonexistent-cat' ) );
ha_eq( 0, count( $empty['patterns'] ), 'unmatched category returns 0 patterns' );

// ─── Test: get-active-template-structure ─────────────────────────
echo "\nTest signal-noise/get-active-template-structure\n";
ha_reset();

// Seed a fixture template.
$GLOBALS['__test_block_templates']['signal-and-noise//page'] = array(
	'slug'    => 'page',
	'id'      => 'signal-and-noise//page',
	'content' => '<!-- wp:template-part {"slug":"header"} /--><!-- wp:post-content /--><!-- wp:template-part {"slug":"footer"} /-->',
);
// Seed a fixture post resolving to that template.
$GLOBALS['__test_posts'][42] = array(
	'ID'          => 42,
	'post_type'   => 'page',
	'post_status' => 'publish',
	'post_name'   => 'about',
	'post_title'  => 'About',
);

sn_theme_register_abilities();
ha_true(
	isset( $GLOBALS['__test_registered_abilities']['signal-noise/get-active-template-structure'] ),
	'get-active-template-structure is registered'
);
$ability = $GLOBALS['__test_registered_abilities']['signal-noise/get-active-template-structure'];
ha_eq( 'diagnostics', $ability['category'], 'category is diagnostics' );

$result = call_user_func( $ability['execute_callback'], array( 'post_id' => 42 ) );
ha_true( is_array( $result ), 'returns array' );
ha_eq( 'page', $result['template_slug'], 'resolves template_slug=page' );
ha_true( isset( $result['blocks'] ) && is_array( $result['blocks'] ), 'has blocks array' );
ha_true( count( $result['blocks'] ) >= 1, 'parses at least one block from fixture' );

$result_slug = call_user_func( $ability['execute_callback'], array( 'slug' => 'about', 'post_type' => 'page' ) );
ha_true( is_array( $result_slug ), 'slug input also resolves' );
ha_eq( 'page', $result_slug['template_slug'], 'slug->template_slug=page' );

$missing = call_user_func( $ability['execute_callback'], array( 'post_id' => 9999 ) );
ha_true( is_wp_error( $missing ), 'missing post returns WP_Error' );

// ─── Test: get-theme-version ─────────────────────────────────────
echo "\nTest signal-noise/get-theme-version\n";
ha_reset();
sn_theme_register_abilities();
ha_true(
	isset( $GLOBALS['__test_registered_abilities']['signal-noise/get-theme-version'] ),
	'get-theme-version is registered'
);
$ability = $GLOBALS['__test_registered_abilities']['signal-noise/get-theme-version'];
ha_eq( 'diagnostics', $ability['category'], 'category is diagnostics' );

$result = call_user_func( $ability['execute_callback'], array() );
ha_true( is_array( $result ), 'returns array' );
ha_eq( '9.1.0', $result['theme_version'], 'theme_version from stub' );
ha_eq( 'Signal & Noise', $result['theme_name'], 'theme_name from stub' );
ha_eq( true,  $result['is_block_theme'], 'is_block_theme true' );
ha_eq( true,  $result['supports_fse'],   'supports_fse true' );
ha_eq( '7.0.0', $result['wp_version'],   'wp_version from stub' );
ha_eq( 'signal-and-noise', $result['theme_template'], 'theme_template = stylesheet for non-child' );

// ─── Test: get-page-notes-pillars ────────────────────────────────
echo "\nTest signal-noise/get-page-notes-pillars\n";
ha_reset();

// Re-seed reading times (ha_reset cleared them).
$GLOBALS['__test_reading_times'] = array(
	'provenance/over-detection' => '7 min',
	'provenance/as-substrate'   => '6 min',
);

// Seed posts behind the pillar slugs so url + last_modified resolve.
$GLOBALS['__test_posts'][101] = array(
	'ID'                => 101,
	'post_name'         => 'over-detection',
	'post_title'        => 'Provenance Over Detection',
	'post_modified'     => '2026-03-15 12:00:00',
	'post_modified_gmt' => '2026-03-15 12:00:00',
	'post_type'         => 'post',
	'post_status'       => 'publish',
);
$GLOBALS['__test_posts'][102] = array(
	'ID'                => 102,
	'post_name'         => 'as-substrate',
	'post_title'        => 'Provenance as Substrate',
	'post_modified'     => '2026-05-10 12:00:00',
	'post_modified_gmt' => '2026-05-10 12:00:00',
	'post_type'         => 'post',
	'post_status'       => 'publish',
);

sn_theme_register_abilities();
ha_true(
	isset( $GLOBALS['__test_registered_abilities']['signal-noise/get-page-notes-pillars'] ),
	'get-page-notes-pillars is registered'
);
$ability = $GLOBALS['__test_registered_abilities']['signal-noise/get-page-notes-pillars'];
ha_eq( 'content', $ability['category'], 'category is content' );

$result = call_user_func( $ability['execute_callback'], array() );
ha_true( is_array( $result ),                                'returns array' );
ha_true( isset( $result['pillars'] ),                        'has pillars key' );
ha_eq( 2, count( $result['pillars'] ),                       'returns 2 pillars (project-defined)' );
ha_eq( 'provenance/over-detection', $result['pillars'][0]['slug'], 'pillar 1 slug' );
ha_eq( 'Provenance Over Detection', $result['pillars'][0]['title'], 'pillar 1 title' );
ha_true( false !== strpos( $result['pillars'][0]['url'], '/provenance/over-detection' ), 'pillar 1 url contains slug' );
ha_eq( 'provenance/as-substrate', $result['pillars'][1]['slug'], 'pillar 2 slug' );
ha_true( isset( $result['pillars'][0]['reading_time_minutes'] ), 'reading_time_minutes present' );

// ─── Test: get-reading-time-for-slug ─────────────────────────────
echo "\nTest signal-noise/get-reading-time-for-slug\n";
ha_reset();

// Re-seed reading times (ha_reset cleared them).
$GLOBALS['__test_reading_times'] = array(
	'provenance/over-detection' => '7 min',
	'provenance/as-substrate'   => '6 min',
);

sn_theme_register_abilities();
ha_true(
	isset( $GLOBALS['__test_registered_abilities']['signal-noise/get-reading-time-for-slug'] ),
	'get-reading-time-for-slug is registered'
);
$ability = $GLOBALS['__test_registered_abilities']['signal-noise/get-reading-time-for-slug'];
ha_eq( 'content', $ability['category'], 'category is content' );

$result = call_user_func( $ability['execute_callback'], array( 'slug' => 'provenance/over-detection' ) );
ha_true( is_array( $result ),                          'returns array' );
ha_eq( 'provenance/over-detection', $result['slug'],   'echoes input slug' );
ha_eq( 7, $result['minutes'],                          'parses 7 from "7 min" fixture' );
ha_true( isset( $result['wpm_basis'] ),                'wpm_basis included' );

// Unknown slug returns minutes=0 (fixture stub returns "5 min" default but no real lookup).
$missing = call_user_func( $ability['execute_callback'], array( 'slug' => 'nonexistent' ) );
ha_eq( 'nonexistent', $missing['slug'], 'echoes nonexistent slug' );
ha_true( $missing['minutes'] >= 0, 'minutes is non-negative for unknown slug' );

// ─── Test: get-design-system-summary ─────────────────────────────
echo "\nTest signal-noise/get-design-system-summary\n";
ha_reset();
// Re-seed the global settings fixture (ha_reset emptied it).
$GLOBALS['__test_global_settings'] = array(
	'color'      => array(
		'palette' => array(
			array( 'slug' => 'void',    'color' => '#ffffff', 'name' => 'Void' ),
			array( 'slug' => 'asphalt', 'color' => '#f5f5f5', 'name' => 'Asphalt' ),
			array( 'slug' => 'bone',    'color' => '#000000', 'name' => 'Bone' ),
			array( 'slug' => 'blood',   'color' => '#e00404', 'name' => 'Blood' ),
		),
	),
	'typography' => array(
		'fontFamilies' => array(
			array( 'slug' => 'heading', 'name' => 'Heading', 'fontFamily' => 'Bebas Neue' ),
			array( 'slug' => 'body',    'name' => 'Body',    'fontFamily' => 'DM Mono' ),
		),
		'fontSizes'    => array(
			array( 'slug' => 'small',  'size' => '0.8rem',  'name' => 'Small' ),
			array( 'slug' => 'medium', 'size' => '1rem',    'name' => 'Medium' ),
			array( 'slug' => 'large',  'size' => '1.25rem', 'name' => 'Large' ),
		),
	),
	'spacing'    => array(
		'spacingScale' => array( 'steps' => 7 ),
		'spacingSizes' => array(
			array( 'slug' => '40', 'size' => '1rem',   'name' => 'Small' ),
			array( 'slug' => '50', 'size' => '1.5rem', 'name' => 'Medium' ),
		),
	),
);
sn_theme_register_abilities();
ha_true(
	isset( $GLOBALS['__test_registered_abilities']['signal-noise/get-design-system-summary'] ),
	'get-design-system-summary is registered'
);
$ability = $GLOBALS['__test_registered_abilities']['signal-noise/get-design-system-summary'];
ha_eq( 'diagnostics', $ability['category'], 'category is diagnostics' );

// Default = markdown.
$md = call_user_func( $ability['execute_callback'], array() );
ha_true( is_array( $md ), 'returns array' );
ha_eq( 'markdown', $md['format'], 'default format is markdown' );
ha_true( false !== strpos( $md['summary'], '## Colors' ),     'markdown has Colors heading' );
ha_true( false !== strpos( $md['summary'], '## Typography' ), 'markdown has Typography heading' );
ha_true( false !== strpos( $md['summary'], 'void'   ),        'markdown lists void color slug' );
ha_true( $md['token_estimate'] > 0,                            'token_estimate computed' );

// Compact-text format.
$compact = call_user_func( $ability['execute_callback'], array( 'format' => 'compact-text' ) );
ha_eq( 'compact-text', $compact['format'], 'compact format echoed' );
ha_true( false !== strpos( $compact['summary'], 'colors:' ), 'compact summary leads with colors:' );
ha_true( $compact['token_estimate'] < $md['token_estimate'], 'compact is smaller than markdown' );

// JSON format = passthrough of get-design-tokens.
$json = call_user_func( $ability['execute_callback'], array( 'format' => 'json' ) );
ha_eq( 'json', $json['format'], 'json format echoed' );
$decoded = json_decode( $json['summary'], true );
ha_true( is_array( $decoded ),                'json summary is parseable' );
ha_true( isset( $decoded['colors']['void'] ), 'json contains tokens passthrough' );

// ─── Test: ai-generate-page-note-summary ─────────────────────────
echo "\nTest signal-noise/ai-generate-page-note-summary\n";
ha_reset();

// Seed a post for the summarizer.
$GLOBALS['__test_posts'][200] = array(
	'ID'           => 200,
	'post_title'   => 'Provenance is the substrate',
	'post_content' => 'Music files need fingerprints, not name tags. Provenance is the substrate everything else rides on.',
	'post_type'    => 'post',
	'post_status'  => 'publish',
);

sn_theme_register_abilities();
ha_true(
	isset( $GLOBALS['__test_registered_abilities']['signal-noise/ai-generate-page-note-summary'] ),
	'ai-generate-page-note-summary is registered'
);
$ability = $GLOBALS['__test_registered_abilities']['signal-noise/ai-generate-page-note-summary'];
ha_eq( 'ai-generation', $ability['category'], 'category is ai-generation' );

// Scenario 1: AI helper disabled → ai_helper_unavailable.
$GLOBALS['__test_ai_helper_disabled'] = true;
$err = call_user_func( $ability['execute_callback'], array( 'post_id' => 200 ) );
ha_true( is_wp_error( $err ), 'returns WP_Error when AI helper disabled' );
ha_eq( 'ai_helper_unavailable', $err->code, 'error code is ai_helper_unavailable' );
$GLOBALS['__test_ai_helper_disabled'] = false;

// Scenario 2: happy path.
$GLOBALS['__test_ai_response']     = 'Provenance is the catalog substrate music files inherit when fingerprints replace name tags.';
$GLOBALS['__test_ai_call_count']   = 0;
$result = call_user_func( $ability['execute_callback'], array( 'post_id' => 200, 'max_words' => 25 ) );
ha_true( is_array( $result ),                                 'happy path returns array' );
ha_eq( 200,            $result['post_id'],                    'echoes post_id' );
ha_true( '' !== $result['summary'],                            'summary non-empty' );
ha_eq( 1, $GLOBALS['__test_ai_call_count'],                   'AI was called once' );
ha_true( false !== strpos( $GLOBALS['__test_ai_last_system'], 'Signal & Noise /notes' ), 'system instruction is the notes voice constant' );

// Scenario 3: AI returns WP_Error → propagates.
$GLOBALS['__test_ai_response'] = new WP_Error( 'snt_ai_unavailable', 'no key' );
$prop = call_user_func( $ability['execute_callback'], array( 'post_id' => 200 ) );
ha_true( is_wp_error( $prop ),                  'AI WP_Error propagated' );
ha_eq( 'snt_ai_unavailable', $prop->code,        'propagated code preserved' );

// Scenario 4: missing post → post_not_found.
$GLOBALS['__test_ai_response'] = 'irrelevant';
$gone = call_user_func( $ability['execute_callback'], array( 'post_id' => 99999 ) );
ha_true( is_wp_error( $gone ),                  'missing post → WP_Error' );
ha_eq( 'post_not_found', $gone->code,           'error code post_not_found' );

// ─── Test: ai-suggest-block-pattern ──────────────────────────────
echo "\nTest signal-noise/ai-suggest-block-pattern\n";
ha_reset();
// Re-seed pattern registry singleton (ha_reset doesn't touch it).
WP_Block_Patterns_Registry::get_instance()->patterns = array(
	array(
		'name'           => 'signal-and-noise/hero-dossier',
		'title'          => 'Hero — Dossier',
		'description'    => 'Title block with industrial spec-sheet header.',
		'categories'     => array( 'signal-noise' ),
		'keywords'       => array( 'hero', 'header' ),
		'viewport_width' => 1200,
		'content'        => '<!-- wp:heading -->{{TITLE}}<!-- /wp:heading -->',
	),
	array(
		'name'           => 'signal-and-noise/section-constrained',
		'title'          => 'Section — Constrained',
		'description'    => 'Constrained-width section block.',
		'categories'     => array( 'signal-noise' ),
		'keywords'       => array( 'section' ),
		'viewport_width' => 1200,
		'content'        => '<!-- wp:paragraph -->{{COPY}}<!-- /wp:paragraph -->',
	),
);
WP_Block_Pattern_Categories_Registry::get_instance()->categories = array(
	array( 'name' => 'signal-noise', 'label' => 'Signal & Noise' ),
);
sn_theme_register_abilities();

ha_true(
	isset( $GLOBALS['__test_registered_abilities']['signal-noise/ai-suggest-block-pattern'] ),
	'ai-suggest-block-pattern is registered'
);
$ability = $GLOBALS['__test_registered_abilities']['signal-noise/ai-suggest-block-pattern'];
ha_eq( 'ai-generation', $ability['category'], 'category is ai-generation' );

$draft = 'This is a draft talking about provenance and substrate as the foundation for music files.';

// Helper-unavailable.
$GLOBALS['__test_ai_helper_disabled'] = true;
$err = call_user_func( $ability['execute_callback'], array( 'draft_content' => $draft ) );
ha_true( is_wp_error( $err ),                       'helper unavailable → WP_Error' );
ha_eq( 'ai_helper_unavailable', $err->code,         'ai_helper_unavailable code' );
$GLOBALS['__test_ai_helper_disabled'] = false;

// Happy path — plain JSON.
$GLOBALS['__test_ai_response'] = wp_json_encode( array(
	'suggestions' => array(
		array( 'pattern_name' => 'signal-and-noise/hero-dossier', 'reasoning' => 'Strong header for a manifesto-style draft.', 'confidence' => 'high' ),
		array( 'pattern_name' => 'signal-and-noise/section-constrained', 'reasoning' => 'Body paragraphs fit constrained section.', 'confidence' => 'medium' ),
	),
) );
$result = call_user_func( $ability['execute_callback'], array( 'draft_content' => $draft ) );
ha_true( is_array( $result ),                         'happy path returns array' );
ha_eq( 2, count( $result['suggestions'] ),            'returns 2 suggestions' );
ha_eq( 'signal-and-noise/hero-dossier', $result['suggestions'][0]['pattern_name'], 'first suggestion pattern_name' );
ha_eq( 'high', $result['suggestions'][0]['confidence'], 'confidence preserved' );

// Markdown-fenced JSON (v3.7.0 lesson).
$GLOBALS['__test_ai_response'] = "```json\n" . wp_json_encode( array( 'suggestions' => array(
	array( 'pattern_name' => 'signal-and-noise/hero-dossier', 'reasoning' => 'r', 'confidence' => 'high' ),
) ) ) . "\n```";
$fenced = call_user_func( $ability['execute_callback'], array( 'draft_content' => $draft ) );
ha_true( is_array( $fenced ),                         'fenced JSON parses' );
ha_eq( 1, count( $fenced['suggestions'] ),            'fenced JSON yields 1 suggestion' );

// Malformed JSON → ai_malformed_response.
$GLOBALS['__test_ai_response'] = 'this is not json at all';
$malformed = call_user_func( $ability['execute_callback'], array( 'draft_content' => $draft ) );
ha_true( is_wp_error( $malformed ),                   'malformed → WP_Error' );
ha_eq( 'ai_malformed_response', $malformed->code,     'code is ai_malformed_response' );

// Pattern name not in registry → dropped from suggestions.
$GLOBALS['__test_ai_response'] = wp_json_encode( array(
	'suggestions' => array(
		array( 'pattern_name' => 'signal-and-noise/hero-dossier',   'reasoning' => 'ok',  'confidence' => 'high' ),
		array( 'pattern_name' => 'signal-and-noise/does-not-exist', 'reasoning' => 'bad', 'confidence' => 'low' ),
	),
) );
$filtered = call_user_func( $ability['execute_callback'], array( 'draft_content' => $draft ) );
ha_eq( 1, count( $filtered['suggestions'] ),          'unknown pattern dropped' );
ha_eq( 'signal-and-noise/hero-dossier', $filtered['suggestions'][0]['pattern_name'], 'valid pattern kept' );

// Cap at 3.
$GLOBALS['__test_ai_response'] = wp_json_encode( array(
	'suggestions' => array(
		array( 'pattern_name' => 'signal-and-noise/hero-dossier',   'reasoning' => 'a', 'confidence' => 'high' ),
		array( 'pattern_name' => 'signal-and-noise/section-constrained', 'reasoning' => 'b', 'confidence' => 'medium' ),
		array( 'pattern_name' => 'signal-and-noise/hero-dossier',   'reasoning' => 'c', 'confidence' => 'low' ),
		array( 'pattern_name' => 'signal-and-noise/hero-dossier',   'reasoning' => 'd', 'confidence' => 'low' ),
	),
) );
$capped = call_user_func( $ability['execute_callback'], array( 'draft_content' => $draft ) );
ha_eq( 3, count( $capped['suggestions'] ), 'caps at 3 suggestions' );

// ─── Test: ai-validate-brand-alignment ───────────────────────────
echo "\nTest signal-noise/ai-validate-brand-alignment\n";
ha_reset();
// Re-seed palette so the SUT's internal call to sn_theme_ability_design_tokens()
// has something to hand back as palette context.
$GLOBALS['__test_global_settings'] = array(
	'color'      => array(
		'palette' => array(
			array( 'slug' => 'void',  'color' => '#ffffff', 'name' => 'Void' ),
			array( 'slug' => 'bone',  'color' => '#000000', 'name' => 'Bone' ),
			array( 'slug' => 'blood', 'color' => '#e00404', 'name' => 'Blood' ),
		),
	),
	'typography' => array( 'fontFamilies' => array(), 'fontSizes' => array() ),
	'spacing'    => array( 'spacingScale' => array(), 'spacingSizes' => array() ),
);
sn_theme_register_abilities();

ha_true(
	isset( $GLOBALS['__test_registered_abilities']['signal-noise/ai-validate-brand-alignment'] ),
	'ai-validate-brand-alignment is registered'
);
$ability = $GLOBALS['__test_registered_abilities']['signal-noise/ai-validate-brand-alignment'];
ha_eq( 'ai-generation', $ability['category'], 'category is ai-generation' );

$sample_content = str_repeat( 'This is sample content that needs to be evaluated for brand alignment. ', 4 );

// Helper-unavailable.
$GLOBALS['__test_ai_helper_disabled'] = true;
$err = call_user_func( $ability['execute_callback'], array( 'content' => $sample_content ) );
ha_true( is_wp_error( $err ),                'helper unavailable → WP_Error' );
ha_eq( 'ai_helper_unavailable', $err->code,  'ai_helper_unavailable code' );
$GLOBALS['__test_ai_helper_disabled'] = false;

// Happy path.
$GLOBALS['__test_ai_response'] = wp_json_encode( array(
	'overall_score' => 72,
	'findings' => array(
		array( 'dimension' => 'voice',      'verdict' => 'drift',     'note' => 'Tone is too consumer-friendly.' ),
		array( 'dimension' => 'vocabulary', 'verdict' => 'off-brand', 'note' => "Word 'discover' doesn't fit SN." ),
		array( 'dimension' => 'palette_fit', 'verdict' => 'aligned',  'note' => 'No off-palette color references.' ),
	),
) );
$result = call_user_func( $ability['execute_callback'], array( 'content' => $sample_content ) );
ha_true( is_array( $result ),                       'happy path returns array' );
ha_eq( 72, $result['overall_score'],                'overall_score parsed' );
ha_eq( 3,  count( $result['findings'] ),            '3 findings returned' );
ha_eq( 'voice', $result['findings'][0]['dimension'], 'first finding dimension preserved' );
ha_true( false !== strpos( $GLOBALS['__test_ai_last_system'], 'brutalist' ), 'system uses brand voice constant' );

// Markdown-fenced JSON.
$GLOBALS['__test_ai_response'] = "```json\n" . wp_json_encode( array(
	'overall_score' => 50,
	'findings' => array( array( 'dimension' => 'tone', 'verdict' => 'aligned', 'note' => 'ok' ) ),
) ) . "\n```";
$fenced = call_user_func( $ability['execute_callback'], array( 'content' => $sample_content ) );
ha_eq( 50, $fenced['overall_score'], 'fenced response parses' );

// Malformed JSON.
$GLOBALS['__test_ai_response'] = 'totally not json';
$bad = call_user_func( $ability['execute_callback'], array( 'content' => $sample_content ) );
ha_true( is_wp_error( $bad ),                   'malformed → WP_Error' );
ha_eq( 'ai_malformed_response', $bad->code,     'code is ai_malformed_response' );

// Invalid verdict in finding is sanitized to "drift" (safe default).
$GLOBALS['__test_ai_response'] = wp_json_encode( array(
	'overall_score' => 80,
	'findings' => array(
		array( 'dimension' => 'tone', 'verdict' => 'martian', 'note' => 'invalid verdict' ),
	),
) );
$sanitized = call_user_func( $ability['execute_callback'], array( 'content' => $sample_content ) );
ha_true( in_array( $sanitized['findings'][0]['verdict'], array( 'aligned', 'drift', 'off-brand' ), true ), 'invalid verdict sanitized to allowed enum' );

// overall_score clamped to 0-100.
$GLOBALS['__test_ai_response'] = wp_json_encode( array(
	'overall_score' => 999,
	'findings' => array( array( 'dimension' => 'voice', 'verdict' => 'aligned', 'note' => 'x' ) ),
) );
$clamped = call_user_func( $ability['execute_callback'], array( 'content' => $sample_content ) );
ha_eq( 100, $clamped['overall_score'], 'overall_score clamped to 100' );

// ─── Test: ai-generate-pattern-content ───────────────────────────
echo "\nTest signal-noise/ai-generate-pattern-content\n";
ha_reset();
// Re-seed pattern registry singleton (ha_reset doesn't touch it).
WP_Block_Patterns_Registry::get_instance()->patterns = array(
	array(
		'name'           => 'signal-and-noise/hero-dossier',
		'title'          => 'Hero — Dossier',
		'description'    => 'Title block with industrial spec-sheet header.',
		'categories'     => array( 'signal-noise' ),
		'keywords'       => array( 'hero', 'header' ),
		'viewport_width' => 1200,
		'content'        => '<!-- wp:heading -->{{TITLE}}<!-- /wp:heading -->',
	),
	array(
		'name'           => 'signal-and-noise/section-constrained',
		'title'          => 'Section — Constrained',
		'description'    => 'Constrained-width section block.',
		'categories'     => array( 'signal-noise' ),
		'keywords'       => array( 'section' ),
		'viewport_width' => 1200,
		'content'        => '<!-- wp:paragraph -->{{COPY}}<!-- /wp:paragraph -->',
	),
);
sn_theme_register_abilities();

ha_true(
	isset( $GLOBALS['__test_registered_abilities']['signal-noise/ai-generate-pattern-content'] ),
	'ai-generate-pattern-content is registered'
);
$ability = $GLOBALS['__test_registered_abilities']['signal-noise/ai-generate-pattern-content'];
ha_eq( 'ai-generation', $ability['category'], 'category is ai-generation' );

// Helper-unavailable.
$GLOBALS['__test_ai_helper_disabled'] = true;
$err = call_user_func( $ability['execute_callback'], array( 'pattern_name' => 'signal-and-noise/hero-dossier', 'topic' => 'A test topic.' ) );
ha_true( is_wp_error( $err ),                'helper unavailable → WP_Error' );
ha_eq( 'ai_helper_unavailable', $err->code,  'ai_helper_unavailable code' );
$GLOBALS['__test_ai_helper_disabled'] = false;

// Pattern not in registry → pattern_not_found.
$GLOBALS['__test_ai_response'] = 'irrelevant';
$missing = call_user_func( $ability['execute_callback'], array( 'pattern_name' => 'signal-and-noise/does-not-exist', 'topic' => 'x' ) );
ha_true( is_wp_error( $missing ),                'unknown pattern → WP_Error' );
ha_eq( 'pattern_not_found', $missing->code,      'code is pattern_not_found' );

// Happy path — well-formed block markup.
$GLOBALS['__test_ai_response'] = '<!-- wp:heading --><h2>Provenance over detection</h2><!-- /wp:heading -->';
$result = call_user_func( $ability['execute_callback'], array( 'pattern_name' => 'signal-and-noise/hero-dossier', 'topic' => 'provenance' ) );
ha_true( is_array( $result ),                                  'happy path returns array' );
ha_eq( 'signal-and-noise/hero-dossier', $result['pattern_name'], 'echoes pattern_name' );
ha_true( false !== strpos( $result['block_markup'], 'wp:heading' ), 'block markup contains wp:heading' );
ha_eq( 0, count( $result['warnings'] ),                        'parseable markup → no warnings' );

// Unparseable AI output → returns markup + warnings entry.
$GLOBALS['__test_ai_response'] = 'this is plain text not block markup';
$unparseable = call_user_func( $ability['execute_callback'], array( 'pattern_name' => 'signal-and-noise/hero-dossier', 'topic' => 'x' ) );
ha_true( is_array( $unparseable ),                'unparseable still returns array' );
ha_true( count( $unparseable['warnings'] ) >= 1,  'warnings entry added' );
ha_eq( 'this is plain text not block markup', $unparseable['block_markup'], 'raw output passed through' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
