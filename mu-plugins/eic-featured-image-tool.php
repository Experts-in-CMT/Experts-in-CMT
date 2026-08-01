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
 * MU Plugin: EIC Featured Image Setter
 * ------------------------------------------------------------
 * Tools > Featured Image.
 *
 * Bulk-sets the native WordPress featured image (post thumbnail,
 * _thumbnail_id) across every post of a chosen post type.
 *
 * The image is chosen from THIS site's media library via the native
 * media picker, so nothing is hardcoded and the same code works after
 * an SFTP deploy (attachment IDs differ per environment; you just
 * pick the image again on each side).
 *
 * Dry-run gated; scope control (differ/empty/all).
 *
 * Location: wp-content/mu-plugins/eic-featured-image-tool.php
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!is_admin()) {
    return;
}

final class EIC_Featured_Image_Tool
{
    const CAP   = "manage_options";
    const NONCE = "eic_featured_image_tool";
    private static $hook = "";

    private static function post_types(): array
    {
        return [
            "subtype"     => "Subtype",
            "breathing"   => "CMT and Breathing",
            "what-is-cmt" => "What is CMT",
            "glossary"    => "Glossary",
            "page"        => "Page",
            "post"        => "Post",
        ];
    }

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
        add_action("admin_enqueue_scripts", [__CLASS__, "enqueue"]);
    }

    public static function menu(): void
    {
        self::$hook = add_management_page(
            "Featured Image",
            "Featured Image",
            self::CAP,
            "eic-featured-image",
            [__CLASS__, "render"]
        );
    }

    public static function enqueue($hook): void
    {
        if ($hook !== self::$hook) {
            return;
        }
        wp_enqueue_media();
        wp_register_script("eic-featured-image", false, ["jquery"], null, true);
        wp_enqueue_script("eic-featured-image");
        wp_add_inline_script("eic-featured-image", self::js());
    }

    private static function js(): string
    {
        return <<<'JS'
(function ($) {
  $(document).on('click', '#eic-pick-image', function (e) {
    e.preventDefault();
    var frame = wp.media({
      title: 'Select featured image',
      button: { text: 'Use this image' },
      library: { type: 'image' },
      multiple: false
    });
    frame.on('select', function () {
      var a = frame.state().get('selection').first().toJSON();
      $('#eic-image-id').val(a.id);
      var url = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
      $('#eic-image-preview').html(
        '<img src="' + url + '" style="max-width:160px;height:auto;border:1px solid #ccd0d4;border-radius:4px"><br><code>ID ' +
        a.id + ' \u00b7 ' + (a.filename || '') + '</code>'
      );
    });
    frame.open();
  });
})(jQuery);
JS;
    }

    private static function collect(string $type): array
    {
        $q = new WP_Query([
            "post_type"      => $type,
            "post_status"    => "any",
            "posts_per_page" => -1,
            "fields"         => "ids",
            "no_found_rows"  => true,
            "orderby"        => "title",
            "order"          => "ASC",
        ]);
        $rows = [];
        foreach ($q->posts as $id) {
            $cur = (int) get_post_thumbnail_id($id);
            $rows[] = ["id" => $id, "title" => get_the_title($id), "cur" => $cur];
        }
        return $rows;
    }

    private static function want(string $scope, int $cur, int $chosen): bool
    {
        if ($scope === "all") {
            return true;
        }
        if ($scope === "empty") {
            return $cur === 0;
        }
        return $cur === 0 || $cur !== $chosen;
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }
        $types = self::post_types();
        $type = isset($_POST["eic_post_type"]) ? sanitize_key($_POST["eic_post_type"]) : "subtype";
        if (!isset($types[$type])) {
            $type = "subtype";
        }
        $img = isset($_POST["eic_image_id"]) ? (int) $_POST["eic_image_id"] : 0;
        $scope = isset($_POST["scope"]) ? sanitize_key($_POST["scope"]) : "differs";
        $action = $_POST["eic_action"] ?? "";

        eic_admin_tool_open("Featured Image");
        echo "<p>Bulk-set the native WordPress featured image across all posts of one type. " .
            "Pick the image from this site's media library so it stays correct per environment.</p>";

        if ($action && check_admin_referer(self::NONCE)) {
            if ($img < 1 || !wp_attachment_is_image($img)) {
                echo '<div class="notice notice-error"><p>Select a valid image first.</p></div>';
            } elseif ($action === "dryrun") {
                self::do_dryrun($type, $img, $scope);
            } elseif ($action === "commit") {
                self::do_commit($type, $img, $scope);
            }
        }

        $preview = "";
        if ($img && wp_attachment_is_image($img)) {
            $thumb = wp_get_attachment_image($img, "thumbnail", false, [
                "style" => "max-width:160px;height:auto;border:1px solid #ccd0d4;border-radius:4px",
            ]);
            $fname = wp_basename(get_attached_file($img));
            $preview = $thumb . "<br><code>ID " . $img . " &middot; " . esc_html($fname) . "</code>";
        }

        echo '<hr><form method="post" style="margin:1em 0">';
        wp_nonce_field(self::NONCE);
        echo '<p><label>Post type: <select name="eic_post_type">';
        foreach ($types as $k => $label) {
            echo '<option value="' . esc_attr($k) . '"' . selected($type, $k, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><button type="button" class="button" id="eic-pick-image">Select image</button></p>';
        echo '<input type="hidden" id="eic-image-id" name="eic_image_id" value="' . esc_attr($img) . '">';
        echo '<div id="eic-image-preview" style="margin:8px 0">' . $preview . '</div>';
        echo '<p>Scope: <select name="scope">' .
            '<option value="differs"' . selected($scope, "differs", false) . '>Only posts that differ or are empty</option>' .
            '<option value="empty"' . selected($scope, "empty", false) . '>Only posts with no featured image</option>' .
            '<option value="all"' . selected($scope, "all", false) . '>All posts of this type</option>' .
            '</select></p>';
        echo '<p><button class="button button-primary" name="eic_action" value="dryrun">Dry run (no writes)</button></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have reviewed the dry run and want to write.</label></p>';
        echo '<button class="button button-primary" name="eic_action" value="commit">Commit</button>';
        echo '</form>';
        eic_admin_tool_close();
    }

    private static function do_dryrun(string $type, int $img, string $scope): void
    {
        $rows = self::collect($type);
        $change = 0;
        $label = self::post_types()[$type];
        $fname = esc_html(wp_basename(get_attached_file($img)));
        echo "<h2>Dry run — " . count($rows) . " " . esc_html($label) . " post(s)</h2>";
        echo "<p>Proposed image: <code>ID " . $img . " &middot; " . $fname . "</code></p>";
        echo '<table class="widefat striped"><thead><tr><th>Post</th><th>Current featured</th><th>Change?</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            // Mirror do_commit(): the preview must gate on the same
            // scope logic the commit uses, or counts will not match.
            $will = self::want($scope, $r["cur"], $img);
            if ($will) {
                $change++;
            }
            $hi = $will ? ' style="background:#fff3cd"' : "";
            $cur = $r["cur"] ? ("ID " . $r["cur"]) : "(none)";
            echo "<tr{$hi}><td><strong>" . esc_html($r["title"]) . "</strong></td><td>" .
                esc_html($cur) . "</td><td>" . ($will ? "set" : "ok") . "</td></tr>";
        }
        echo "</tbody></table>";
        echo "<p><strong>" . $change . "</strong> post(s) would change.</p>";
    }

    private static function do_commit(string $type, int $img, string $scope): void
    {
        if (empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }
        $rows = self::collect($type);
        $set = 0;
        foreach ($rows as $r) {
            if (self::want($scope, $r["cur"], $img)) {
                set_post_thumbnail($r["id"], $img);
                $set++;
            }
        }
        echo '<div class="notice notice-success"><p><strong>Done.</strong> Set the featured image on ' .
            $set . ' ' . esc_html(self::post_types()[$type]) . ' post(s).</p></div>';
    }
}

EIC_Featured_Image_Tool::init();
