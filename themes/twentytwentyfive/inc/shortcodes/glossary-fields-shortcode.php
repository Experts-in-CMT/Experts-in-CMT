<?php
/**
 * Shortcode: [glossary_fields]
 * Renders the Glossary Source button using the glossary-fields-template.php partial.
 *
 * Usage: Add a Shortcode block in the Glossary single template and insert:
 * [glossary_fields]
 *
 * @package ExpertsInCMT
 */

if (!defined("ABSPATH")) {
    exit();
}

add_shortcode('glossary_fields', function ($atts = []) {
  ob_start();

  $template = get_stylesheet_directory() . '/templates/glossary-fields-template.php';

  if (file_exists($template)) {
    include $template;
  }

  // Clean rogue line breaks and extra spaces
  $output = ob_get_clean();
  $output = preg_replace('/^\s+|\s+$/u', '', $output);

  return trim($output);
});