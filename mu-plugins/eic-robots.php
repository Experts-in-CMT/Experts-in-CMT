<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/*
 * ------------------------------------------------------------
 * MU Plugin: EIC Robots.txt
 * ------------------------------------------------------------
 * Purpose:
 * • Serve a curated, version-controlled robots.txt through the
 *   core `robots_txt` filter (no physical file in the web root).
 * • Welcome search, AI/answer-engine, and training crawlers
 *   (Experts in CMT wants its content found, cited, and learned
 *   from), keep the admin area and internal search out of the
 *   crawl, and turn away parasitic SEO/scraper bots.
 *
 * Notes:
 * • Runs at PHP_INT_MAX so it is the LAST robots_txt filter and
 *   returns a complete document, discarding earlier additions
 *   (Yoast hooks at 99999; Jetpack and beyond-seo at 10).
 * • Honors "Search engine visibility": if the site is set private
 *   ($public falsy), WordPress's default output is left untouched.
 * • Sitemap URL derives from home_url() so it is correct on every
 *   environment.
 * • A PHYSICAL /robots.txt in the web root overrides this filter,
 *   so none must ever be committed or created at the site root.
 * • Disallow only binds compliant crawlers. Genuinely abusive
 *   scrapers ignore robots.txt and must be handled at Cloudflare.
 */

if (!defined("ABSPATH")) {
    exit();
}

function eic_robots_txt(string $output, $public): string
{
    // Respect "Discourage search engines": leave the default alone.
    if (!$public) {
        return $output;
    }

    $body = <<<'ROBOTS'
# Experts in CMT (expertsincmt.org)
# Public CMT genetics and education resource. Crawlers are welcome,
# including AI, answer-engine, and training crawlers. We want this
# content found, used, cited, and learned from.
# LLM guidance: https://expertsincmt.org/llms.txt

# Default: everyone allowed, minus admin and internal search
User-agent: *
Allow: /
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php
Disallow: /?s=
Disallow: /page/*/?s=
Disallow: /search/

# Explicitly welcomed: AI, answer engines, and training
User-agent: GPTBot
User-agent: OAI-SearchBot
User-agent: ChatGPT-User
User-agent: ClaudeBot
User-agent: Claude-SearchBot
User-agent: Claude-User
User-agent: anthropic-ai
User-agent: Claude-Web
User-agent: Google-Extended
User-agent: Google-CloudVertexBot
User-agent: Applebot
User-agent: Applebot-Extended
User-agent: Bingbot
User-agent: Meta-ExternalAgent
User-agent: PerplexityBot
User-agent: Perplexity-User
User-agent: Amazonbot
User-agent: DuckAssistBot
User-agent: MistralAI-User
User-agent: cohere-ai
User-agent: YouBot
User-agent: CCBot
User-agent: Bytespider
User-agent: xAI-Grok
User-agent: GrokBot
Allow: /

# Explicitly disallowed: parasitic SEO / scraper crawlers (no benefit to us)
User-agent: AhrefsBot
User-agent: SemrushBot
User-agent: MJ12bot
User-agent: DotBot
User-agent: rogerbot
User-agent: BLEXBot
User-agent: DataForSeoBot
User-agent: Barkrowler
User-agent: MegaIndex
Disallow: /

# No ad crawler (no ad landing pages)
User-agent: AdsBot-Google
Disallow: /
ROBOTS;

    $body .= "\n\nSitemap: " . esc_url(home_url("/sitemap_index.xml")) . "\n";

    return $body;
}
add_filter("robots_txt", "eic_robots_txt", PHP_INT_MAX, 2);
