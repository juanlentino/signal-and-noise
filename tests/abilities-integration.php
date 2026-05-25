<?php
/**
 * AI-invocation integration tests for theme abilities (v9.1.2).
 *
 * Where tests/abilities-registration.php exercises individual ability
 * execute_callbacks DIRECTLY (call_user_func on the registered callback),
 * this file exercises the FULL Abilities API dispatch path:
 *
 *     wp_get_ability( $slug )->execute( $args )
 *
 * That path is what real AI callers (the WP 7.0 AI Client, the desktop-mode
 * Command Palette, the abilities REST controller) hit. It runs:
 *
 *   1. permission_callback (auth gating)
 *   2. input_schema validation (required + enum)
 *   3. execute_callback (the actual implementation)
 *
 * Steps 1+2 are the contract surface this file pins down — registration
 * tests already cover step 3's output shape.
 *
 * Mocking strategy: stub WP_Ability + wp_get_ability locally so the tests
 * run identically whether or not WP 7.0's Abilities API is installed.
 * Per-cap flipping via $GLOBALS['__test_user_caps'] lets each test
 * scenario simulate a different effective user.
 *
 * Run: php tests/abilities-integration.php
 *
 * @since theme v9.1.3
 */

define( 'ABSPATH', '/' );

// ─── WP function stubs ───────────────────────────────────────────────
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { return $value; }
}
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $s ) { return (string) $s; } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $s ) { return (string) $s; } }
if ( ! function_exists( 'esc_url' ) )  { function esc_url( $u )  { return (string) $u; } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
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
if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() { return true; }
}

// ─── Capability stub ────────────────────────────────────────────────
$GLOBALS['__test_user_caps'] = array(
	'read'           => true,
	'edit_posts'     => true,
	'manage_options' => true,
);
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap = '' ) {
		return ! empty( $GLOBALS['__test_user_caps'][ $cap ] );
	}
}

// ─── WP_Error + is_wp_error ─────────────────────────────────────────
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

// ─── Theme-side WP fixtures ─────────────────────────────────────────
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
		'fontSizes' => array(
			array( 'slug' => 'small',  'size' => '0.8rem',  'name' => 'Small' ),
			array( 'slug' => 'medium', 'size' => '1rem',    'name' => 'Medium' ),
		),
	),
	'spacing'    => array(
		'spacingScale' => array( 'steps' => 7 ),
		'spacingSizes' => array(
			array( 'slug' => '40', 'size' => '1rem', 'name' => 'Small' ),
		),
	),
);
if ( ! function_exists( 'wp_get_global_settings' ) ) {
	function wp_get_global_settings() { return $GLOBALS['__test_global_settings']; }
}

if ( ! class_exists( 'SN_Test_Theme' ) ) {
	class SN_Test_Theme {
		public function get( $key ) {
			$map = array( 'Name' => 'Signal & Noise', 'Version' => '9.1.2' );
			return isset( $map[ $key ] ) ? $map[ $key ] : '';
		}
		public function get_stylesheet() { return 'signal-and-noise'; }
		public function get_template()   { return 'signal-and-noise'; }
	}
}
if ( ! function_exists( 'wp_get_theme' ) )      { function wp_get_theme() { return new SN_Test_Theme(); } }
if ( ! function_exists( 'wp_is_block_theme' ) ) { function wp_is_block_theme() { return true; } }
if ( ! function_exists( 'wp_get_wp_version' ) ) { function wp_get_wp_version() { return '7.0.0'; } }
$GLOBALS['wp_version'] = '7.0.0';

// Block patterns registry stubs.
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

// Seed pattern fixtures.
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

// Posts stub.
$GLOBALS['__test_posts'] = array();
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id ) {
		$id = (int) $id;
		return isset( $GLOBALS['__test_posts'][ $id ] ) ? (object) $GLOBALS['__test_posts'][ $id ] : null;
	}
}
if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $slug, $output = OBJECT, $post_type = 'page' ) {
		foreach ( $GLOBALS['__test_posts'] as $id => $p ) {
			if ( isset( $p['post_name'] ) && $p['post_name'] === $slug ) {
				return (object) $p;
			}
		}
		return null;
	}
}
$GLOBALS['__test_reading_times'] = array(
	'provenance/over-detection' => '7 min',
	'provenance/as-substrate'   => '6 min',
);
if ( ! function_exists( 'sn_notes_reading_time_for_slug' ) ) {
	function sn_notes_reading_time_for_slug( $slug ) {
		return isset( $GLOBALS['__test_reading_times'][ $slug ] )
			? $GLOBALS['__test_reading_times'][ $slug ]
			: '5 min';
	}
}

