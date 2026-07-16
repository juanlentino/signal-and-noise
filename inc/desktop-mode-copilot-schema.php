<?php
/**
 * Desktop Mode AI Copilot — tool-schema conformance for the theme's abilities.
 *
 * Desktop Mode 0.9.4+ turned the Copilot's tools into WordPress Abilities and
 * auto-enrols EVERY read-only ability on the site: "No opt-in: register a
 * read-only ability and the assistant can use it." There is no opt-out. Its
 * converter (includes/ai-copilot/search.php) hands each ability's input_schema
 * to the provider RAW, and three constructs that are perfectly valid JSON
 * Schema — and that the Abilities API accepts — are rejected by Anthropic:
 *
 *   1. 'type' => array( 'object', 'null' )   type: Input should be 'object'
 *   2. top-level anyOf / oneOf / allOf       not supported at the top level
 *   3. 'properties' => array()               properties: Input should be an object
 *
 * ONE non-conformant tool 400s the ENTIRE request. Not "that tool is dropped" —
 * Ask AI dies site-wide, with an error naming a tool INDEX that mentions neither
 * abilities, nor this theme, nor the Copilot. See WordPress/desktop-mode#362.
 *
 * The theme declares all three shapes and they are load-bearing: the union is
 * the GET/null run-path, the anyOf on get-active-template-structure means
 * "post_id OR slug", and a no-args ability naturally writes 'properties' =>
 * array() because PHP has no empty-map literal.
 *
 * WHY THE THEME OWNS THIS (v10.42.3)
 *
 * The companion plugin normalizes the whole tool list at the same filter, so
 * these schemas were already safe — but ONLY while that plugin is active. That
 * was an undeclared cross-dependency: deactivate the plugin, or run this theme
 * without it, and Ask AI dies from the theme's own schemas. A theme cannot
 * depend on a plugin to stay compatible with a third plugin. So this file is
 * deliberately self-contained: it does NOT call the plugin's
 * sn_mcp_normalize_schema(). Both filters running is harmless — normalizing is
 * idempotent, so the second pass is a no-op.
 *
 * NOTHING IS WEAKENED. This projects an ability into a TOOL schema; the ability
 * itself is untouched. WP_Ability::execute() still validates against the real
 * schema and permission_callback still gates it, so a stripped anyOf is still
 * enforced server-side — the model is simply told in prose (the description)
 * instead of in schema.
 *
 * @package signal-and-noise
 * @since   theme v10.42.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Project a JSON Schema into the subset the provider's tool validator accepts.
 *
 * Idempotent: a conformant schema goes in and comes out identical.
 *
 * @param mixed $schema An ability input_schema (array), or anything else.
 * @return array A schema safe to hand the provider.
 */
function sn_theme_normalize_copilot_schema( $schema ) {
	if ( ! is_array( $schema ) || empty( $schema ) ) {
		// An ability with no input still needs a valid object schema, and the
		// empty map MUST be an object — (object) array() encodes to {}, whereas
		// array() would encode to [] and trip shape 3 on the way out.
		return array(
			'type'       => 'object',
			'properties' => (object) array(),
		);
	}

	// Shape 1 — the top-level type must be the literal "object", never a union.
	$schema['type'] = 'object';

	// Shape 2 — strip TOP-LEVEL combinators only. A combinator nested inside a
	// property is a real constraint the provider accepts; rewriting those would
	// silently narrow the ability's contract.
	unset( $schema['oneOf'], $schema['allOf'], $schema['anyOf'] );

	// Shape 3 — an empty PHP array encodes to [] and the provider needs {}.
	if ( isset( $schema['properties'] ) && array() === $schema['properties'] ) {
		$schema['properties'] = (object) array();
	}

	return $schema;
}

/**
 * Normalize every tool the Copilot is about to send to the provider.
 *
 * PHP_INT_MAX is load-bearing, and it is the same lesson as the normalizer
 * itself, one level up. Normalizing unconditionally only helps for the tools we
 * SEE, and at a default priority we would see only the tools that existed at
 * that priority. This filter's own docblock invites others to hook it
 * ("injecting synthetic command tools"), so anything registered later would land
 * downstream of us and take the assistant down anyway. We cannot enumerate who
 * else hooks this filter, or at what priority — so don't guess. Run last.
 *
 * This normalizes EVERY tool, not just the theme's own. That is deliberate: one
 * third-party plugin's bad schema kills Ask AI for the whole site, the fix costs
 * a few array ops on a payload already being built, and it weakens nothing. It
 * is exactly the fix proposed upstream in WordPress/desktop-mode#362.
 *
 * NEVER add a "this one already looks fine, skip it" guard here. That guard is
 * what turned one bug into three releases in the companion plugin: it asked "is
 * this one of the wrong shapes I know about?" and skipped everything else, so
 * each unknown shape sailed through and the provider's 400 simply moved to the
 * next tool. The list of unsupported constructs belongs to the provider, not to
 * us, and we only ever learn it one live 400 at a time.
 *
 * @param mixed $tools Provider tool definitions.
 * @return mixed The tool list, every schema conformant.
 */
function sn_theme_normalize_copilot_tools( $tools ) {
	if ( ! is_array( $tools ) ) {
		return $tools;
	}

	foreach ( $tools as $i => $tool ) {
		// Skip anything without an array `parameters`. Never fabricate a schema
		// for a tool that declares none — desktop-mode's converter already
		// supplies its own fallback for that case.
		if ( ! is_array( $tool ) || ! isset( $tool['parameters'] ) || ! is_array( $tool['parameters'] ) ) {
			continue;
		}
		$tools[ $i ]['parameters'] = sn_theme_normalize_copilot_schema( $tool['parameters'] );
	}

	return $tools;
}
add_filter( 'desktop_mode_ai_tools', 'sn_theme_normalize_copilot_tools', PHP_INT_MAX );
