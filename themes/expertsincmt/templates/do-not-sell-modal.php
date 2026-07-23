<?php
/**
 * © 2025 Kenneth Raymond — All rights reserved.
 * Part of the Experts in CMT WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ============================================================
 *  TEMPLATE: Do Not Sell My Information Modal
 * ------------------------------------------------------------
 *  Purpose:
 *  - Provides the HTML structure for the “Do Not Sell My Info”
 *    compliance modal, similar to Wix’s built-in behavior.
 *
 *  Notes:
 *  - Visibility is controlled via JS (added separately).
 *  - Styling is handled by /assets/css/do-not-sell.css (added later).
 *  - This file is loaded via the [do_not_sell_modal] shortcode.
 *
 *  Requirements:
 *  - Keep markup minimal and semantic.
 *  - aria- attributes and role settings support accessibility.
 * ============================================================
 */
?>

<div id="dnsmi-modal" class="dnsmi-modal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="dnsmi-modal__overlay" data-dnsmi-close></div>

    <div class="dnsmi-modal__dialog" role="document">
        <button class="dnsmi-modal__close" type="button" aria-label="Close" data-dnsmi-close>×</button>

        <h2 class="dnsmi-modal__title">Do Not Sell My Data</h2>

        <div class="dnsmi-modal__content">
            <p>You have the right to request that your personal data not be sold. To make this request, complete this form.</p>

            <form class="dnsmi-modal__form">

    <span class="dnsmi-modal__field"><label for="dnsmi-name" class="dnsmi-modal__label">Name</label><input id="dnsmi-name" name="dnsmi-name" type="text" class="dnsmi-modal__input"></span>

    <span class="dnsmi-modal__field"><label for="dnsmi-email" class="dnsmi-modal__label">Email</label><input id="dnsmi-email" name="dnsmi-email" type="email" class="dnsmi-modal__input"></span>

    <?php
    $dnsmi_btn = "Do Not Sell My Data";
    $dnsmi_btn = trim(
        wp_strip_all_tags(preg_replace("/<br\s*\/?>/i", "", $dnsmi_btn))
    );
    ?>
    <button type="submit" class="dnsmi-modal__submit"><?php echo esc_html(
        $dnsmi_btn
    ); ?></button>

</form>
<div class="dnsmi-modal__success" style="display:none;">
    <h3 class="dnsmi-modal__success-title">Request Received</h3>
    <p class="dnsmi-modal__success-message">
        Your request has been received. Your personal data will not be sold or transferred to a third party.
    </p>
    <button type="button" class="dnsmi-modal__submit" data-dnsmi-close>Close</button>
</div>


        </div>
    </div>
</div>
