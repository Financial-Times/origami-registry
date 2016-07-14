// Require module
// require('o-header');
require('o-date');
require('o-tabs');
require('o-overlay');

require('./filterbar');
require('./click-helper');
require('./demos');

// Wait until the page has loaded
if (document.readyState === 'interactive' || document.readyState === 'complete') {
	document.dispatchEvent(new CustomEvent('o.DOMContentLoaded'));
}
document.addEventListener('DOMContentLoaded', function() {
	// Dispatch a custom event that will tell all required modules to initialise
	document.dispatchEvent(new CustomEvent('o.DOMContentLoaded'));
});
