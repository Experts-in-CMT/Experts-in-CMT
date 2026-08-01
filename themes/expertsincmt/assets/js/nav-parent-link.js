/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/*!
 * ------------------------------------------------------------
 * NAVIGATION — PARENT LINK RESTORATION
 * ------------------------------------------------------------
 * Purpose:
 *   - Restores clickability for parent menu items in WP’s
 *     block-based Navigation system.
 *   - Allows submenus to open normally while parent items
 *     remain true links (desktop and mobile).
 *
 * IMPORTANT:
 *   The array NAV_ROUTES defines **parent-level menu items**
 *   whose links should NOT be blocked by the submenu toggle.
 *
 *   Whenever a new top-level menu item in the Site Editor has
 *   children AND should stay clickable, it MUST be added to
 *   NAV_ROUTES manually.
 *
 * Notes:
 *   - No interference with submenu toggles
 *   - No hover hijacking
 *   - Supports nested structures
 */

/* ------------------------------------------------------------
   Mobile behavior: parent items EXPAND their submenu on tap
   instead of navigating (expand-only). Shared by the main setup
   and the hard-route binder below so both paths behave the same.
   "Mobile" = the responsive overlay is open, or viewport <= 600px.
   ------------------------------------------------------------ */
const isMobileNav = () =>
	!!document.querySelector('.wp-block-navigation__responsive-container.is-menu-open') ||
	window.matchMedia('(max-width: 600px)').matches;

function eicToggleSubmenu(item) {
	if (!item) return false;
	const isOpen = item.classList.toggle('is-open');
	const toggle = item.querySelector('.wp-block-navigation-submenu__toggle');
	if (toggle) toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
	// Close sibling submenus when opening this one.
	const parentList = item.parentElement;
	if (isOpen && parentList) {
		Array.from(parentList.children).forEach((sib) => {
			if (sib !== item && sib.classList && sib.classList.contains('is-open')) {
				sib.classList.remove('is-open');
				const st = sib.querySelector('.wp-block-navigation-submenu__toggle');
				if (st) st.setAttribute('aria-expanded', 'false');
			}
		});
	}
	return isOpen;
}

// pointerup / touchend / click all fire for a single tap; only act on the
// first so the submenu toggles once per tap instead of flickering.
function eicMobileToggleGuarded(item) {
	if (!item) return;
	const now = Date.now();
	if (now - (item.__navLastToggle || 0) < 350) return;
	item.__navLastToggle = now;
	eicToggleSubmenu(item);
}

