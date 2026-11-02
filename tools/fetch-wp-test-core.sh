#!/usr/bin/env bash
#
# Fetch the WordPress core files the standalone sweep needs, into a target
# wp-includes directory. ONE list, used by CI (both lanes in ci.yml) and by hand.
#
#   html-api/          WP_HTML_Tag_Processor and its six helpers (#383): the
#                      rewrite pins (article-toc, note-link-previews,
#                      blocks-view-transitions, the Spotify oEmbed filter).
#   class-wp-token-map.php   needed by the HTML API's decoder.
#   interactivity-api/ core's server-side directives processor (#384), for
#                      tests/theme-toggle-directives.php.
#   speculative-loading.php, class-wp-speculation-rules.php,
#   class-wp-url-pattern-prefixer.php   core's speculation rules, for
#                      tests/speculation.php (the rules core prints).
#
# The files come from the wordpress-develop branch the theme runs against.
# Bump WP_REF here when the site moves to a new core branch.
#
# Usage:  bash tools/fetch-wp-test-core.sh <dest-wp-includes-dir>
#         export SNT_WP_HTML_API=<dest-wp-includes-dir>/html-api
#         bash tests/run.sh
#
set -euo pipefail

WP_REF="${WP_REF:-7.1}"
dest="${1:?usage: fetch-wp-test-core.sh <dest-wp-includes-dir>}"
base="https://raw.githubusercontent.com/WordPress/wordpress-develop/${WP_REF}/src/wp-includes"

mkdir -p "$dest/html-api" "$dest/interactivity-api"
for f in class-wp-html-tag-processor.php class-wp-html-attribute-token.php class-wp-html-span.php class-wp-html-text-replacement.php class-wp-html-decoder.php html5-named-character-references.php; do
	curl -sSfL "$base/html-api/$f" -o "$dest/html-api/$f"
done
for f in class-wp-token-map.php speculative-loading.php class-wp-speculation-rules.php class-wp-url-pattern-prefixer.php; do
	curl -sSfL "$base/$f" -o "$dest/$f"
done
for f in class-wp-interactivity-api.php class-wp-interactivity-api-directives-processor.php interactivity-api.php; do
	curl -sSfL "$base/interactivity-api/$f" -o "$dest/interactivity-api/$f"
done

echo "Fetched WordPress ${WP_REF} test core into $dest. Set SNT_WP_HTML_API=$dest/html-api"
