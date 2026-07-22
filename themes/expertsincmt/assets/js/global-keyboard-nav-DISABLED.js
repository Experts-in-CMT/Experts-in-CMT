/**
 * Copyright (c) 2025 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the expertsincmt WordPress theme.
 * Do not copy, modify, or redistribute without permission.
 *
 * ============================================================
 * Accessibility — Global Keyboard Navigation System
 * ------------------------------------------------------------
 * Status: Not currently enabled in production.
 *
 * Purpose:
 *   - Arrow-key + WASD spatial navigation (nearest-neighbor focus)
 *   - Smooth scrolling between focusable elements
 *   - ESC key exits form fields
 *   - Live region announcements for screen readers
 *
 * Notes:
 *   - Safe to keep in the repository in its disabled state
 *   - Before re-enabling, confirm:
 *       • No interference with AJAX-driven loop updates
 *       • Proper cooperation with loop focus targets (#results roots)
 *       • Harmony with future modal/off-canvas components
 * ============================================================
 */


/* =========================================================
   Global Keyboard Navigation — Arrow Keys + WASD
   - Spatial navigation using nearest neighbor logic
   - Smooth scrolling between focus targets
   - Escape exits form fields
   - ARIA live region announces focus for screen readers
   ========================================================= */
(function() {
	const focusableSelector = [
		'a[href]',
		'button:not([disabled])',
		'input:not([disabled]):not([type="hidden"])',
		'select:not([disabled])',
		'textarea:not([disabled])',
		'[tabindex]:not([tabindex="-1"])'
	].join(',');

	const isVisible = (el) => {
		if (!el) return false;
		if (el.offsetParent === null) return false;
		const r = el.getBoundingClientRect();
		return r.width > 0 && r.height > 0;
	};

	const getFocusable = () =>
		Array.from(document.querySelectorAll(focusableSelector)).filter(isVisible);

	const isTypingContext = (ev) => {
		const t = ev.target;
		if (!t) return false;
		if (t.isContentEditable) return true;
		const tag = t.tagName;
		return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
	};

	const center = (el) => {
		const r = el.getBoundingClientRect();
		return {
			x: r.left + r.width / 2,
			y: r.top + r.height / 2
		};
	};

	function findTarget(items, current, dir) {
		const c0 = center(current);
		const EPS = 2;

		const inDir = {
			right: el => center(el).x > c0.x + EPS,
			left: el => center(el).x < c0.x - EPS,
			down: el => center(el).y > c0.y + EPS,
			up: el => center(el).y < c0.y - EPS,
		} [dir];

		const dist = {
			right: el => ({
				primary: Math.max(0, center(el).x - c0.x),
				cross: Math.abs(center(el).y - c0.y)
			}),
			left: el => ({
				primary: Math.max(0, c0.x - center(el).x),
				cross: Math.abs(center(el).y - c0.y)
			}),
			down: el => ({
				primary: Math.max(0, center(el).y - c0.y),
				cross: Math.abs(center(el).x - c0.x)
			}),
			up: el => ({
				primary: Math.max(0, c0.y - center(el).y),
				cross: Math.abs(center(el).x - c0.x)
			}),
		} [dir];

		const ALPHA = 0.25;
		let best = null;
		let bestScore = Infinity;

		for (const el of items) {
			if (el === current) continue;
			if (!inDir(el)) continue;

			const d = dist(el);
			if (d.primary <= 0) continue;

			const score = d.primary + ALPHA * d.cross;

			if (score < bestScore) {
				best = el;
				bestScore = score;
			}
		}

		return best;
	}

	// Spatial navigation handler
	document.addEventListener('keydown', (e) => {
		if (isTypingContext(e)) return;

		const items = getFocusable();
		if (!items.length) return;

		const current = document.activeElement;

		const key = (e.key || '').toLowerCase();
		const code = e.code || '';

		const wantRight = (key === 'arrowright') || (code === 'KeyD');
		const wantLeft = (key === 'arrowleft') || (code === 'KeyA');
		const wantDown = (key === 'arrowdown') || (code === 'KeyS');
		const wantUp = (key === 'arrowup') || (code === 'KeyW');

		const isNav = wantRight || wantLeft || wantDown || wantUp;

		// No current focus? Start at first focusable
		if (!items.includes(current) && isNav) {
			e.preventDefault();
			e.stopPropagation();

			const first = items[0];
			first.scrollIntoView({
				behavior: 'smooth',
				block: 'center',
				inline: 'center'
			});
			setTimeout(() => {
				first.focus({
					preventScroll: true
				});
				announceForScreenReader(first);
			}, 100);
			return;
		}

		let dir = null;
		if (wantRight) dir = 'right';
		else if (wantLeft) dir = 'left';
		else if (wantDown) dir = 'down';
		else if (wantUp) dir = 'up';
		if (!dir) return;

		const target = findTarget(items, current, dir);
		if (target) {
			e.preventDefault();
			e.stopPropagation();

			target.scrollIntoView({
				behavior: 'smooth',
				block: 'center',
				inline: 'center'
			});

			setTimeout(() => {
				target.focus({
					preventScroll: true
				});
				announceForScreenReader(target);
			}, 100);
		}
		// Else let arrow keys scroll naturally
	});

	// ESC exits form fields (optional)
	document.addEventListener('keydown', (e) => {
		const t = e.target;
		if (!t) return;

		const tag = t.tagName;
		const isEditable = t.isContentEditable;

		const isFormField =
			tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || isEditable;

		if (e.key === 'Escape' && isFormField) {
			t.blur();
		}
	});

	// Announces focus target for screen reader users
	function announceForScreenReader(el) {
		const live = document.getElementById('screenreader-nav-status');
		if (!live) return;

		const label = el.getAttribute('aria-label') ||
			el.getAttribute('alt') ||
			el.getAttribute('title') ||
			el.innerText ||
			el.textContent ||
			el.tagName;

		live.textContent = `Focused: ${label.trim()}`;
	}
})();