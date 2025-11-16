<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Template Part: Glossary Source Button
 * ------------------------------------------------------------
 * Renders the single centered "Source" button on Glossary pages.
 * Pulls two ACF fields:
 *   • source_url
 *   • source_label (optional override)
 */

defined("ABSPATH") || exit();

$post_id = get_the_ID();
if (!$post_id) {
    return;
}

$source_url = trim((string) get_field("source_url", $post_id));
$source_label = trim((string) get_field("source_label", $post_id));

if (empty($source_url)) {
    return;
}

// Default label
if ($source_label === "" || $source_label === null) {
    $source_label = "Source";
}

// Sanitize label text (remove rogue <br>)
$btn_text = trim(
    wp_strip_all_tags(preg_replace("/<br\s*\/?>/i", "", $source_label))
);

$title = get_the_title($post_id);
$aria = sprintf(
    "Open source for %s",
    is_string($title) ? wp_strip_all_tags($title) : "this term"
);
?>

<div class="eic-glossary-fields">
  <div class="eic-glossary-source">
    <a
      class="dr-more"
      href="<?php echo esc_url($source_url); ?>"
      target="_blank"
      rel="noopener noreferrer"
      aria-label="<?php echo esc_attr($aria); ?>"
    ><?php echo esc_html($btn_text); ?></a>
  </div>
</div>
<?php /* no trailing newline */ ?>
