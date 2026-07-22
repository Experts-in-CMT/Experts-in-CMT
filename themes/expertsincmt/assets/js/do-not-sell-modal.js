/**
 * © 2025 Kenneth Raymond — All rights reserved.
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ============================================================
 *  JS: Do Not Sell My Information Modal
 * ============================================================
 */

document.addEventListener('DOMContentLoaded', function() {

	/* ============================================================
	 *  MOVE MODAL TO <body> ROOT
	 * ============================================================ */
	const modal = document.getElementById('dnsmi-modal');
	if (modal && !modal.classList.contains('dnsmi-rebased')) {
		document.body.appendChild(modal);
		modal.classList.add('dnsmi-rebased');
	}
	if (!modal) return;

	const dialog = modal.querySelector('.dnsmi-modal__dialog');
	const closeBtn = modal.querySelector('.dnsmi-modal__close');

	/* ========================================================================
	 *  Success + Form references
	 * ======================================================================== */
	const form = modal.querySelector('.dnsmi-modal__form');
	const success = modal.querySelector('.dnsmi-modal__success');

	/* ========================================================================
	 *  COOKIE HELPERS
	 * ======================================================================== */
	function setCookie(name, value, days) {
		let d = new Date();
		d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
		document.cookie = name + "=" + value + ";expires=" + d.toUTCString() + ";path=/";
	}

	function getCookie(name) {
		let match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
		return match ? match[2] : null;
	}

	/* ========================================================================
	 *  Detect admin (logged-in users skip cookie logic)
	 * ======================================================================== */
	const isAdmin = document.body.classList.contains('logged-in');

	/* ========================================================================
	 *  Disable link if cookie present (non-admin only)
	 * ======================================================================== */
	const dnsmiLink = document.querySelector('a[href="#do-not-sell-my-info"]');

	if (!isAdmin && dnsmiLink && getCookie('dnsmi_submitted')) {
		dnsmiLink.textContent = "Your data will not be sold to any 3rd party.";
		dnsmiLink.removeAttribute('href');
		dnsmiLink.style.pointerEvents = 'none';
		dnsmiLink.style.opacity = '0.7';
	}

	/* ============================================================
	 *  Reset screens (used on EVERY close)
	 * ============================================================ */
	function dnsmiResetScreens() {
		if (form && success) {
			form.style.display = 'block';
			success.style.display = 'none';
			form.reset();

			// REMOVE success-active class
			modal.classList.remove('success-active');
		}
	}

	/* ========================================================================
	 *  OPEN HANDLER
	 * ======================================================================== */
	document
		.querySelectorAll('a[href="#do-not-sell-my-info"]')
		.forEach(link => {
			link.addEventListener('click', function(e) {
				e.preventDefault();
				modal.classList.add('is-active');
			});
		});

	/* ========================================================================
	 *  CLOSE HANDLERS
	 * ======================================================================== */

	closeBtn.addEventListener('click', () => {
		modal.classList.remove('is-active');
		dnsmiResetScreens();
	});

	modal.addEventListener('click', function(e) {
		if (!dialog.contains(e.target)) {
			modal.classList.remove('is-active');
			dnsmiResetScreens();
		}
	});

	document.addEventListener('keyup', function(e) {
		if (e.key === 'Escape') {
			modal.classList.remove('is-active');
			dnsmiResetScreens();
		}
	});

	modal.querySelectorAll('[data-dnsmi-close]').forEach(el => {
		el.addEventListener('click', () => {
			modal.classList.remove('is-active');
			dnsmiResetScreens();
		});
	});

	/* ========================================================================
	 *  SUBMIT → CLEAR FORM + SHOW SUCCESS SCREEN
	 * ======================================================================== */
	if (form && success) {
		form.addEventListener('submit', function(e) {
			e.preventDefault();

			if (!isAdmin) {
				setCookie('dnsmi_submitted', 'true', 365);
			}

			form.reset();
			form.style.display = 'none';
			success.style.display = 'block';

			// ADD success-active class
			modal.classList.add('success-active');

			if (!isAdmin && dnsmiLink) {
				dnsmiLink.textContent = "Your data will not be sold to any 3rd party.";
				dnsmiLink.removeAttribute('href');
				dnsmiLink.style.pointerEvents = 'none';
				dnsmiLink.style.opacity = '0.7';
			}
		});
	}

});