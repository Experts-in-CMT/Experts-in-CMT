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
 * Shortcode: [eic_dataset_download]
 * ------------------------------------------------------------
 * Ungated download button for the current gene-resolved CMT dataset.
 * Links the newest zip produced by the CMT Dataset Export tool
 * (uploads/eic-datasets/eic-cmt-genes-v*.zip), which bundles the JSON,
 * CSV, README, and license. No email capture, no gate — a direct link,
 * matching the open CC BY 4.0 release.
 *
 * Renders nothing if no build exists yet. Optional attribute:
 *   label="Download the dataset"   button text.
 *
 * Auto-loaded via the inc/shortcodes glob; styles are inline (printed
 * once) so no separate stylesheet is needed.
 * ------------------------------------------------------------
 */

if (!defined("ABSPATH")) {
    exit();
}

add_shortcode("eic_dataset_download", function ($atts = []) {
    $atts = shortcode_atts(
        ["label" => "Download the dataset"],
        $atts,
        "eic_dataset_download"
    );

    $up = wp_upload_dir();
    $dir = trailingslashit($up["basedir"]) . "eic-datasets";
    $zips = glob(trailingslashit($dir) . "eic-cmt-genes-v*.zip");
    if (empty($zips)) {
        return "";
    }

    // Newest build wins.
    usort($zips, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $latest = $zips[0];
    $file = basename($latest);
    $ver = preg_match('/v([0-9A-Za-z._-]+)\.zip$/', $file, $m) ? $m[1] : "";
    $built = date_i18n(get_option("date_format"), (int) filemtime($latest));
    $size = size_format((int) filesize($latest), 1);
    // Cache-bust the static zip URL with the build's mtime. The filename is
    // stable across rebuilds, so without this a CDN or the browser serves the
    // old bytes at the same URL. Self-healing: the query changes only when the
    // file changes, so there is nothing to bump by hand.
    $url = trailingslashit($up["baseurl"]) . "eic-datasets/" . rawurlencode($file) . "?v=" . (int) filemtime($latest);

    static $css_done = false;
    $style = "";
    if (!$css_done) {
        $css_done = true;
        $style =
            "<style>" .
            ".eic-dl{margin:1.5rem 0}" .
            ".eic-dl__btn{display:inline-block;padding:.85rem 1.6rem;border-radius:10px;background:var(--primary,#173a63);color:#fff;font-weight:600;text-decoration:none;transition:transform .12s ease,box-shadow .12s ease}" .
            ".eic-dl__btn:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgb(14 42 84 / 18%)}" .
            ".eic-dl__meta{margin:.6rem 0 0;font-size:13px;color:var(--muted,#6b7480)}" .
            ".eic-dl__hint{margin:.35rem 0 0;font-size:12px;color:var(--muted,#6b7480)}" .
            "</style>";
    }

    ob_start();
    ?>
    <?php echo $style; ?>
    <div class="eic-dl">
      <a class="eic-dl__btn" href="<?php echo esc_url($url); ?>" download="<?php echo esc_attr(
          $file
      ); ?>"><?php echo esc_html($atts["label"]); ?></a>
      <p class="eic-dl__meta">Version <?php echo esc_html($ver); ?> &middot; <?php echo esc_html(
          $built
      ); ?> &middot; ZIP <?php echo esc_html(
          $size
      ); ?> &middot; JSON, CSV, README, license &middot; CC BY 4.0</p>
      <p class="eic-dl__hint">Trouble downloading? Some in-app browsers (Instagram, Facebook) block file downloads; open this page in Safari or Chrome instead.</p>
    </div>
    <?php
    return ob_get_clean();
});
