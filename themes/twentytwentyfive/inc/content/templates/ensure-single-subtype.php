<?php
/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  TEMPLATE GUARANTEE — "single-subtype"
 *  ------------------------------------------------------------
 *  Purpose:
 *    Ensures that the block theme template `single-subtype`
 *    always exists and remains published inside the Site Editor.
 *
 *  Behavior:
 *    - Runs only when a block theme is active.
 *    - Checks for the wp_template post matching:
 *          {theme}//single-subtype
 *    - If missing or unpublished, creates or repairs it.
 *    - Keeps template in sync so Subtype single pages always
 *      fall back cleanly to the TT25 PHP template unless the
 *      user edits it in the Site Editor.
 *
 *  Notes:
 *    - This is infrastructure glue; not part of AJAX/filter stacks.
 *    - Ensures Subtype single pages never break due to missing
 *      wp_template entries (a known TT25 ecosystem pitfall).
 * ============================================================
 */

add_action(
    "init",
    function () {
        if (!wp_is_block_theme()) {
            return;
        }

        $theme = wp_get_theme()->get_stylesheet();
        $slug = "single-subtype";
        $id = $theme . "//" . $slug;

        $template = function_exists("get_block_template")
            ? get_block_template($id, "wp_template")
            : null;

        if (
            !$template ||
            (isset($template->status) && "publish" !== $template->status)
        ) {
            $args = [
                "post_type" => "wp_template",
                "post_status" => "publish",
                "post_name" => $slug,
                "post_title" => "Single Item: Subtype",
                "post_content" => "", // empty = use file fallback unless/ until you edit in the Site Editor
                "tax_input" => ["wp_theme" => [$theme]],
            ];

            $existing = get_posts([
                "post_type" => "wp_template",
                "name" => $slug,
                "post_status" => ["any", "trash", "draft", "publish"],
                "numberposts" => 1,
                "tax_query" => [
                    [
                        "taxonomy" => "wp_theme",
                        "field" => "name",
                        "terms" => [$theme],
                    ],
                ],
            ]);

            if ($existing) {
                $args["ID"] = $existing[0]->ID;
                wp_update_post($args);
            } else {
                wp_insert_post($args);
            }
        }
    },
    20
);