// Template-resolution stub.
$GLOBALS['__test_block_templates'] = array(
	'signal-and-noise//page' => array(
		'slug'    => 'page',
		'id'      => 'signal-and-noise//page',
		'content' => '<!-- wp:template-part {"slug":"header"} /--><!-- wp:post-content /-->',
	),
);
if ( ! function_exists( 'get_block_template' ) ) {
	function get_block_template( $id ) {
		return isset( $GLOBALS['__test_block_templates'][ $id ] )
			? (object) $GLOBALS['__test_block_templates'][ $id ]
			: null;
	}
}
if ( ! function_exists( 'parse_blocks' ) ) {
	function parse_blocks( $content ) {
		if ( '' === trim( (string) $content ) ) { return array(); }
		preg_match_all( '/<!--\s*wp:([a-z0-9_\-\/]+)/i', (string) $content, $m );
		$blocks = array();
		foreach ( $m[1] as $name ) {
			$blocks[] = array(
				'blockName'   => false === strpos( $name, '/' ) ? 'core/' . $name : $name,
				'attrs'       => array(),
				'innerBlocks' => array(),
			);
		}
		return $blocks;
	}
}

// AI helper stubs.
$GLOBALS['__test_ai_response']        = null;
$GLOBALS['__test_ai_call_count']      = 0;
$GLOBALS['__test_ai_last_prompt']     = '';
$GLOBALS['__test_ai_last_system']     = '';
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

// ─── Abilities API stubs ────────────────────────────────────────────
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
	function wp_register_ability( $name, $args ) {
		$GLOBALS['__test_registered_abilities'][ $name ] = $args;
		return true;
	}
}

/**
 * Test-only WP_Ability stub.
 *
 * Models the actual WP_Ability::execute() contract from upstream:
 * https://github.com/WordPress/abilities-api — runs permission_callback,
 * then enforces input_schema required + enum, then dispatches the
 * execute_callback. Matches the real dispatch sequence so test outcomes
 * mirror what AI callers will see in production.
 */
if ( ! class_exists( 'WP_Ability' ) ) {
	class WP_Ability {
		public $name;
		public $config;
		public function __construct( $name, $config ) {
			$this->name   = $name;
			$this->config = $config;
		}
		public function execute( $input = null ) {
			// Step 1: permission gate.
			if ( isset( $this->config['permission_callback'] ) ) {
				$allowed = call_user_func( $this->config['permission_callback'], $input );
				if ( is_wp_error( $allowed ) ) {
					return $allowed;
				}
				if ( ! $allowed ) {
					return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', array( 'status' => 403 ) );
				}
			}
			// Step 2: required field validation.
			if ( isset( $this->config['input_schema']['required'] ) ) {
				foreach ( (array) $this->config['input_schema']['required'] as $required_key ) {
					if ( ! is_array( $input ) || ! isset( $input[ $required_key ] ) ) {
						return new WP_Error( 'rest_invalid_param', "Missing required: $required_key", array( 'status' => 400 ) );
					}
				}
			}
			// Step 3: enum constraint.
			if ( isset( $this->config['input_schema']['properties'] ) && is_array( $input ) ) {
				foreach ( $this->config['input_schema']['properties'] as $key => $schema ) {
					if ( isset( $schema['enum'] ) && isset( $input[ $key ] ) ) {
						if ( ! in_array( $input[ $key ], $schema['enum'], true ) ) {
							return new WP_Error( 'rest_invalid_param', "Invalid enum for $key", array( 'status' => 400 ) );
						}
					}
				}
			}
			// Step 4: execute.
			return call_user_func( $this->config['execute_callback'], $input );
		}
	}
}
if ( ! function_exists( 'wp_get_ability' ) ) {
	function wp_get_ability( $name ) {
		if ( ! isset( $GLOBALS['__test_registered_abilities'][ $name ] ) ) {
			return null;
		}
		return new WP_Ability( $name, $GLOBALS['__test_registered_abilities'][ $name ] );
	}
}

// ─── Load the SUT ───────────────────────────────────────────────────
require_once __DIR__ . '/../inc/abilities-registration.php';

// Trigger registration (the action stubs above are no-ops, so call directly).
sn_theme_register_ability_categories();
sn_theme_register_abilities();

