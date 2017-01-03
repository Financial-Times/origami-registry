<?php
/**
 * Front controller
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

require __DIR__."/../app/global.php";

if (isset($_SERVER['HTTP_DEBUG']) and $_SERVER['HTTP_DEBUG'] == getenv('DEBUG_KEY')) {
	setenv('IS_DEV', true);
}

$app = new ServicesContainer();
Model::useDI($app);

$serveStart = microtime(true);
// get initial questions count
$init_questions = $app->db_read->queryRow('SHOW GLOBAL STATUS LIKE %s', 'Questions');

$router = new \FTLabs\Routing\Router($app, array(
	"controllersuffix" => ''
));

$router->setPattern('component', '[\w\-]+?');
$router->setPattern('searchterm', '[\w\-]+?');
$router->setPattern('version', '\d+[\d\.]*(?:\-(?:beta|rc)\.\d+)?');
$router->setPattern('demoname', '.*');
$router->setPattern('democodeaction', '(code|edit)');

// Registry pages
$router->route('/', '/components');
$router->route('/components', 'ComponentListing');
$router->route('/components/:component(?:\.(?<format>json|html))?', 'ComponentDetail');
$router->route('/components/:component@:version(?:\.(?<format>json|html))?', 'ComponentDetail');
$router->route('/components/:component/refresh', 'Refresh');
$router->route('/components/:component@:version/refresh', 'Refresh');
$router->route('/components/:component/demos/visual/:demoname', 'Embed');
$router->route('/components/:component@:version/demos/visual/:demoname', 'Embed');
$router->route('/components/:component/demos/:democodeaction/:demoname', 'Source');
$router->route('/components/:component@:version/demos/:democodeaction/:demoname', 'Source');
$router->route('/embedapi', 'EmbedApi');
$router->route('/embedapi.css', 'EmbedApi');

// Bower registry API
$router->route('/packages', 'Bower');
$router->route('/packages/:component', 'Bower');
$router->route('/packages/search/:searchterm', 'Bower');

// Web service metadata
$router->route('/__health', 'Health');
$router->route('/__gtg', 'Health');
$router->route('/__about', 'About');
$router->route('/__metrics', 'Metrics');
$router->route('/__error', 'ErrorTest');

$router->errorUnsupportedMethod('Errors\Error405');
$router->errorNoRoute('Errors\Error404');

$req = \FTLabs\Routing\Request::createFromGlobals();
$resp = new \FTLabs\Routing\Response();

$router->dispatch($req, $resp);

$serveDiff = microtime(true) - $serveStart;
$app->metrics->timing($app->metrics_prefix . 'serve.all.time', $serveDiff);

// How many questions
$final_questions = $app->db_read->queryRow('SHOW GLOBAL STATUS LIKE %s', 'Questions');
$questions_diff = intval($final_questions['Value']) - intval($init_questions['Value']);
$app->logger->info('Questions count', array( 'path' => $req->getPath(), 'referer' => $req->getHeader('referer'), 'value' => $questions_diff ) );
$app->metrics->measure($app->metrics_prefix . 'db.questions', $questions_diff);
$app->metrics->measure($app->metrics_prefix . 'db.final_questions', intval($final_questions['Value']));


/* Serve the response */

$resp->serve();

// Flush the metrics to graphite at the end of every load
// Use "@" to catch/dismiss any errors this throws as it shouldn't effect the running of the service.
// Would work the same as doing try { ... } catch {}, except fsockopen doesn't throw exceptions.
@$app->metrics->flush();
