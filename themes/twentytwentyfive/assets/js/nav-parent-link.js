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
		let link = item.querySelector('a.wp-block-navigation-item__content');
		if (!link) return; // If it's a <button>, fix in the Site Editor: transform to Link and set URL.

		// Remove WP interactivity attributes that bind handlers
		Array.from(item.attributes).forEach((attr) => {
			if (attr.name.startsWith('data-wp-')) item.removeAttribute(attr.name);
		});

		// Clone the anchor to drop any previously attached listeners
		link = replaceToDropListeners(link);

		// Authoritative navigation handlers (multiple to cover all cases)
		const navHandler = (e) => {
			if (e.target.closest('.wp-block-navigation-submenu__toggle')) return;
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

		// Desktop hover open/close
		let timer = null;
		item.addEventListener('mouseenter', function() {
			const root = item.closest('.wp-block-navigation') || document;
			closeAll(root, item);
			item.classList.add('is-open');
			const t = item.querySelector('.wp-block-navigation-submenu__toggle');
			if (t) t.setAttribute('aria-expanded', 'true');
		});

		item.addEventListener('mouseleave', function() {
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