// Seed test posts for AI page-note summary.
$GLOBALS['__test_posts'][200] = array(
	'ID'           => 200,
	'post_title'   => 'Provenance is the substrate',
	'post_content' => 'Music files need fingerprints, not name tags. Provenance is the substrate everything else rides on.',
	'post_type'    => 'post',
	'post_status'  => 'publish',
);
$GLOBALS['__test_posts'][42] = array(
	'ID'         => 42,
	'post_type'  => 'page',
	'post_name'  => 'about',
	'post_title' => 'About',
);

// ─── Harness ─────────────────────────────────────────────────────────
$pass = 0; $fail = 0;
function ap_eq( $expected, $actual, $msg ) {
	global $pass, $fail;
	if ( $expected === $actual ) { $pass++; echo "  PASS: $msg\n"; }
	else { $fail++; echo "  FAIL: $msg\n    Expected: " . var_export( $expected, true ) . "\n    Actual:   " . var_export( $actual, true ) . "\n"; }
}
function ap_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  PASS: $msg\n"; }
	else { $fail++; echo "  FAIL: $msg\n"; }
}
function ap_reset_caps() {
	$GLOBALS['__test_user_caps'] = array(
		'read'           => true,
		'edit_posts'     => true,
		'manage_options' => true,
	);
}
function ap_reset_ai() {
	$GLOBALS['__test_ai_response']        = null;
	$GLOBALS['__test_ai_call_count']      = 0;
	$GLOBALS['__test_ai_helper_disabled'] = false;
}

// ════════════════════════════════════════════════════════════════════
// Category: wp_get_ability() dispatch fundamentals
// ════════════════════════════════════════════════════════════════════

echo "\nCategory: dispatch via wp_get_ability()\n";

// Non-existent slug returns null.
ap_true( null === wp_get_ability( 'signal-and-noise/does-not-exist' ), 'wp_get_ability returns null for unknown slug' );

// All 12 abilities are registered.
$expected_abilities = array(
	'signal-and-noise/get-design-tokens',
	'signal-and-noise/list-block-patterns',
	'signal-and-noise/get-active-template-structure',
	'signal-and-noise/get-theme-version',
	'signal-and-noise/get-page-notes-pillars',
	'signal-and-noise/get-reading-time-for-slug',
	'signal-and-noise/get-design-system-summary',
	'signal-and-noise/ai-generate-page-note-summary',
	'signal-and-noise/ai-suggest-block-pattern',
	'signal-and-noise/ai-validate-brand-alignment',
	'signal-and-noise/ai-generate-pattern-content',
	'signal-and-noise/ai-rewrite-in-brand-voice',
);
foreach ( $expected_abilities as $slug ) {
	ap_true( null !== wp_get_ability( $slug ), "ability registered: $slug" );
}

// ════════════════════════════════════════════════════════════════════
// Category: Read abilities — happy path via execute()
// ════════════════════════════════════════════════════════════════════

echo "\nCategory: read abilities — happy path via execute()\n";

// get-design-tokens
ap_reset_caps();
$out = wp_get_ability( 'signal-and-noise/get-design-tokens' )->execute( array() );
ap_true( is_array( $out ), 'get-design-tokens: execute returns array' );
ap_true( isset( $out['colors']['blood'] ) && '#e00404' === $out['colors']['blood'], 'get-design-tokens: blood color in output' );
ap_true( isset( $out['typography'], $out['spacing'], $out['version'] ), 'get-design-tokens: required keys present per schema' );

// list-block-patterns
$out = wp_get_ability( 'signal-and-noise/list-block-patterns' )->execute( array() );
ap_true( is_array( $out ) && isset( $out['patterns'], $out['categories'] ), 'list-block-patterns: required keys present' );
ap_eq( 2, count( $out['patterns'] ), 'list-block-patterns: returns 2 fixture patterns' );

// get-active-template-structure (by post_id)
$out = wp_get_ability( 'signal-and-noise/get-active-template-structure' )->execute( array( 'post_id' => 42 ) );
ap_true( is_array( $out ) && isset( $out['template_slug'], $out['blocks'] ), 'get-active-template-structure: required keys present' );
ap_eq( 'page', $out['template_slug'], 'get-active-template-structure: template_slug=page' );

// get-theme-version
$out = wp_get_ability( 'signal-and-noise/get-theme-version' )->execute( array() );
ap_true( is_array( $out ), 'get-theme-version: returns array' );
ap_eq( '9.1.2', $out['theme_version'], 'get-theme-version: theme_version from stub' );
ap_eq( true, $out['is_block_theme'], 'get-theme-version: is_block_theme true' );