/* Parent links must navigate; hover opens, mouseout closes; chevron toggles; defeat TT25 interactivity */
(function() {
	const HOVER_DELAY = 120;
	const DEBUG = false; // set true to see console logs

	const log = (...args) => {
		if (DEBUG) console.log('[nav]', ...args);
	};

	function stripButtonStylesAndAttrs(scope) {
		scope.querySelectorAll('a.wp-block-navigation-item__content').forEach((a) => {
			// Remove pill classes
			a.classList.remove('wp-element-button', 'is-style-outline', 'is-style-fill');
			// Kill WP interactivity attributes that may hijack events
			Array.from(a.attributes).forEach((attr) => {
				if (attr.name.startsWith('data-wp-')) a.removeAttribute(attr.name);
			});
			a.removeAttribute('role');
			// Hard reset visuals so theme "button" look can't sneak in
			a.style.background = 'transparent';
			a.style.border = '0';
			a.style.boxShadow = 'none';
			a.style.outline = 'none';
			a.style.borderRadius = '0';
			a.style.padding = '';
			a.style.pointerEvents = 'auto';
			a.style.position = 'relative';
			a.style.zIndex = '2';
		});

		// Also remove interactivity attrs the theme may add on containers
		scope.querySelectorAll('.wp-block-navigation, .wp-block-navigation__container, .has-child, .wp-block-navigation-item').forEach((el) => {
			Array.from(el.attributes).forEach((attr) => {
				if (attr.name.startsWith('data-wp-')) el.removeAttribute(attr.name);
			});
		});
	}

	// Replace a node to drop any previously attached listeners
	function replaceToDropListeners(el) {
		const clone = el.cloneNode(true);
		el.replaceWith(clone);
		return clone;
	}

	function closeAll(menuRoot, except) {
		const openItems = menuRoot.querySelectorAll('.has-child.is-open');
		openItems.forEach((it) => {
			if (it === except) return;
			it.classList.remove('is-open');
			const t = it.querySelector('.wp-block-navigation-submenu__toggle');
			if (t) t.setAttribute('aria-expanded', 'false');
		});
	}

	function forceNavigate(link, event) {
		const href = link.getAttribute('href');
		if (!href || href === '#') return;
		if (event) {
			try {
				event.preventDefault();
			} catch {}
			try {
				event.stopPropagation();
			} catch {}
			try {
				if (event.stopImmediatePropagation) event.stopImmediatePropagation();
			} catch {}
		}
		log('navigate ->', href);
		window.location.assign(href);
	}

	function ensureToggle(item, link) {
		let toggle = item.querySelector('.wp-block-navigation__submenu-icon, .wp-block-navigation-submenu__toggle');
		if (!toggle) {
			toggle = document.createElement('button');
			toggle.type = 'button';
			toggle.className = 'wp-block-navigation-submenu__toggle';
			toggle.setAttribute('aria-expanded', 'false');
			toggle.setAttribute('aria-label', 'Open submenu');
			toggle.innerHTML = '<span class="nav-toggle-chevron" aria-hidden="true">▾</span>';
			link.insertAdjacentElement('afterend', toggle);
		}

		const submenu = item.querySelector('.wp-block-navigation__submenu-container');
		if (submenu && !submenu.id) submenu.id = 'submenu-' + Math.random().toString(36).slice(2, 9);
		if (submenu) toggle.setAttribute('aria-controls', submenu.id);

		toggle.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			const isOpen = item.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

			// Close siblings when opening this one
			const parentList = item.parentElement;
			if (isOpen && parentList) {
				Array.from(parentList.children).forEach((sib) => {
					if (sib !== item && sib.classList && sib.classList.contains('is-open')) {
						sib.classList.remove('is-open');
						const st = sib.querySelector('.wp-block-navigation-submenu__toggle');
						if (st) st.setAttribute('aria-expanded', 'false');
					}
				});
			}
		});
	}

	function setupItem(item) {
		// Bind to the parent's OWN content element (an <a> for linked parents,
		// a <button> for submenu-only parents like "About"). Must exclude the
		// child links inside the submenu container: a plain
		// querySelector('a...') grabs the first CHILD link for a button-parent,
		// which mis-binds the toggle to a child (tapping a child collapses the
		// parent) and leaves the parent itself without any handler.
		let link = Array.from(item.querySelectorAll('.wp-block-navigation-item__content'))
			.find((el) => !el.closest('.wp-block-navigation__submenu-container'));
		if (!link) return;

		// Remove WP interactivity attributes that bind handlers, from both the
		// item and its own content (button-parents like "About" keep native
		// data-wp toggles that would otherwise fight our tap handler).
		[item, link].forEach((node) => {
			Array.from(node.attributes).forEach((attr) => {
				if (attr.name.startsWith('data-wp-')) node.removeAttribute(attr.name);
			});
		});

		// Clone the anchor/button to drop any previously attached listeners
		link = replaceToDropListeners(link);

		// Authoritative navigation handlers (multiple to cover all cases)
		const navHandler = (e) => {
			if (e.target.closest('.wp-block-navigation-submenu__toggle')) return;
			// Mobile: expand/collapse the submenu instead of navigating.
			if (isMobileNav() && item.classList.contains('has-child')) {
				e.preventDefault();
				e.stopPropagation();
				if (e.stopImmediatePropagation) e.stopImmediatePropagation();
				eicMobileToggleGuarded(item);
				return;
			}
			forceNavigate(link, e);
		};

		link.addEventListener('pointerup', navHandler, {
			capture: true,
			passive: false
		});
		link.addEventListener('click', navHandler, {
			capture: true,
			passive: false
		});
		link.addEventListener('touchend', navHandler, {
			capture: true,
			passive: false
		});
		link.addEventListener('keydown', function(e) {
			if (e.key === 'Enter' || e.keyCode === 13) navHandler(e);
		}, {
			capture: true,
			passive: false
		});

		// Chevron toggle
		ensureToggle(item, link);

		// Desktop hover open/close. SKIPPED on mobile: iOS treats the first
		// tap on an element with hover handlers as a "hover" rather than a
		// click, which hijacked child-link taps and left the parent stuck.
		let timer = null;
		item.addEventListener('mouseenter', function() {
			if (isMobileNav()) return;
			const root = item.closest('.wp-block-navigation') || document;
			closeAll(root, item);
			item.classList.add('is-open');
			const t = item.querySelector('.wp-block-navigation-submenu__toggle');
			if (t) t.setAttribute('aria-expanded', 'true');
		});

		item.addEventListener('mouseleave', function() {
			if (isMobileNav()) return;
			clearTimeout(timer);
			timer = setTimeout(() => {
				item.classList.remove('is-open');
				const t = item.querySelector('.wp-block-navigation-submenu__toggle');
				if (t) t.setAttribute('aria-expanded', 'false');
			}, HOVER_DELAY);
		});
	}

	function outsideInteractions() {
		// Close any open menus when clicking outside
		document.addEventListener('click', function(e) {
			document.querySelectorAll('.wp-block-navigation').forEach((menu) => {
				if (!menu.contains(e.target)) closeAll(menu, null);
			});
		});

		// Close on Escape
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape') {
				document.querySelectorAll('.wp-block-navigation').forEach((menu) => closeAll(menu, null));
			}
		});
	}

	function init() {
		const menus = document.querySelectorAll('.wp-block-navigation');
		if (!menus.length) return;

		menus.forEach((menu) => {
			// First, neutralize WP interactivity and pill styles within this menu
			stripButtonStylesAndAttrs(menu);
			// Then, wire up each parent
			menu.querySelectorAll('.has-child').forEach(setupItem);
		});

		outsideInteractions();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

/* ============================================================
   ============  REGISTER CLICKABLE PARENT MENU ITEMS  =========
   ============  ADD NEW PARENT ITEMS TO NAV_ROUTES  ===========
   ============  REQUIRED FOR ANY PARENT WITH CHILDREN  =========
   ============================================================ */

/* --- HARD ROUTES: force parent item navigation --- */
const NAV_ROUTES = [

	{
		text: 'Genetics',
		url: '/genetics'
	},
	{
		text: 'Learn',
		url: '/learn'
	},
];


function labelText(el) {
	return (el.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
}

function bindHardRoute(el, url) {
	if (el.tagName === 'BUTTON') {
		const a = document.createElement('a');
		a.className = el.className;
		a.href = url;
		a.innerHTML = el.innerHTML;
		el.replaceWith(a);
		el = a;
	} else {
		el.setAttribute('href', url);
	}

	const navNow = (e) => {
		// Mobile: a hard-route parent with a submenu expands instead of
		// navigating, matching the expand-only behavior of every parent.
		const parentItem = el.closest('.has-child');
		if (isMobileNav() && parentItem) {
			e.preventDefault();
			e.stopPropagation();
			if (e.stopImmediatePropagation) e.stopImmediatePropagation();
			eicMobileToggleGuarded(parentItem);
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		if (e.stopImmediatePropagation) e.stopImmediatePropagation();
		window.location.assign(url);
	};

	['pointerup', 'click', 'touchend'].forEach((evt) =>
		el.addEventListener(evt, navNow, {
			capture: true,
			passive: false
		})
	);
	el.addEventListener(
		'keydown',
		(e) => {
			if (e.key === 'Enter' || e.keyCode === 13) navNow(e);
		}, {
			capture: true,
			passive: false
		}
	);
}

function applyNavRoutes() {
	document.querySelectorAll('.wp-block-navigation .has-child').forEach((item) => {
		const label = item.querySelector('.wp-block-navigation-item__content');
		if (!label) return;
		const t = labelText(label);
		NAV_ROUTES.forEach(({
			text,
			url
		}) => {
			if (t === text.toLowerCase()) {
				bindHardRoute(label, url);
			}
		});
	});
}

applyNavRoutes();
setTimeout(applyNavRoutes, 300);
/* --- END HARD ROUTES --- */
