<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/*
 * ------------------------------------------------------------
 * Shortcode: [eic_entry_points]
 * ------------------------------------------------------------
 * Entry-point cards. Hardcoded, with named sets so different pages can show
 * different cards from the same file:
 *
 *   [eic_entry_points set="home"]      four "Where to start?" cards (default)
 *   [eic_entry_points set="genetics"]  four cards mirroring the Genetics nav
 *   [eic_entry_points set="learn"]     three cards mirroring the Learn nav
 *   [eic_entry_points set="platform"]  five cards spanning the whole platform
 *                                      (for the 404 "Explore The Platform")
 *
 * Each card is a clickable panel. The card title carries the real link and a
 * stretched ::after overlays the whole card, so the entire panel is a click
 * target while a screen reader announces one clean link per card (the title).
 * The icon and the bottom "Explore" cue are decorative.
 *
 * The label above each card's list is the "lead". A set has a default lead
 * (the home set uses "Start here if:"); a card can override it with its own
 * "lead" (the genetics set gives each card its own tagline). A card whose lead
 * resolves to "" renders no label.
 *
 * Styling follows the genes/subtype card system (assets/css/genes-loop.css):
 * shared card tokens, a light-weight navy title, bare navy icons in the hero's
 * stroke style (the branch and DNA icons are reused from the heroes), and the
 * house scale-on-hover.
 *
 * Optional attributes:
 *   set="home"                 which card set to render (home | genetics).
 *   heading="Where to start?"  renders an <h2> above the grid.
 *   Omit heading to render the cards only and keep your own heading block.
 *
 * To change the entries, edit the $sets array below. Auto-loaded via the
 * inc/shortcodes glob. Styles live in assets/css/entry-points.css (auto-loaded
 * via the assets/css glob).
 * ------------------------------------------------------------
 */

if (!defined("ABSPATH")) {
    exit();
}

