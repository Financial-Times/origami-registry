/*global require*/
/**
 * Syntax highlighting using highlight.js - https://highlightjs.org/
 */

const hljs = require('highlight.js');
hljs.configure({
	languages: [
		'javascript',
		'xml',
		'json',
		'scss',
		'css',
		'ruby',
		'diff',
		'makefile',
		'markdown',
		'php',
		'python',
		'java',
		'sql',
		'bash',
		'handlebars',
		'nginx',
		'perl',
		'scala'
	]
});


// TODO: This really ought to be restricted to elements within a techdocs component,
// but to do that properly, main.js needs to instantiate techdocs instances.  In theory
// You could have more than one techdocs per page.
function init() {

	hljs.initHighlighting();

	// Detect additions of new <pre><code> blocks to the page (TODO: should be instance DOM only)
	// and highlight them automatically
	if ('MutationObserver' in window) {
		const observer = new MutationObserver(function(mutations) {
			mutations.forEach(function(mut) {

				// Ignore any mutations that do not add new elements
				if (mut.type !== 'childList' || !mut.addedNodes.length) return;
				[].slice.call(mut.addedNodes).forEach(function(el) {

					// Ignore unless added element is a PRE > CODE
					el = (el.tagName === 'PRE' && el.querySelector('code')) || el;
					if (el.tagName === 'CODE' && el.parentNode.tagName === 'PRE') {
						hljs.highlightBlock(el);
					}
				});
			});
		});
		observer.observe(document.documentElement, {subtree: true, childList: true});
	}
}

module.exports = init;
