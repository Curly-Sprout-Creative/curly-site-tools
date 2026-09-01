/**
 * Fix background-attachment on iOS: swap .fixed-bg to .scroll-bg.
 *
 * Snippet 16.
 */
(function () {
	'use strict';

	function applyScrollBgOnIOS() {
		var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
		if (isIOS) {
			var fixedElements = document.querySelectorAll('.fixed-bg');
			fixedElements.forEach(function (element) {
				element.classList.add('scroll-bg');
			});
		}
	}

	document.addEventListener('DOMContentLoaded', applyScrollBgOnIOS);
})();
