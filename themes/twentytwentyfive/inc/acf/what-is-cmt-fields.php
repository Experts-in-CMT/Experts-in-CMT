<?php
/**
 * © 2025 Kenneth Raymond — All rights reserved.
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * File: ACF — What Is CMT Topic Fields
 * Purpose: Registers the minimal field group for the What Is CMT
 *          Topics CPT. Includes a dynamic Title field (for Meta Field Block)
 *          and a 300-character summary field used for hub page previews.
 */

if (!defined('ABSPATH')) exit;

add_action('init', 'eic_acf_what_is_cmt_fields');
function eic_acf_what_is_cmt_fields() {

    acf_add_local_field_group(array(
        'key'      => 'group_eic_what_is_cmt',
        'title'    => 'What Is CMT Topic Fields',
        'fields'   => array(

            // ============================================================
            // Title (used for Meta Field Block output)
            // ============================================================
            array(
                'key'           => 'field_eic_wic_title',
                'label'         => 'Topic Title',
                'name'          => 'topic_title',
                'type'          => 'text',
                'instructions'  => 'Enter the title exactly as it should appear on the page.',
                'required'      => 1,
                'maxlength'     => 120,
                'wrapper'       => array(
                    'width' => '100',
                ),
            ),

            // ============================================================
            // Summary (300-character max)
            // ============================================================
            array(
                'key'           => 'field_eic_wic_summary',
                'label'         => 'Short Summary',
                'name'          => 'summary',
                'type'          => 'textarea',
                'instructions'  => '2-4 sentences. Maximum 600 characters.',
                'maxlength'     => 600,
                'rows'          => 3,
                'new_lines'     => '', // plain text only
                'wrapper'       => array(
                    'width' => '100',
                ),
            ),
        ),

        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'what-is-cmt',
                ),
            ),
        ),

        'style'         => 'seamless',
        'position'      => 'acf_after_title',
        'menu_order'    => 0,
    ));
}
