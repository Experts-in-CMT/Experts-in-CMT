<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/**
 * Plugin Name: EIC Gene Browser Hero Mask Preview
 * Description: Live fade/mask preview on the Gene Browser hero image thumbnail in the ACF meta box. Drag the Desktop and Mobile "Fade Start %" / "Fade End %" sliders and watch each mask move, matching the front-end hero ::after gradient at that breakpoint. Editor-only; renders no front-end output.
 * Version: 1.0.0
 * Author: Kenneth Raymond
 *
 * ------------------------------------------------------------
 * Gene Browser Hero — Editor Mask Preview
 * ------------------------------------------------------------
 * Sibling of eic-vmech-hero-mask-preview.php. The hero
 * ([gene_browser_hero]) masks its background image with a
 * left-to-right gradient driven by --gbx-hero-fade-start/end
 * (desktop) and the mobile variants (<= 600px), so this plugin
 * renders two live previews on the ACF thumbnail.
 *
 * Fields (gene-browser-hero-fields.php):
 *   gbx_hero_image                (image)
 *   gbx_hero_fade_start           (range 0..100)
 *   gbx_hero_fade_end             (range 0..100)
 *   gbx_hero_fade_start_mobile    (range 0..100)
 *   gbx_hero_fade_end_mobile      (range 0..100)
 *
 * Location: wp-content/mu-plugins/eic-gbx-hero-mask-preview.php
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("admin_enqueue_scripts", function ($hook) {
    if ($hook !== "post.php" && $hook !== "post-new.php") {
        return;
    }

    wp_register_style("eic-gbx-hero-mask-preview", false);
    wp_enqueue_style("eic-gbx-hero-mask-preview");
    wp_add_inline_style(
        "eic-gbx-hero-mask-preview",
        <<<CSS
.acf-field[data-name="gbx_hero_image"] .image-wrap {
    position: relative;
    overflow: hidden;
    border-radius: 6px;
}
.eic-gbxh-preview {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 1;
}
.eic-gbxh-preview-mobile {
    position: relative;
    display: block;
    clear: both;
    box-sizing: border-box;
    margin-top: 8px;
    max-width: 100%;
    border-radius: 6px;
    overflow: hidden;
    line-height: 0;
}
.eic-gbxh-preview-mobile__img {
    display: block;
    width: 100%;
    height: auto;
}
.eic-gbxh-preview-mobile__mask {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 1;
}
.eic-gbxh-preview__edge {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 0;
    z-index: 2;
    transform: translateX(-0.5px);
}
.eic-gbxh-preview__edge--start {
    border-left: 1px solid rgba(23, 71, 119, 0.85);
}
.eic-gbxh-preview__edge--end {
    border-left: 1px dashed rgba(23, 71, 119, 0.7);
}
.eic-gbxh-preview__tag {
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
.eic-gbxh-preview__label {
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

    wp_register_script("eic-gbx-hero-mask-preview", false, [], null, true);
    wp_enqueue_script("eic-gbx-hero-mask-preview");
    wp_add_inline_script(
        "eic-gbx-hero-mask-preview",
        <<<JS
(function () {
    "use strict";

    var IMG = "gbx_hero_image";
    var START = "gbx_hero_fade_start";
    var END = "gbx_hero_fade_end";
    var START_M = "gbx_hero_fade_start_mobile";
    var END_M = "gbx_hero_fade_end_mobile";
    var BG = "#ffffff";

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
        var es = root.querySelector(".eic-gbxh-preview__edge--start");
        var ee = root.querySelector(".eic-gbxh-preview__edge--end");
        if (es) es.style.left = s + "%";
        if (ee) ee.style.left = e + "%";
        var ts = root.querySelector(".eic-gbxh-preview__tag--start");
        var te = root.querySelector(".eic-gbxh-preview__tag--end");
        if (ts) { ts.style.left = s + "%"; ts.textContent = s + "%"; }
        if (te) { te.style.left = e + "%"; te.textContent = e + "%"; }
    }

    var MARKERS =
        '<span class="eic-gbxh-preview__edge eic-gbxh-preview__edge--start"></span>' +
        '<span class="eic-gbxh-preview__edge eic-gbxh-preview__edge--end"></span>' +
        '<span class="eic-gbxh-preview__tag eic-gbxh-preview__tag--start"></span>' +
        '<span class="eic-gbxh-preview__tag eic-gbxh-preview__tag--end"></span>';

    var observer = null;

    function updateDesktop(hasImage) {
        var wrap = imageWrap();
        if (!wrap) return;
        if (!hasImage) {
            var stale = wrap.querySelector(".eic-gbxh-preview");
            if (stale) stale.remove();
            var staleLabel = wrap.querySelector(".eic-gbxh-preview__label");
            if (staleLabel) staleLabel.remove();
            return;
        }
        var s = rangeVal(START, 33);
        var e = rangeVal(END, 66);
        if (e < s) e = s;

        var ov = wrap.querySelector(".eic-gbxh-preview");
        if (!ov) {
            ov = document.createElement("div");
            ov.className = "eic-gbxh-preview";
            ov.innerHTML = MARKERS;
            wrap.appendChild(ov);
        }
        if (!wrap.querySelector(".eic-gbxh-preview__label")) {
            var lab = document.createElement("div");
            lab.className = "eic-gbxh-preview__label";
            lab.textContent = "Desktop";
            wrap.appendChild(lab);
        }
        ov.style.background = gradient(s, e);
        placeMarkers(ov, s, e);
    }

    function updateMobile(src) {
        var wrap = imageWrap();
        var parent = wrap
            ? wrap.parentNode
            : (function () {
                  var f = field(IMG);
                  return f ? f.querySelector(".acf-input") : null;
              })();
        if (!parent) return;

        var tile = parent.querySelector(".eic-gbxh-preview-mobile");

        if (!src || !wrap) {
            if (tile) tile.remove();
            return;
        }

        var s = rangeVal(START_M, 55);
        var e = rangeVal(END_M, 100);
        if (e < s) e = s;

        if (!tile) {
            tile = document.createElement("div");
            tile.className = "eic-gbxh-preview-mobile";
            tile.innerHTML =
                '<img class="eic-gbxh-preview-mobile__img" alt="" />' +
                '<div class="eic-gbxh-preview-mobile__mask"></div>' +
                MARKERS +
                '<div class="eic-gbxh-preview__label">Mobile</div>';
            parent.insertBefore(tile, wrap.nextSibling);
        }

        var pRect = parent.getBoundingClientRect();
        var wRect = wrap.getBoundingClientRect();
        tile.style.maxWidth = "none";
        tile.style.marginLeft =
            Math.max(0, Math.round(wRect.left - pRect.left)) + "px";
        tile.style.width = Math.round(wRect.width || 300) + "px";
        var im = tile.querySelector(".eic-gbxh-preview-mobile__img");
        if (im && im.getAttribute("src") !== src) im.setAttribute("src", src);
        var mask = tile.querySelector(".eic-gbxh-preview-mobile__mask");
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
        if (!f) return;
        if (window.MutationObserver && !observer) {
            observer = new MutationObserver(function () { update(); });
        }
        update();
    }

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
