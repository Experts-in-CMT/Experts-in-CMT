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
 * MU Plugin: EIC Security Headers
 * ------------------------------------------------------------
 * Baseline response headers on every front-end response. The
 * 2026-09-10 pass found production serving none of these; the
 * only CSP directive in front was upgrade-insecure-requests.
 *
 *   Strict-Transport-Security  TLS responses only (is_ssl), so a
 *                              local http site never pins itself
 *   X-Content-Type-Options     no MIME sniffing
 *   X-Frame-Options            no framing off-site (nothing on
 *                              the platform is meant to be embedded)
 *   Referrer-Policy            full URL to same origin, origin
 *                              only elsewhere
 *   Permissions-Policy         camera, microphone, geolocation,
 *                              payment closed; the site uses none
 *   X-Powered-By               removed
 *
 * Scope, deliberately narrow: headers only. XML-RPC, author
 * archives, and the REST users endpoint are already closed
 * upstream on production, so nothing here duplicates them.
 *
 * Location: wp-content/mu-plugins/eic-security-headers.php
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("send_headers", "eic_security_headers");
function eic_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    if (is_ssl()) {
        header("Strict-Transport-Security: max-age=31536000");
    }
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()");
    header_remove("X-Powered-By");
}