add_shortcode("eic_entry_points", function ($atts) {
    $atts = shortcode_atts(
        ["set" => "home", "heading" => ""],
        $atts,
        "eic_entry_points"
    );

    // Bare line-icons in the hero's style (viewBox 24, stroke-width 2,
    // currentColor). The book, branch and DNA icons are reused verbatim from
    // the Genes DB and Variant Mechanisms heroes.
    $svg = fn($paths) =>
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" ' .
        'stroke-width="2" aria-hidden="true">' . $paths . "</svg>";

    $icon_info = $svg(
        '<circle cx="12" cy="12" r="9"/><path d="M12 11v5" stroke-linecap="round"/>' .
        '<circle cx="12" cy="7.8" r="1" fill="currentColor" stroke="none"/>'
    );
    // Reused from the Genes DB hero (open book) — naming and history.
    $icon_book = $svg(
        '<path d="M4 5c3-1 6-1 8 1 2-2 5-2 8-1v13c-3-1-6-1-8 1-2-2-5-2-8-1V5z" ' .
        'stroke-linejoin="round"/><path d="M12 6v13" stroke-linecap="round"/>'
    );
    // Search glass: finding your gene/subtype in the catalog is the DB's job.
    $icon_search = $svg(
        '<circle cx="10.5" cy="10.5" r="6.5"/>' .
        '<line x1="15.5" y1="15.5" x2="21" y2="21" stroke-linecap="round"/>'
    );
    // Reused from the Variant Mechanisms hero (categories icon).
    $icon_branch = $svg(
        '<path d="M5 3v6a3 3 0 0 0 3 3h8a3 3 0 0 1 3 3v3M5 12v9" ' .
        'stroke-linecap="round" stroke-linejoin="round"/>' .
        '<circle cx="5" cy="4" r="1.6"/><circle cx="19" cy="20" r="1.6"/>' .
        '<circle cx="5" cy="20" r="1.6"/>'
    );
    // Reused from the Genes DB hero (DNA double-helix "genes" icon). This one
    // is rendered a touch larger than the other card icons (see the --lg
    // modifier below) so the helix reads at card size.
    $icon_dna = $svg(
        '<path d="M6 3c0 6 12 6 12 12M18 21c0-6-12-6-12-12M7 6h10M7 18h10" ' .
        'stroke-linecap="round"/>'
    );
    // Breath / airflow: exhale lines with a curl, for CMT and Breathing.
    $icon_breath = $svg(
        '<path d="M3 8h12a2.5 2.5 0 1 0-2.5-2.5" stroke-linecap="round" ' .
        'stroke-linejoin="round"/>' .
        '<path d="M3 13h8a2 2 0 1 1-2 2" stroke-linecap="round" ' .
        'stroke-linejoin="round"/>' .
        '<path d="M3 18h5" stroke-linecap="round"/>'
    );
    // Nerve impulse: a signal spike, echoing The Dorsal Root's impulse hero.
    $icon_impulse = $svg(
        '<path d="M2 12h4l2.2-7 3.6 14 2.4-9 1.8 4H22" ' .
        'stroke-linecap="round" stroke-linejoin="round"/>'
    );
    // Glossary: a term-and-definition list. Distinct from the book so the
    // Glossary and CMT Classifications never both read as books in one grid.
    $icon_glossary = $svg(
        '<circle cx="5" cy="7" r="1.3" fill="currentColor" stroke="none"/>' .
        '<circle cx="5" cy="12" r="1.3" fill="currentColor" stroke="none"/>' .
        '<circle cx="5" cy="17" r="1.3" fill="currentColor" stroke="none"/>' .
        '<path d="M9 7h11M9 12h11M9 17h8" stroke-linecap="round"/>'
    );

    // ---- Card sets --------------------------------------------------------
    // "lead" on a set is the default label above every card's list. A card may
    // carry its own "lead" to override it (or "" to hide it). "cols" is the
    // desktop column count; every set is 3-up for parity with the rest of the
    // site, and the grid centers an incomplete final row (4 cards -> 3 + 1,
    // 5 cards -> 3 + 2).
    $sets = [
        "home" => [
            "lead" => "Start here if:",
            "cols" => 3,
            "cards" => [
                [
                    "title" => "What Is CMT?",
                    "url"   => home_url("/what-is-cmt/"),
                    "icon"  => $icon_info,
                    "items" => [
                        "New diagnosis",
                        "First exposure",
                        "Family member with CMT",
                        "“I don’t know what this even is”",
                        "Just looking for basic info about CMT",
                    ],
                ],
                [
                    "title" => "The Database",
                    "url"   => home_url("/cmt-genetics-database/"),
                    "icon"  => $icon_search,
                    "items" => [
                        "You have a gene but no subtype",
                        "Your diagnosis is weird",
                        "You need subtype symptoms",
                        "You want to dig deeper",
                        "You want details, not bloat",
                    ],
                ],
                [
                    "title" => "Variant Mechanisms",
                    "url"   => home_url("/genetics/cmt-variant-mechanisms-browser/"),
                    "icon"  => $icon_branch,
                    "items" => [
                        "You know the gene, but want the how",
                        "You want to know what the variant does",
                        "You want the why, not just the label",
                        "You’re comparing subtypes",
                    ],
                ],
                [
                    "title"   => "Genetic Testing",
                    "url"     => home_url("/genetic-testing/"),
                    "icon"    => $icon_dna,
                    "icon_lg" => true, // helix reads better a touch larger
                    "items" => [
                        "Genetics came up",
                        "Testing was mentioned",
                        "You’re not sure why testing matters",
                        "You want the bigger picture",
                        "You need info to bring to your doctor",
                    ],
                ],
            ],
        ],

        // Mirrors the Genetics nav dropdown: Classifications, Genetics
        // Database, Variant Mechanisms, Genetic Testing. Each card carries its
        // own tagline lead instead of the shared "Start here if:".
        "genetics" => [
            "lead" => "",
            "cols" => 3,
            "cards" => [
                [
                    "title" => "CMT Classifications",
                    "url"   => home_url("/cmt-classifications/"),
                    "icon"  => $icon_book,
                    "lead"  => "What’s in a name?",
                    "items" => [
                        "The classifications defined",
                        "From historic to current",
                        "Roussy-Lévy",
                        "HNPP",
                        "Dejerine-Sottas",
                    ],
                ],
                [
                    "title" => "CMT Genetics Database",
                    "url"   => home_url("/cmt-genetics-database/"),
                    "icon"  => $icon_search,
                    "lead"  => "CMT. Curated.",
                    "items" => [
                        "CMT genes and subtypes",
                        "Easy to use",
                        "Subtype-specific symptoms",
                        "Core genetic data",
                        "Reference publications",
                    ],
                ],
                [
                    "title" => "CMT Variant Mechanisms",
                    "url"   => home_url("/genetics/cmt-variant-mechanisms-browser/"),
                    "icon"  => $icon_branch,
                    "lead"  => "The how, not the what",
                    "items" => [
                        "Mechanisms by gene",
                        "What the variant does",
                        "Loss vs gain of function",
                        "Why subtypes differ",
                        "Curated from publications",
                    ],
                ],
                [
                    "title"   => "CMT Genetic Testing",
                    "url"     => home_url("/genetic-testing/"),
                    "icon"    => $icon_dna,
                    "icon_lg" => true,
                    "lead"    => "The info that matters",
                    "items" => [
                        "Types of tests",
                        "When to test",
                        "Genetic test limitations",
                        "Who to see",
                        "Commonly ordered tests",
                    ],
                ],
            ],
        ],

        // Mirrors the Learn nav dropdown: What Is CMT?, CMT and Breathing, CMT
        // Glossary. Three cards, so it renders 3-up on desktop. Each card
        // carries its own tagline lead.
        "learn" => [
            "lead" => "",
            "cols" => 3,
            "cards" => [
                [
                    "title" => "What Is CMT?",
                    "url"   => home_url("/what-is-cmt/"),
                    "icon"  => $icon_info,
                    "lead"  => "CMT: Unpacked and Unfiltered",
                    "items" => [
                        "Signs and symptoms",
                        "Diagnosing",
                        "The different types",
                    ],
                ],
                [
                    "title" => "CMT and Breathing",
                    "url"   => home_url("/cmt-and-breathing/"),
                    "icon"  => $icon_breath,
                    "lead"  => "Answers to Difficult Questions",
                    "items" => [
                        "What to look for",
                        "The breathing muscles",
                        "The breathing nerves",
                    ],
                ],
                [
                    "title" => "CMT Glossary",
                    "url"   => home_url("/cmt-words/"),
                    "icon"  => $icon_glossary,
                    "lead"  => "CMT Words: Found",
                    "items" => [
                        "Medical terms defined",
                        "Hard to find definitions",
                        "Easily searchable",
                        "Dictionary sources included",
                    ],
                ],
            ],
        ],

        // A whole-platform cross-section for the 404 "Explore The Platform"
        // block: one card per section (learn, tool, reference, editorial,
        // look-up), so a lost visitor sees the range. Renders 3-up (3 + 2).
        "platform" => [
            "lead" => "",
            "cols" => 3,
            "cards" => [
                [
                    "title" => "What Is CMT?",
                    "url"   => home_url("/what-is-cmt/"),
                    "icon"  => $icon_info,
                    "lead"  => "CMT: Unpacked and Unfiltered",
                    "items" => [
                        "Signs and symptoms",
                        "Diagnosing",
                        "The different types",
                    ],
                ],
                [
                    "title" => "CMT Genetics Database",
                    "url"   => home_url("/cmt-genetics-database/"),
                    "icon"  => $icon_search,
                    "lead"  => "CMT. Curated.",
                    "items" => [
                        "CMT genes and subtypes",
                        "Easy to use",
                        "Subtype-specific symptoms",
                        "Core genetic data",
                        "Reference publications",
                    ],
                ],
                [
                    "title" => "CMT Classifications",
                    "url"   => home_url("/cmt-classifications/"),
                    "icon"  => $icon_book,
                    "lead"  => "What’s in a name?",
                    "items" => [
                        "The classifications defined",
                        "From historic to current",
                        "Roussy-Lévy",
                        "HNPP",
                        "Dejerine-Sottas",
                    ],
                ],
                [
                    "title" => "The Dorsal Root",
                    "url"   => home_url("/dorsal-root/"),
                    "icon"  => $icon_impulse,
                    "lead"  => "Nerves talk. We listen.",
                    "items" => [
                        "Stories and science",
                        "Living with CMT",
                        "The sensory side",
                        "Fresh reads",
                    ],
                ],
                [
                    "title" => "CMT Glossary",
                    "url"   => home_url("/cmt-words/"),
                    "icon"  => $icon_glossary,
                    "lead"  => "CMT Words: Found",
                    "items" => [
                        "Medical terms defined",
                        "Hard to find definitions",
                        "Easily searchable",
                        "Dictionary sources included",
                    ],
                ],
            ],
        ],
    ];

    $key = isset($sets[$atts["set"]]) ? $atts["set"] : "home";
    $set = $sets[$key];
    $default_lead = $set["lead"];
    $entries = $set["cards"];
    $cols = isset($set["cols"]) ? (int) $set["cols"] : 2;

    ob_start();
    ?>
    <section class="eic-entry-points eic-entry-points--cols-<?php echo $cols; ?>"<?php echo $atts["heading"] !== ""
        ? ' aria-labelledby="eic-ep-heading"'
        : ' aria-label="Entry points"'; ?>>
      <?php if ($atts["heading"] !== ""): ?>
        <h2 id="eic-ep-heading" class="eic-entry-points__heading"><?php echo esc_html(
            $atts["heading"]
        ); ?></h2>
      <?php endif; ?>
      <div class="eic-entry-points__grid">
        <?php foreach ($entries as $e):
            $lead = array_key_exists("lead", $e) ? $e["lead"] : $default_lead;
            ?>
          <article class="eic-ep-card">
            <div class="eic-ep-card__head">
              <span class="eic-ep-card__icon<?php echo !empty($e["icon_lg"])
                  ? " eic-ep-card__icon--lg"
                  : ""; ?>" aria-hidden="true"><?php echo $e["icon"]; ?></span>
              <h3 class="eic-ep-card__title">
                <a class="eic-ep-card__link" href="<?php echo esc_url(
                    $e["url"]
                ); ?>"><?php echo esc_html($e["title"]); ?></a>
              </h3>
            </div>
            <?php if ($lead !== ""): ?>
              <p class="eic-ep-card__lead"><?php echo esc_html($lead); ?></p>
            <?php endif; ?>
            <ul class="eic-ep-card__list">
              <?php foreach ($e["items"] as $item): ?>
                <li><?php echo esc_html($item); ?></li>
              <?php endforeach; ?>
            </ul>
            <span class="eic-ep-card__cta" aria-hidden="true">Explore <span class="eic-ep-card__cta-arrow">&rarr;</span></span>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
    return ob_get_clean();
});
