<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Plugin Name: EIC Subtype Exporter
 * Description: Exports the entire Subtype database (full record data set) as a single lossless JSON file.
 * Version: 1.0.0
 * Author: Kenneth Raymond
 *
 * ------------------------------------------------------------
 * Subtype Database — Full Export
 * ------------------------------------------------------------
 * For each `subtype` record, exports:
 *   - Core post columns (title, slug, status, dates, content, excerpt, etc.)
 *   - post_content and post_excerpt
 *   - ALL raw postmeta (captures every ACF field, schema ACF field,
 *     and every Yoast `_yoast_wpseo_*` entry in one pass, lossless)
 *   - Resolved ACF values via get_fields() for readability (when ACF is active)
 *   - All taxonomy terms (cmt_type, inheritance, neuropathy, chromosome,
 *     plus post_tag and any other registered taxonomy), with term meta
 *   - The Yoast `yoast_indexable` table row for the post
 *
 * NOTE (round-trip): this is a LOSSLESS BACKUP shape (post columns +
 * raw meta + taxonomies + Yoast row), NOT the authored shape the
 * Subtype Importer ingests. Export files are for archival / DB
 * restore, not for feeding back into eic-subtype-importer.php (which
 * expects the authored surface, e.g. {"subtype","type_classification",
 * ...}). The two are intentionally different formats.
 *
 * Location:
 *   /wp-content/mu-plugins/eic-subtype-exporter.php
 *
 * Usage:
 *   Tools → Subtype Export → Download Full Export (.json)
 */

if (!defined("ABSPATH")) {
    exit();
}

const EIC_SUBTYPE_EXPORT_POST_TYPE = "subtype";
const EIC_SUBTYPE_EXPORT_ACTION = "eic_export_subtypes";
const EIC_SUBTYPE_EXPORT_NONCE = "eic_export_subtypes_nonce";
const EIC_SUBTYPE_EXPORT_CAP = "manage_options";

/**
 * Register the Tools submenu page.
 */
add_action("admin_menu", function () {
    add_submenu_page(
        "tools.php",
        "Subtype Export",
        "Subtype Export",
        EIC_SUBTYPE_EXPORT_CAP,
        "eic-subtype-export",
        "eic_subtype_export_render_page"
    );
});

/**
 * Render the export admin page.
 */
function eic_subtype_export_render_page()
{
    if (!current_user_can(EIC_SUBTYPE_EXPORT_CAP)) {
        return;
    }

    $count = (int) wp_count_posts(EIC_SUBTYPE_EXPORT_POST_TYPE)->publish;
    $total = array_sum((array) wp_count_posts(EIC_SUBTYPE_EXPORT_POST_TYPE));
    ?>
    <?php eic_admin_tool_open('Subtype Export'); ?>
        <p>
            Exports every <code>subtype</code> record as a single lossless JSON
            file: all core columns, post content and excerpt, all raw postmeta
            (ACF, schema ACF, and Yoast SEO meta), all taxonomies and tags with
            term meta, and the Yoast indexable row.
        </p>
        <p>
            <strong><?php echo esc_html($count); ?></strong> published,
            <strong><?php echo esc_html($total); ?></strong> total records
            (including drafts, pending, private, scheduled).
        </p>
        <form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr(EIC_SUBTYPE_EXPORT_ACTION); ?>">
            <?php wp_nonce_field(EIC_SUBTYPE_EXPORT_ACTION, EIC_SUBTYPE_EXPORT_NONCE); ?>
            <p>
                <button type="submit" class="button button-primary">
                    Download Full Export (.json)
                </button>
            </p>
        </form>
    <?php eic_admin_tool_close(); ?>
    <?php
}

/**
 * Handle the download request: build JSON and stream it.
 */
add_action("admin_post_" . EIC_SUBTYPE_EXPORT_ACTION, function () {
    if (!current_user_can(EIC_SUBTYPE_EXPORT_CAP)) {
        wp_die("Insufficient permissions.");
    }

    check_admin_referer(
        EIC_SUBTYPE_EXPORT_ACTION,
        EIC_SUBTYPE_EXPORT_NONCE
    );

    $payload = eic_subtype_export_build_payload();

    $json = wp_json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ($json === false) {
        wp_die("JSON encoding failed: " . json_last_error_msg());
    }

    $filename =
        "eic-subtypes-export-" . gmdate("Ymd-His") . ".json";

    nocache_headers();
    header("Content-Type: application/json; charset=utf-8");
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header("Content-Length: " . strlen($json));

    echo $json; // Already escaped/encoded JSON.
    exit();
});

