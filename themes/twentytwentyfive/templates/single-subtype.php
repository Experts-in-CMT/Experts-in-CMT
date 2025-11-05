<?php
/**
 * Template: Single Item — Subtype
 * ------------------------------------------------------------
 * Purpose:
 * - Loads the Subtype ACF field renderer
 * - Header and Footer already included globally
 * - Header banner handled by the header template itself
 *
 * @package ExpertsInCMT
 * @since 1.0
 */

// Exit if accessed directly
defined("ABSPATH") || exit();

/* ============================================================
   # SUBTYPE FIELDS TEMPLATE
   ------------------------------------------------------------
   Renders all ACF-driven Subtype data in three main sections:
     1. Subtype Overview
     2. Clinical & Genetic Context
     3. Key Publication
   ============================================================ */
get_template_part("templates/subtype-fields-template");
