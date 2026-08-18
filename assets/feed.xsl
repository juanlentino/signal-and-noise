<?xml version="1.0" encoding="UTF-8"?>
<!--
  Signal & Noise — RSS feed stylesheet.

  Renders the raw RSS2 document as a readable page for anyone who clicks the
  feed link without a reader installed. The site names RSS as its ONLY endorsed
  channel ("No subscription form. No schedule. Notes via RSS"), so serving that
  channel as an apparent parse error contradicted its own front page.

  Presentation only: browsers with XSLT apply this, browsers without it show the
  raw XML that was always there, and every feed READER ignores it entirely
  (readers parse the XML and never fetch a stylesheet). It therefore degrades to
  exactly the previous behaviour and cannot break subscription.

  Asset paths are ROOT-RELATIVE on purpose: relative URLs in XSLT output resolve
  against the SOURCE document (the feed URL, /notes/feed/), not against this
  stylesheet, so "fonts/x.woff2" would resolve to /notes/feed/fonts/x.woff2.
  Font stacks fall back to system faces if the theme directory ever moves.
-->
<xsl:stylesheet version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sn="https://juanlentino.com/ns/feed"
	exclude-result-prefixes="atom sn">

<xsl:output method="html" encoding="UTF-8" indent="yes"
	doctype-system="about:legacy-compat" />

<xsl:template match="/rss/channel">
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex" />
	<title><xsl:value-of select="title" /> — Feed</title>
	<style>
		@font-face {
			font-family: 'Bebas Neue';
			src: url('/wp-content/themes/signal-and-noise/assets/fonts/bebas-neue-latin.woff2') format('woff2');
			font-display: swap; font-weight: 400; font-style: normal;
		}
		@font-face {
			font-family: 'DM Mono';
			src: url('/wp-content/themes/signal-and-noise/assets/fonts/dm-mono-400-latin.woff2') format('woff2');
			font-display: swap; font-weight: 400; font-style: normal;
		}
		:root {
			--bone: #000; --rust: #666; --blood: #e00404;
			--paper: #fff; --rule: #e5e5e5;
		}
		* { box-sizing: border-box; }
		body {
			margin: 0; background: var(--paper); color: var(--bone);
			font-family: 'DM Mono', 'Courier New', monospace;
			font-size: 15px; line-height: 1.6;
			padding: clamp(1.5rem, 5vw, 5rem);
		}
		.wrap { max-width: 1100px; margin: 0 auto; }
		.eyebrow {
			font-size: max(0.7rem, 11px); letter-spacing: 0.18em;
			text-transform: uppercase; color: var(--blood); margin: 0 0 1rem;
		}
		h1 {
			font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400;
			font-size: clamp(3rem, 8vw, 7rem); line-height: 0.95;
			letter-spacing: -0.02em; margin: 0 0 1.25rem;
		}
		.dek { color: var(--rust); max-width: 52ch; margin: 0 0 1.5rem; }
		.notice {
			border-left: 2px solid var(--blood);
			padding: 0.75rem 0 0.75rem 1rem; margin: 0 0 2rem;
			color: var(--rust); max-width: 62ch;
		}
		.notice strong { color: var(--bone); font-weight: 400; }
		.url {
			display: block; margin-top: 0.6rem; word-break: break-all;
			color: var(--blood); font-size: max(0.75rem, 12px);
		}
		a { color: var(--blood); }
		a:hover, a:focus-visible { text-decoration: none; }
		hr {
			border: 0; border-top: 1px solid var(--rule); margin: 2.5rem 0;
		}
		.count {
			font-size: max(0.7rem, 11px); letter-spacing: 0.18em;
			text-transform: uppercase; color: var(--rust);
			display: flex; justify-content: space-between; margin-bottom: 2rem;
		}
		ol { list-style: none; margin: 0; padding: 0; }
		li { border-bottom: 1px solid var(--rule); padding: 1.75rem 0; }
		.row { display: grid; grid-template-columns: 10rem 1fr; gap: 1.5rem; }
		@media (max-width: 700px) { .row { grid-template-columns: 1fr; gap: 0.5rem; } }
		.spec {
			font-size: max(0.7rem, 11px); letter-spacing: 0.14em;
			text-transform: uppercase; color: var(--rust);
		}
		.spec .rt { display: block; margin-top: 0.35rem; }
		h2 {
			font-family: 'Bebas Neue', Impact, sans-serif; font-weight: 400;
			font-size: clamp(1.5rem, 3vw, 2.1rem); line-height: 1.05;
			margin: 0 0 0.6rem;
		}
		h2 a { color: var(--bone); text-decoration: none; }
		h2 a:hover, h2 a:focus-visible { color: var(--blood); }
		.desc { color: var(--rust); margin: 0; max-width: 62ch; }
		footer {
			margin-top: 3rem; font-size: max(0.7rem, 11px);
			letter-spacing: 0.18em; text-transform: uppercase; color: var(--rust);
		}
	</style>
</head>
<body>
<div class="wrap">

	<p class="eyebrow">RSS &#183; Feed</p>
	<h1><xsl:value-of select="title" /></h1>
	<p class="dek"><xsl:value-of select="description" /></p>

	<div class="notice">
		<strong>This is a feed, not a broken page.</strong> Paste the address below
		into any feed reader and new notes arrive on their own — no account, no
		email, no algorithm deciding what you see.
		<a class="url" href="{atom:link[@rel='self']/@href}"><xsl:value-of select="atom:link[@rel='self']/@href" /></a>
		<xsl:if test="not(atom:link[@rel='self']/@href)">
			<span class="url"><xsl:value-of select="link" />feed/</span>
		</xsl:if>
	</div>

	<hr />

	<div class="count">
		<span>Feed &#183; Latest</span>
		<span><xsl:value-of select="count(item)" /> entries</span>
	</div>

	<ol>
		<xsl:for-each select="item">
			<li>
				<div class="row">
					<div class="spec">
						<span><xsl:value-of select="substring(pubDate, 6, 11)" /></span>
						<xsl:if test="sn:readingTimeMinutes">
							<span class="rt"><xsl:value-of select="sn:readingTimeMinutes" /> min</span>
						</xsl:if>
					</div>
					<div>
						<h2><a href="{link}"><xsl:value-of select="title" /></a></h2>
						<p class="desc"><xsl:value-of select="description" /></p>
					</div>
				</div>
			</li>
		</xsl:for-each>
	</ol>

	<footer>
		<a href="{link}">&#8592; Back to <xsl:value-of select="title" /></a>
	</footer>

</div>
</body>
</html>
</xsl:template>

</xsl:stylesheet>
