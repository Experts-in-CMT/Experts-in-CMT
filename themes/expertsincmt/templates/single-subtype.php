<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Template: Single Item — Subtype
 * ------------------------------------------------------------
 * Purpose:
 *   - Loads the Subtype ACF field renderer
 *   - Header and Footer already included globally
 *   - Header banner handled by the header template itself
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
