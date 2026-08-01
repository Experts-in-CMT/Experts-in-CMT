<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * ============================================================
 *  Shortcode: [about_author]
 * ------------------------------------------------------------
 *  Renders the standing "About the Author" block for Kenneth
 *  Raymond. Opt-in: placed per post as a Shortcode block, so
 *  co-authored pieces that supply their own author section
 *  simply omit it.
 *
 *  Registered from the theme rather than Code Snippets so the
 *  block is version-controlled and does not depend on a
 *  database-stored snippet.
 *
 *  Location:
 *    /inc/shortcodes/about-author-shortcode.php
 * ============================================================
 */

if (!defined("ABSPATH")) {
    exit();
}

add_shortcode("about_author", function () {
    $bio =
        "Kenneth Raymond was first diagnosed with CMT1 in late 2002 at the " .
        "age of 29 and genetically confirmed with CMT1A a year later. " .
        "Treating his chronic pain became part of his diagnostic journey. " .
        "Since then, he has devoted his life to studying, researching, and " .
        "understanding all aspects of CMT, with a focus on the genetics of " .
        "the disease. Currently pursuing an MS in biological science " .
        "communications at Arizona State University, Kenneth’s commitment " .
        "to advancing knowledge and improving the lives of those living " .
        "with CMT remains as strong as ever.";

    $html = "<h2>About the Author</h2>";
    $html .= "<p>" . $bio . "</p>";

    return $html;
});