// get-page-notes-pillars
$out = wp_get_ability( 'signal-and-noise/get-page-notes-pillars' )->execute( array() );
ap_true( isset( $out['pillars'] ) && count( $out['pillars'] ) === 2, 'get-page-notes-pillars: 2 pillars' );

// get-reading-time-for-slug
$out = wp_get_ability( 'signal-and-noise/get-reading-time-for-slug' )->execute( array( 'slug' => 'provenance/over-detection' ) );
ap_true( is_array( $out ) && isset( $out['minutes'] ), 'get-reading-time-for-slug: has minutes' );
ap_eq( 7, $out['minutes'], 'get-reading-time-for-slug: parses "7 min"' );

// get-design-system-summary
$out = wp_get_ability( 'signal-and-noise/get-design-system-summary' )->execute( array() );
ap_true( is_array( $out ) && isset( $out['format'], $out['summary'], $out['token_estimate'] ), 'get-design-system-summary: required keys present' );
ap_eq( 'markdown', $out['format'], 'get-design-system-summary: default format is markdown' );

// ════════════════════════════════════════════════════════════════════
// Category: read abilities — capability gating
// ════════════════════════════════════════════════════════════════════

echo "\nCategory: read abilities — capability denial\n";

// Anonymous-visitor sim: no caps.
$GLOBALS['__test_user_caps'] = array();

$denied_read_abilities = array(
	'signal-and-noise/get-design-tokens',
	'signal-and-noise/list-block-patterns',
	'signal-and-noise/get-theme-version',
	'signal-and-noise/get-page-notes-pillars',
	'signal-and-noise/get-design-system-summary',
);
foreach ( $denied_read_abilities as $slug ) {
	$res = wp_get_ability( $slug )->execute( array() );
	ap_true( is_wp_error( $res ), "read denial: $slug → WP_Error when 'read' missing" );
	ap_eq( 'rest_forbidden', $res->get_error_code(), "read denial: $slug error code is rest_forbidden" );
}

ap_reset_caps();

// ════════════════════════════════════════════════════════════════════
// Category: read abilities (with input) — validation
// ════════════════════════════════════════════════════════════════════

echo "\nCategory: read abilities (with input) — validation\n";

// get-reading-time-for-slug: missing required slug → WP_Error
$res = wp_get_ability( 'signal-and-noise/get-reading-time-for-slug' )->execute( array() );
ap_true( is_wp_error( $res ), 'get-reading-time-for-slug: missing slug → WP_Error' );
ap_eq( 'rest_invalid_param', $res->get_error_code(), 'get-reading-time-for-slug: code rest_invalid_param' );

// get-reading-time-for-slug: empty string slug → the execute_callback's own invalid_input guard fires
$res = wp_get_ability( 'signal-and-noise/get-reading-time-for-slug' )->execute( array( 'slug' => '' ) );
ap_true( is_wp_error( $res ), 'get-reading-time-for-slug: empty slug → WP_Error from callback guard' );
ap_eq( 'invalid_input', $res->get_error_code(), 'get-reading-time-for-slug: callback returns invalid_input for empty slug' );

// get-design-system-summary: invalid enum value for format → the SUT's callback gracefully falls back to markdown
// (the harness-level enum check fires first for the strict path, but the SUT also has a defense-in-depth fallback —
// we test what the dispatch contract sees first: the rest_invalid_param error).
$res = wp_get_ability( 'signal-and-noise/get-design-system-summary' )->execute( array( 'format' => 'martian' ) );
ap_true( is_wp_error( $res ), 'get-design-system-summary: invalid enum → WP_Error' );
ap_eq( 'rest_invalid_param', $res->get_error_code(), 'get-design-system-summary: invalid enum code' );

// get-active-template-structure: post_id not found → callback returns post_not_found (passes harness validation)
$res = wp_get_ability( 'signal-and-noise/get-active-template-structure' )->execute( array( 'post_id' => 99999 ) );
ap_true( is_wp_error( $res ), 'get-active-template-structure: non-existent post → WP_Error' );
ap_eq( 'post_not_found', $res->get_error_code(), 'get-active-template-structure: post_not_found code' );

// get-active-template-structure: by slug (resolves)
$res = wp_get_ability( 'signal-and-noise/get-active-template-structure' )->execute( array( 'slug' => 'about', 'post_type' => 'page' ) );
ap_true( is_array( $res ) && 'page' === $res['template_slug'], 'get-active-template-structure: slug input resolves' );

