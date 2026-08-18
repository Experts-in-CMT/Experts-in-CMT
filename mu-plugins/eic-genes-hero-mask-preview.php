<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Plugin Name: EIC Subtype Browser Hero Mask Preview
 * Description: Live fade/mask preview on the Subtype Browser app hero image thumbnail in the ACF meta box. Drag the Desktop and Mobile "Fade Start %" / "Fade End %" sliders and watch each mask move, matching the front-end hero ::after gradient at that breakpoint. Editor-only; renders no front-end output.
 * Version: 1.0.0
 * Author: Kenneth Raymond
 *
 * ------------------------------------------------------------
 * Subtype Browser Hero — Editor Mask Preview
 * ------------------------------------------------------------
 * The hero ([genes_hero] / subtype-browser-hero.css) masks its background
 * image with a left-to-right gradient:
 *
 *   linear-gradient(to right,
 *     var(--bg) 0%,
 *     var(--bg) var(--genes-hero-fade-start),
 *     transparent var(--genes-hero-fade-end))
 *
 * The front end uses a SEPARATE gradient at <= 600px driven by the
 * mobile fade vars, so this plugin renders two live previews:
 *
 *   • Desktop: overlaid directly on ACF's real image thumbnail,
 *     driven by genes_hero_fade_start / genes_hero_fade_end.
 *   • Mobile: a stacked preview tile below the thumbnail, driven by
 *     genes_hero_fade_start_mobile / genes_hero_fade_end_mobile.
 *
 * Both update live as their sliders move, so the fade can be dialed
 * in per breakpoint without a save-and-check loop. Mirrors the header
 * banner's preview (eic-banner-mask-preview.php).
 *
 * Fields (subtype-browser-hero-fields.php):
 *   genes_hero_image               (image)         field_eic_genes_hero_image
 *   genes_hero_fade_start          (range 0..100)  field_eic_genes_hero_fade_start
 *   genes_hero_fade_end            (range 0..100)  field_eic_genes_hero_fade_end
 *   genes_hero_fade_start_mobile   (range 0..100)  field_eic_genes_hero_fade_start_mobile
 *   genes_hero_fade_end_mobile     (range 0..100)  field_eic_genes_hero_fade_end_mobile
 *
 * Location: wp-content/mu-plugins/eic-genes-hero-mask-preview.php
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("admin_enqueue_scripts", function ($hook) {
    // Only on the post/page edit screens where the ACF group can appear.
    if ($hook !== "post.php" && $hook !== "post-new.php") {
        return;
    }

    // --- Inline styles ---
    wp_register_style("eic-genes-hero-mask-preview", false);
    wp_enqueue_style("eic-genes-hero-mask-preview");
    wp_add_inline_style(
        "eic-genes-hero-mask-preview",
        <<<CSS
.acf-field[data-name="genes_hero_image"] .image-wrap {
    position: relative;
    overflow: hidden;
    border-radius: 6px;
}
.eic-gh-preview {
    position: absolute;
    inset: 0;
    pointer-events: none; /* never block ACF's edit/remove buttons */
    z-index: 1;
}
/* Stacked mobile preview: its own tile below the real thumbnail, showing the
   FULL image (not cropped) at the same size as the desktop preview, so the
   mask is the only thing that differs between the two. */
.eic-gh-preview-mobile {
    position: relative;
    display: block;
    clear: both;
    box-sizing: border-box;
    margin-top: 8px;
    max-width: 100%;
    border-radius: 6px;
    overflow: hidden;
    line-height: 0; /* remove the inline-image descender gap */
}
.eic-gh-preview-mobile__img {
    display: block;
    width: 100%;
    height: auto;
}
.eic-gh-preview-mobile__mask {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 1;
}
.eic-gh-preview__edge {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 0;
    z-index: 2;
    transform: translateX(-0.5px);
}
.eic-gh-preview__edge--start {
    border-left: 1px solid rgba(23, 71, 119, 0.85);
}
.eic-gh-preview__edge--end {
    border-left: 1px dashed rgba(23, 71, 119, 0.7);
}
.eic-gh-preview__tag {
    position: absolute;
    bottom: 4px;
    z-index: 2;
    transform: translateX(-50%);
    font-size: 9px;
    line-height: 1;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: #174777;
    background: rgba(255, 255, 255, 0.85);
    padding: 2px 4px;
    border-radius: 3px;
    white-space: nowrap;
}
.eic-gh-preview__label {
    position: absolute;
    top: 6px;
    left: 6px;
    z-index: 3;
    pointer-events: none;
    font-size: 9px;
    line-height: 1;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #174777;
    background: rgba(255, 255, 255, 0.8);
    padding: 3px 6px;
    border-radius: 4px;
}
CSS
    );

    // --- Inline script ---
    wp_register_script("eic-genes-hero-mask-preview", "", [], null, true);
    wp_enqueue_script("eic-genes-hero-mask-preview");
    wp_add_inline_script(
        "eic-genes-hero-mask-preview",
        <<<JS
(function () {
    "use strict";

    var IMG = "genes_hero_image";
    var START = "genes_hero_fade_start";
    var END = "genes_hero_fade_end";
    var START_M = "genes_hero_fade_start_mobile";
    var END_M = "genes_hero_fade_end_mobile";
    var BG = "#ffffff"; // matches --bg on the front end

    function field(name) {
        return document.querySelector('.acf-field[data-name="' + name + '"]');
    }

    function rangeVal(name, fallback) {
        var f = field(name);
        if (!f) return fallback;
        var el = f.querySelector('input[type="range"], input[type="number"]');
        var v = el ? parseFloat(el.value) : NaN;
        return isNaN(v) ? fallback : Math.max(0, Math.min(100, v));
    }

    function imageWrap() {
        var f = field(IMG);
        return f ? f.querySelector(".image-wrap") : null;
    }

    function gradient(s, e) {
        return (
            "linear-gradient(to right, " +
            BG + " 0%, " +
            BG + " " + s + "%, " +
            "transparent " + e + "%)"
        );
    }

    function placeMarkers(root, s, e) {
        var es = root.querySelector(".eic-gh-preview__edge--start");
        var ee = root.querySelector(".eic-gh-preview__edge--end");
        if (es) es.style.left = s + "%";
        if (ee) ee.style.left = e + "%";
        var ts = root.querySelector(".eic-gh-preview__tag--start");
        var te = root.querySelector(".eic-gh-preview__tag--end");
        if (ts) { ts.style.left = s + "%"; ts.textContent = s + "%"; }
        if (te) { te.style.left = e + "%"; te.textContent = e + "%"; }
    }

    var MARKERS =
        '<span class="eic-gh-preview__edge eic-gh-preview__edge--start"></span>' +
        '<span class="eic-gh-preview__edge eic-gh-preview__edge--end"></span>' +
        '<span class="eic-gh-preview__tag eic-gh-preview__tag--start"></span>' +
        '<span class="eic-gh-preview__tag eic-gh-preview__tag--end"></span>';

    var observer = null;

    // Desktop preview: overlay on ACF's real thumbnail.
    function updateDesktop(hasImage) {
        var wrap = imageWrap();
        if (!wrap) return;
        if (!hasImage) {
            var stale = wrap.querySelector(".eic-gh-preview");
            if (stale) stale.remove();
            var staleLabel = wrap.querySelector(".eic-gh-preview__label");
            if (staleLabel) staleLabel.remove();
            return;
        }
        var s = rangeVal(START, 0);
        var e = rangeVal(END, 45);
        if (e < s) e = s;

        var ov = wrap.querySelector(".eic-gh-preview");
        if (!ov) {
            ov = document.createElement("div");
            ov.className = "eic-gh-preview";
            ov.innerHTML = MARKERS;
            wrap.appendChild(ov);
        }
        if (!wrap.querySelector(".eic-gh-preview__label")) {
            var lab = document.createElement("div");
            lab.className = "eic-gh-preview__label";
            lab.textContent = "Desktop";
            wrap.appendChild(lab);
        }
        ov.style.background = gradient(s, e);
        placeMarkers(ov, s, e);
    }

    // Mobile preview: a standalone tile pinned directly below the real
    // thumbnail, at the thumbnail's own width, using the image as a
    // background so it never disturbs ACF's real <img> or its controls.
    function updateMobile(src) {
        var wrap = imageWrap();
        var parent = wrap
            ? wrap.parentNode
            : (function () {
                  var f = field(IMG);
                  return f ? f.querySelector(".acf-input") : null;
              })();
        if (!parent) return;

        var tile = parent.querySelector(".eic-gh-preview-mobile");

        // No image (or no thumbnail yet): remove any stale tile and bail.
        if (!src || !wrap) {
            if (tile) tile.remove();
            return;
        }

        var s = rangeVal(START_M, 55);
        var e = rangeVal(END_M, 100);
        if (e < s) e = s;

        if (!tile) {
            tile = document.createElement("div");
            tile.className = "eic-gh-preview-mobile";
            tile.innerHTML =
                '<img class="eic-gh-preview-mobile__img" alt="" />' +
                '<div class="eic-gh-preview-mobile__mask"></div>' +
                MARKERS +
                '<div class="eic-gh-preview__label">Mobile</div>';
            // Sit immediately after the thumbnail wrapper, in the same column.
            parent.insertBefore(tile, wrap.nextSibling);
        }

        // Align the tile exactly under the thumbnail. ACF's thumbnail can be
        // narrower than, and left-inset within, its uploader wrapper, so match
        // the thumbnail's real left offset (relative to the shared parent) and
        // width rather than assuming full-width. Robust to ACF's layout.
        var pRect = parent.getBoundingClientRect();
        var wRect = wrap.getBoundingClientRect();
        tile.style.maxWidth = "none";
        tile.style.marginLeft =
            Math.max(0, Math.round(wRect.left - pRect.left)) + "px";
        tile.style.width = Math.round(wRect.width || 300) + "px";
        // Show the FULL image (same source and width as the desktop preview),
        // its natural aspect ratio, so the mask can be judged against real
        // image content rather than a cropped strip.
        var im = tile.querySelector(".eic-gh-preview-mobile__img");
        if (im && im.getAttribute("src") !== src) im.setAttribute("src", src);
        var mask = tile.querySelector(".eic-gh-preview-mobile__mask");
        if (mask) mask.style.background = gradient(s, e);
        placeMarkers(tile, s, e);
    }

    function update() {
        if (observer) observer.disconnect();

        var wrap = imageWrap();
        var img = wrap ? wrap.querySelector("img") : null;
        var src = img ? img.getAttribute("src") : "";
        var hasImage = !!(wrap && img && src);

        updateDesktop(hasImage);
        updateMobile(hasImage ? src : "");

        reobserve();
    }

    function reobserve() {
        var f = field(IMG);
        if (f && observer) {
            observer.observe(f, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ["src", "class"]
            });
        }
    }

    // Live update as any of the four sliders (or their synced number inputs) move.
    document.addEventListener("input", function (ev) {
        var t = ev.target;
        if (!t || !t.closest) return;
        if (
            t.closest('.acf-field[data-name="' + START + '"]') ||
            t.closest('.acf-field[data-name="' + END + '"]') ||
            t.closest('.acf-field[data-name="' + START_M + '"]') ||
            t.closest('.acf-field[data-name="' + END_M + '"]')
        ) {
            update();
        }
    });

    function init() {
        var f = field(IMG);
        if (!f) return; // hero group not on this screen
        if (window.MutationObserver && !observer) {
            // Re-render when the image is added/removed/changed. The observer
            // is disconnected during update() so our own overlay edits can't
            // retrigger it.
            observer = new MutationObserver(function () { update(); });
        }
        update();
    }

    // ACF renders async; hook its ready action plus a couple of fallbacks.
    if (window.acf && window.acf.addAction) {
        acf.addAction("ready", init);
        acf.addAction("append", update);
    }
    document.addEventListener("DOMContentLoaded", init);
    setTimeout(init, 600);
    setTimeout(update, 1600);
})();
JS
    );
});