/**
 * Build the full export payload for every subtype record.
 *
 * @return array
 */
function eic_subtype_export_build_payload()
{
    global $wpdb;

    $ids = get_posts([
        "post_type" => EIC_SUBTYPE_EXPORT_POST_TYPE,
        "post_status" => ["publish", "draft", "pending", "private", "future"],
        "posts_per_page" => -1,
        "orderby" => "ID",
        "order" => "ASC",
        "fields" => "ids",
        "suppress_filters" => true,
        "no_found_rows" => true,
    ]);

    // Every taxonomy registered against the CPT, plus post_tag for completeness.
    $taxonomies = get_object_taxonomies(
        EIC_SUBTYPE_EXPORT_POST_TYPE,
        "names"
    );
    if (!in_array("post_tag", $taxonomies, true)) {
        $taxonomies[] = "post_tag";
    }

    $has_acf = function_exists("get_fields");
    $indexable_table = $wpdb->prefix . "yoast_indexable";
    $has_indexable =
        (bool) $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $indexable_table)
        );

    $records = [];

    foreach ($ids as $id) {
        $post = get_post($id);
        if (!$post) {
            continue;
        }

        $records[] = [
            "post" => [
                "ID" => (int) $post->ID,
                "post_title" => $post->post_title,
                "post_name" => $post->post_name,
                "post_status" => $post->post_status,
                "post_type" => $post->post_type,
                "post_author" => (int) $post->post_author,
                "post_date" => $post->post_date,
                "post_date_gmt" => $post->post_date_gmt,
                "post_modified" => $post->post_modified,
                "post_modified_gmt" => $post->post_modified_gmt,
                "post_parent" => (int) $post->post_parent,
                "menu_order" => (int) $post->menu_order,
                "guid" => $post->guid,
                "permalink" => get_permalink($post),
                "post_content" => $post->post_content,
                "post_excerpt" => $post->post_excerpt,
            ],
            "meta" => eic_subtype_export_raw_meta($id),
            "acf" => $has_acf
                ? (get_fields($id) ?: [])
                : null,
            "taxonomies" => eic_subtype_export_terms($id, $taxonomies),
            "yoast_indexable" => $has_indexable
                ? eic_subtype_export_indexable($id, $indexable_table)
                : null,
        ];
    }

    return [
        "manifest" => [
            "generator" => "EIC Subtype Exporter",
            "version" => "1.0.0",
            "site_url" => site_url(),
            "table_prefix" => $wpdb->prefix,
            "post_type" => EIC_SUBTYPE_EXPORT_POST_TYPE,
            "taxonomies" => array_values($taxonomies),
            "exported_at" => gmdate("c"),
            "count" => count($records),
        ],
        "subtypes" => $records,
    ];
}

/**
 * All raw postmeta rows for a post, preserving duplicate keys and
 * exact stored values (captures ACF, schema ACF, and Yoast meta).
 *
 * @param int $post_id
 * @return array meta_key => [ raw value, ... ]
 */
function eic_subtype_export_raw_meta($post_id)
{
    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT meta_key, meta_value
             FROM {$wpdb->postmeta}
             WHERE post_id = %d
             ORDER BY meta_id ASC",
            $post_id
        ),
        ARRAY_A
    );

    $meta = [];
    foreach ((array) $rows as $row) {
        $meta[$row["meta_key"]][] = $row["meta_value"];
    }

    return $meta;
}

/**
 * All terms for a post across the given taxonomies, with term meta.
 *
 * @param int   $post_id
 * @param array $taxonomies
 * @return array taxonomy => [ term, ... ]
 */
function eic_subtype_export_terms($post_id, array $taxonomies)
{
    $out = [];

    foreach ($taxonomies as $taxonomy) {
        $terms = wp_get_object_terms($post_id, $taxonomy);
        if (is_wp_error($terms) || empty($terms)) {
            continue;
        }

        $out[$taxonomy] = array_map(function ($term) {
            return [
                "term_id" => (int) $term->term_id,
                "name" => $term->name,
                "slug" => $term->slug,
                "taxonomy" => $term->taxonomy,
                "parent" => (int) $term->parent,
                "description" => $term->description,
                "term_meta" => get_term_meta($term->term_id),
            ];
        }, $terms);
    }

    return $out;
}

/**
 * The Yoast indexable row for a post as an associative array.
 *
 * @param int    $post_id
 * @param string $table
 * @return array|null
 */
function eic_subtype_export_indexable($post_id, $table)
{
    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE object_type = %s AND object_id = %d
             LIMIT 1",
            "post",
            $post_id
        ),
        ARRAY_A
    );

    return $row ?: null;
}