// ════════════════════════════════════════════════════════════════════
// Category: generative AI abilities — gating + validation
// ════════════════════════════════════════════════════════════════════

echo "\nCategory: generative AI abilities — gating + validation\n";

// AI ability rejected when user lacks edit_posts cap.
$GLOBALS['__test_user_caps'] = array( 'read' => true ); // subscriber-shaped
ap_reset_ai();
$GLOBALS['__test_ai_response'] = 'Provenance is the substrate; fingerprints are the proof.';

$res = wp_get_ability( 'signal-and-noise/ai-generate-page-note-summary' )->execute( array( 'post_id' => 200 ) );
ap_true( is_wp_error( $res ), 'ai-generate-page-note-summary: subscriber denied' );
ap_eq( 'rest_forbidden', $res->get_error_code(), 'ai-generate-page-note-summary: rest_forbidden for subscriber' );

ap_reset_caps();

// Missing required field across the 5 generative abilities.
$generative_required = array(
	'signal-and-noise/ai-generate-page-note-summary'  => 'post_id',
	'signal-and-noise/ai-suggest-block-pattern'       => 'draft_content',
	'signal-and-noise/ai-validate-brand-alignment'    => 'content',
	'signal-and-noise/ai-generate-pattern-content'    => 'pattern_name', // also requires topic
	'signal-and-noise/ai-rewrite-in-brand-voice'      => 'source_text',
);
foreach ( $generative_required as $slug => $required_key ) {
	$res = wp_get_ability( $slug )->execute( array() );
	ap_true( is_wp_error( $res ), "$slug: missing $required_key → WP_Error" );
	ap_eq( 'rest_invalid_param', $res->get_error_code(), "$slug: missing required code is rest_invalid_param" );
}

// Invalid enum on intensity (ai-rewrite-in-brand-voice).
$res = wp_get_ability( 'signal-and-noise/ai-rewrite-in-brand-voice' )->execute( array(
	'source_text' => str_repeat( 'long enough text to pass minLength check. ', 3 ),
	'intensity'   => 'nuclear',
) );
ap_true( is_wp_error( $res ), 'ai-rewrite-in-brand-voice: invalid intensity → WP_Error' );
ap_eq( 'rest_invalid_param', $res->get_error_code(), 'ai-rewrite-in-brand-voice: code rest_invalid_param' );

// Invalid enum on content_type (ai-validate-brand-alignment).
$res = wp_get_ability( 'signal-and-noise/ai-validate-brand-alignment' )->execute( array(
	'content'      => str_repeat( 'pad ', 25 ),
	'content_type' => 'broken',
) );
ap_true( is_wp_error( $res ), 'ai-validate-brand-alignment: invalid content_type → WP_Error' );

// Invalid enum on tone_hint (ai-generate-pattern-content).
$res = wp_get_ability( 'signal-and-noise/ai-generate-pattern-content' )->execute( array(
	'pattern_name' => 'signal-and-noise/hero-dossier',
	'topic'        => 'provenance',
	'tone_hint'    => 'shouty',
) );
ap_true( is_wp_error( $res ), 'ai-generate-pattern-content: invalid tone_hint → WP_Error' );

// ════════════════════════════════════════════════════════════════════
// Category: generative AI abilities — happy path through execute()
// ════════════════════════════════════════════════════════════════════

echo "\nCategory: generative AI abilities — happy path via execute()\n";

ap_reset_caps();

// ai-generate-page-note-summary: AI returns text → schema-conformant output.
ap_reset_ai();
$GLOBALS['__test_ai_response'] = 'Provenance is the substrate; fingerprints are the proof.';
$out = wp_get_ability( 'signal-and-noise/ai-generate-page-note-summary' )->execute( array( 'post_id' => 200 ) );
ap_true( is_array( $out ), 'ai-generate-page-note-summary: happy path returns array' );
ap_true( isset( $out['summary'], $out['post_id'] ), 'ai-generate-page-note-summary: required output keys' );
ap_eq( 200, $out['post_id'], 'ai-generate-page-note-summary: echoes post_id' );
ap_eq( 1, $GLOBALS['__test_ai_call_count'], 'ai-generate-page-note-summary: AI called once' );

