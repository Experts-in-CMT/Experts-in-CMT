<?php
/**
 * Plugin Name: Site Footer Copyright
 * Description: Outputs © 2020–current year and the site name at the bottom of every page.
 * Version: 1.0.0
 * Author: cmtgenes dev
 */

add_action('wp_footer', function () {
    $start = 2020;
    $year  = (int) current_time('Y');
    $name  = get_bloginfo('name');
    $years = ($year <= $start) ? $start : ($start . '–' . $year);

    echo '<div class="site-copyright">&copy; ' . esc_html($years) . ' ' . esc_html($name) . '</div>';
}, 99);
