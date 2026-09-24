/**
 * Open offsite links in a new tab with rel="noopener noreferrer".
 *
 * Snippet 23.
 *
 * Builders (Oxygen/Breakdance) render links with an explicit target="_self",
 * so treat "_self" (as well as an empty/absent target) as "no target set" and
 * override it. A MutationObserver also covers links added after load (menus,
 * lightboxes, AJAX content).
 */
(function () {
	'use strict';

	var siteHostname = window.location.hostname;

	function needsNewTab(link) {
		try {
			var host = link.hostname;

			// Same host, or no hostname (mailto:, tel:, #, …): leave alone.
			if (!host || host === siteHostname) {
				return false;
			}

			// Respect an explicit non-_self target; override _self/empty/absent.
			var target = link.getAttribute('target');
			return !target || target === '_self';
		} catch (e) {
			return false;
		}
	}

	function processLink(link) {
		if (!needsNewTab(link)) {
			return;
		}
		link.setAttribute('target', '_blank');
		link.setAttribute('rel', 'noopener noreferrer');
	}

	function processAll(root) {
		var links = (root || document).querySelectorAll('a');
		for (var i = 0; i < links.length; i++) {
			processLink(links[i]);
		}
	}

	function start() {
		processAll(document);

		if (typeof MutationObserver === 'undefined') {
			return;
		}

		var observer = new MutationObserver(function (mutations) {
			for (var m = 0; m < mutations.length; m++) {
				var added = mutations[m].addedNodes;
				for (var i = 0; i < added.length; i++) {
					var node = added[i];
					if (node.nodeType !== 1) {
						continue;
					}
					if (node.tagName === 'A') {
						processLink(node);
					} else if (node.querySelectorAll) {
						processAll(node);
					}
				}
			}
		});

		observer.observe(document.body, { childList: true, subtree: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
