/**
 * Open offsite links in a new tab with rel="noopener noreferrer".
 *
 * Snippet 23.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var siteHostname = window.location.hostname;
		var links = document.querySelectorAll('a');

		links.forEach(function (link) {
			try {
				var linkHostname = link.hostname;

				// Different hostname from the current site, and no target set.
				if (linkHostname && linkHostname !== siteHostname && !link.getAttribute('target')) {
					link.setAttribute('target', '_blank');
					link.setAttribute('rel', 'noopener noreferrer');
				}
			} catch (e) {
				// Malformed hrefs (mailto:, #, etc.) have no hostname property.
				console.error('Error processing link:', link.href, e);
			}
		});
	});
})();
