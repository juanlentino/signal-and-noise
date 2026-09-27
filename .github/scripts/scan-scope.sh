#!/usr/bin/env bash
# Decides whether the Claude security scan can be skipped for a PR.
# stdin: the PR's files API rows, one JSON object per line ({filename, patch}).
# stdout: true ONLY when every file is docs/Markdown, or a line tools/cut-release.sh
# writes: style.css whose only changed lines are `Version:`, readme.txt whose
# only changed lines are `Stable tag:`. Anything else, a missing patch (GitHub
# omits it for large diffs), empty input or a jq error prints false: doubt scans.
set -uo pipefail
rows=$(cat)
[ -n "$rows" ] || { echo false; exit 0; }
jq -rs '
  def docs: test("^(docs/.*|[^/]+\\.md)$");
  def only(re):
    (.patch // "") | split("\n")
    | map(select(test("^[-+]")))
    | (length > 0) and all(test(re));
  (length > 0) and all(
    (.filename | docs)
    or (.filename == "style.css" and only("^[-+]Version: +[0-9]+\\.[0-9]+\\.[0-9]+$"))
    or (.filename == "readme.txt" and only("^[-+]Stable tag: +[0-9]+\\.[0-9]+\\.[0-9]+$"))
  )
' <<<"$rows" 2>/dev/null || echo false
