<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  SHORTCODE: AUTHORITATIVE RESOURCES
 *  ------------------------------------------------------------
 *  Purpose:
 *    - Renders a compact strip of outbound links to primary,
 *      authoritative references for Charcot-Marie-Tooth disease.
 *    - Gives answer engines on-page evidence that the site's
 *      claims are anchored to recognized sources — the last of
 *      the four "citability" signals (pages that cite get cited).
 *
 *  Usage:
 *    [eic_authoritative_resources]
 *    [eic_authoritative_resources heading="References"]
 *
 *  The link list is filterable so it can be curated without
 *  editing this file:
 *    add_filter( 'eic_authoritative_resources_links', fn( $l ) => $l );
 *
 *  Seed links are the same references already declared as the
 *  CMT MedicalCondition `sameAs` set in pages-jsonld.php, so
 *  they are known-good. Extend with patient-facing authorities
 *  (GARD, MedlinePlus, GeneReviews) once their exact URLs are
 *  confirmed.
 *
 *  Location:
 *    /inc/shortcodes/authoritative-resources-shortcode.php
 * ============================================================
 */

defined("ABSPATH") || exit();

/**
 * The curated, filterable list of authoritative references.
 *
 * @return array<int,array{name:string,url:string,source:string}>
 */
function eic_authoritative_resources_links()
{
    $links = [
        [
            "name" => "Charcot-Marie-Tooth disease: phenotypic series",
            "url" => "https://omim.org/phenotypicSeries/PS118220",
            "source" => "OMIM",
        ],
        [
            "name" => "Charcot-Marie-Tooth disease",
            "url" => "https://www.orpha.net/en/disease/detail/166",
            "source" => "Orphanet",
        ],
        [
            "name" => "Charcot-Marie-Tooth Disease",
            "url" => "https://meshb.nlm.nih.gov/record/ui?ui=D002607",
            "source" => "MeSH, U.S. National Library of Medicine",
        ],
    ];

    return (array) apply_filters("eic_authoritative_resources_links", $links);
}

add_shortcode("eic_authoritative_resources", "eic_authoritative_resources_render");

/**
 * Render the authoritative-resources strip.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function eic_authoritative_resources_render($atts = [])
{
    $atts = shortcode_atts(
        [
            "heading" => "Authoritative resources",
        ],
        $atts,
        "eic_authoritative_resources"
    );

    $links = eic_authoritative_resources_links();
    if (empty($links)) {
        return "";
    }

    static $printed_style = false;

    ob_start();

    if (!$printed_style) {
        $printed_style = true; ?>
        <style>
            .eic-auth-res {
                margin: 2rem 0;
                padding: 1.5rem 1.75rem;
                border: 1px solid rgba(0, 0, 0, 0.08);
                border-radius: 12px;
                background: var(--eic-paper-alt, #f4f5f2);
            }
            .eic-auth-res__heading {
                margin: 0 0 0.35rem;
                font-size: 0.78rem;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: var(--eic-accent, #1c6b57);
            }
            .eic-auth-res__intro {
                margin: 0 0 1rem;
                font-size: 0.95rem;
                color: var(--eic-ink-soft, #4d5a55);
                max-width: 60ch;
            }
            .eic-auth-res__list {
                list-style: none;
                margin: 0;
                padding: 0;
                display: grid;
                gap: 0.5rem;
            }
            .eic-auth-res__item {
                display: flex;
                flex-wrap: wrap;
                align-items: baseline;
                gap: 0.5rem;
            }
            .eic-auth-res__link {
                font-weight: 600;
                text-decoration: underline;
                text-underline-offset: 2px;
            }
            .eic-auth-res__source {
                font-size: 0.82rem;
                color: var(--eic-ink-soft, #4d5a55);
            }
        </style>
    <?php }
    ?>
    <aside class="eic-auth-res" aria-label="<?php echo esc_attr(
        $atts["heading"]
    ); ?>">
        <p class="eic-auth-res__heading"><?php echo esc_html(
            $atts["heading"]
        ); ?></p>
        <p class="eic-auth-res__intro">
            Charcot-Marie-Tooth disease as catalogued by recognized medical and
            genetics authorities.
        </p>
        <ul class="eic-auth-res__list">
            <?php foreach ($links as $link): ?>
                <li class="eic-auth-res__item">
                    <a
                        class="eic-auth-res__link"
                        href="<?php echo esc_url($link["url"]); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    ><?php echo esc_html($link["name"]); ?></a>
                    <span class="eic-auth-res__source"><?php echo esc_html(
                        $link["source"]
                    ); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>
    <?php return (string) ob_get_clean();
}