// ai-suggest-block-pattern: AI returns valid JSON → suggestions array.
ap_reset_ai();
$GLOBALS['__test_ai_response'] = wp_json_encode( array(
	'suggestions' => array(
		array( 'pattern_name' => 'signal-and-noise/hero-dossier', 'reasoning' => 'strong header', 'confidence' => 'high' ),
	),
) );
$out = wp_get_ability( 'signal-and-noise/ai-suggest-block-pattern' )->execute( array(
	'draft_content' => 'A draft about provenance and substrate as foundation for music files.',
) );
ap_true( is_array( $out ) && isset( $out['suggestions'] ), 'ai-suggest-block-pattern: required output keys' );
ap_eq( 1, count( $out['suggestions'] ), 'ai-suggest-block-pattern: 1 suggestion' );
ap_eq( 'signal-and-noise/hero-dossier', $out['suggestions'][0]['pattern_name'], 'ai-suggest-block-pattern: pattern_name passthrough' );

// ai-validate-brand-alignment: AI returns JSON → score + findings.
ap_reset_ai();
$GLOBALS['__test_ai_response'] = wp_json_encode( array(
	'overall_score' => 72,
	'findings'      => array(
		array( 'dimension' => 'voice', 'verdict' => 'drift', 'note' => 'too marketing-flavored' ),
	),
) );
$out = wp_get_ability( 'signal-and-noise/ai-validate-brand-alignment' )->execute( array(
	'content' => str_repeat( 'Sample copy for brand alignment evaluation. ', 4 ),
) );
ap_true( is_array( $out ) && isset( $out['overall_score'], $out['findings'] ), 'ai-validate-brand-alignment: required output keys' );
ap_eq( 72, $out['overall_score'], 'ai-validate-brand-alignment: score parsed' );

// ai-generate-pattern-content: AI returns block markup.
ap_reset_ai();
$GLOBALS['__test_ai_response'] = '<!-- wp:heading --><h2>Provenance over detection</h2><!-- /wp:heading -->';
$out = wp_get_ability( 'signal-and-noise/ai-generate-pattern-content' )->execute( array(
	'pattern_name' => 'signal-and-noise/hero-dossier',
	'topic'        => 'provenance',
) );
ap_true( is_array( $out ) && isset( $out['block_markup'], $out['pattern_name'] ), 'ai-generate-pattern-content: required output keys' );
ap_eq( 'signal-and-noise/hero-dossier', $out['pattern_name'], 'ai-generate-pattern-content: pattern_name echoed' );

// ai-rewrite-in-brand-voice: AI returns JSON → rewritten + summary.
ap_reset_ai();
$GLOBALS['__test_ai_response'] = wp_json_encode( array(
	'rewritten_text'     => 'Audio fingerprinting establishes provenance for music files.',
	'summary_of_changes' => 'Stripped marketing verbs.',
) );
$out = wp_get_ability( 'signal-and-noise/ai-rewrite-in-brand-voice' )->execute( array(
	'source_text' => 'Discover the amazing world of audio fingerprinting unlock provenance now.',
	'intensity'   => 'medium',
) );
ap_true( is_array( $out ) && isset( $out['rewritten_text'], $out['summary_of_changes'] ), 'ai-rewrite-in-brand-voice: required output keys' );

// ════════════════════════════════════════════════════════════════════
// Category: generative AI abilities — plugin AI helper unavailable
// ════════════════════════════════════════════════════════════════════

echo "\nCategory: generative AI abilities — plugin AI helper unavailable\n";

$GLOBALS['__test_ai_helper_disabled'] = true;
$ai_abilities = array(
	'signal-and-noise/ai-generate-page-note-summary' => array( 'post_id' => 200 ),
	'signal-and-noise/ai-suggest-block-pattern'      => array( 'draft_content' => str_repeat( 'pad ', 10 ) ),
	'signal-and-noise/ai-validate-brand-alignment'   => array( 'content' => str_repeat( 'pad ', 25 ) ),
	'signal-and-noise/ai-generate-pattern-content'   => array( 'pattern_name' => 'signal-and-noise/hero-dossier', 'topic' => 'x' ),
	'signal-and-noise/ai-rewrite-in-brand-voice'     => array( 'source_text' => str_repeat( 'pad ', 10 ) ),
);
foreach ( $ai_abilities as $slug => $args ) {
	$res = wp_get_ability( $slug )->execute( $args );
	ap_true( is_wp_error( $res ), "$slug: helper-disabled → WP_Error" );
	ap_eq( 'ai_helper_unavailable', $res->get_error_code(), "$slug: ai_helper_unavailable code" );
}
$GLOBALS['__test_ai_helper_disabled'] = false;

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
