<?php

/*
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * Note:
 * This MU-plugin outputs the dynamic © year range and site name
 * in the footer. Safe to keep active across all environments.
 */

add_action('wp_footer', function () {
    $start = 2020; // adjust if needed
    $year  = (int) current_time('Y');
    $name  = get_bloginfo('name');
    $years = ($year <= $start) ? $start : ($start . '–' . $year);

    echo '<div class="site-copyright">&copy; ' . esc_html($years) . ' ' . esc_html($name) . '</div>';
}, 99);
