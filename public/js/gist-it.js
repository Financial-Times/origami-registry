/**
 * Display Gist-it gists
 */

export function gistIt() {

	const hljs = require('highlight.js');

	[].slice.call(document.querySelectorAll('.o-techdocs-gist')).forEach(function(el) {

		const repo = el.getAttribute('data-repo');
		const branch = el.getAttribute('data-branch') || 'master';
		const path = el.getAttribute('data-path');
		const callbackName = "oTechdocsGistIt"+Math.floor(Math.random()*10000000)+(new Date()).getTime();

		const url = "//gist-it.appspot.com/github/" + repo + "/blob/" + branch + path +"?footer=0&callback=" + callbackName;

		window[callbackName] = function(content) {

			// Extract just the prettyprint bit, which can then use techdocs standard styling
			content = content.replace(/^[\s\S]*(<pre [^>]*>)([\s\S]*?<\/pre>)[\s\S]*$/, "<pre><code>$2");
			content = content.replace(/<\/pre>$/, "</code></pre>");

			el.innerHTML = content;
			window[callbackName] = undefined;

			// Re-run highlighter so that the new content is highlighted
			hljs.highlightBlock(el.querySelector('code'));
		};

		const sc = document.createElement('script'); sc.src = url;
		const s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(sc, s);
	});
};
