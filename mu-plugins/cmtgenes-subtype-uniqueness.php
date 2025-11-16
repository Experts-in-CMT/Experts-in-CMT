<?php

/*
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ------------------------------------------------------------
 * MU Plugin: Subtype Uniqueness Enforcement
 * ------------------------------------------------------------
 * Scope:
 * • Prevents duplicate Subtype posts from being created
 * • Checks ACF field “subtype” (fallback to raw meta)
 * • Blocks save with a clear admin message when duplicates exist
 *
 * Critical:
 * • Triggers on save_post_subtype
 * • Ignores autosaves and trashed posts
 * • Ensures a single canonical Subtype entry per value
 */

add_action(
    "save_post_subtype",
    function ($post_id, $post, $update) {
        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }
        if ($post->post_status === "trash") {
            return;
        }

        // Prefer ACF value; fallback to raw meta
        $val = function_exists("get_field")
            ? get_field("subtype", $post_id)
            : get_post_meta($post_id, "subtype", true);
        $val = trim((string) $val);
        if ($val === "") {
            return;
        }

        $dupe = new WP_Query([
            "post_type" => "subtype",
            "post_status" => [
                "publish",
                "pending",
                "draft",
                "future",
                "private",
            ],
            "posts_per_page" => 1,
            "post__not_in" => [$post_id],
            "meta_query" => [
                ["key" => "subtype", "value" => $val, "compare" => "="],
            ],
            "fields" => "ids",
            "no_found_rows" => true,
        ]);

        if ($dupe->have_posts()) {
            wp_die(
                __(
                    "Duplicate Subtype detected. A record with this Subtype already exists. Update that record instead.",
                    "experts-in-cmt"
                ),
                __("Duplicate Subtype", "experts-in-cmt"),
                403
            );
        }
    },
    10,
    3
);
