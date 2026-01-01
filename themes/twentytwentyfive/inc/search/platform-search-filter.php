<?php

/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Platform Search Input
 *
 * Since version: 1.8.0
 * Feature: platform-search
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!shortcode_exists("platform_search_filter")) {
    add_shortcode("platform_search_filter", function () {

        // Current search text
        $raw = "";
if (isset($_GET["qs"]) && is_string($_GET["qs"])) {
    $raw = (string) $_GET["qs"];
} elseif (isset($_GET["s"]) && is_string($_GET["s"])) {
    $raw = (string) $_GET["s"];
}
$search_text = $raw !== "" ? sanitize_text_field($raw) : "";

          

      // Submit to current URL for search
$action_url = esc_url( home_url( '/' ) );
$reset_url  = esc_url( home_url( '/' ) );


        ob_start();
        ?>
      <div class="ps-row">
  <form class="ps-form" method="get" action="<?php echo esc_attr($action_url); ?>">
    <div class="ps-input-group">

      <!-- SEARCH LABEL -->
      <label class="ps-label" for="ps-input">
        Site Search
      </label>

      <!-- INPUT + BUTTON INLINE -->
      <div class="ps-input-group__inner">
        <input
          class="ps-input"
          id="ps-input"
          type="search"
          name="s"
          value="<?php echo esc_attr($search_text); ?>"
          placeholder='Search the Platform...'
          autocomplete="off"
        />
        <button type="submit" class="ps-btn" onclick="this.form.action = this.form.action + '#results';">Search</button>

      </div>

      <?php
      // Preserve unrelated GET params
      foreach ($_GET as $k => $v) {
          if (in_array($k, ["s", "gd_paged", "gd_sort"], true)) {
              continue;
          }
          if (is_scalar($v)) {
              printf(
                  '<input type="hidden" name="%s" value="%s" />',
                  esc_attr($k),
                  esc_attr($v)
              );
          }
      }
      ?>

    </div>
  </form>
</div>


        <?php
        return ob_get_clean();
    });
}
