<?php
/**
 * Demo source code
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace Controllers;

use \Origami\Component;
use \OrigamiRegistry;
use \FTLabs\HTTPRequest;
use \Masterminds\HTML5;

class Source extends BaseController {

	protected function validate() {
		if (!$this->component or !$this->version) return false;
	}

	public function get() {


		try {
			$url = $url = 'https://'.getenv('BUILD_SERVICE_HOST').'/v2/demos/'.$this->component->module_name.'@'.$this->version->tag_name.'/'.$this->routeargs['demoname'];
			$request = new HTTPRequest($url);
			$request->setTimeLimit(10);
			$response = $request->send();
			$code = $response->getBody();

			if ($response->getResponseStatusCode() !== 200) {
				throw new \Exception('HTTP Status '.$response->getResponseStatusCode().' from build service');
			}

			if (!$code) {
				throw new \Exception('Blank response from build service loading '.$url);
			}

			// Load the code into DOMDocument to validate it and so it can be reduced to the right chunk
			libxml_use_internal_errors(true);
			$dom = new \DomDocument();
			$html5 = new HTML5();
			$dom = $html5->loadHTML($code);
			$errors = libxml_get_errors();
			foreach ($errors as $i => $error) {
				if ($error->code == 801) unset($errors[$i]);  // Ignore unrecognised HTML tags
			}
		} catch (\Exception $e) {
			$errors = array(array('line'=>0, 'message'=>'Unable to load demo.  '.$e->getMessage()));
		}
		if ($errors) $this->addViewData('validationerrors', $errors);

		$this->resp->setCacheTTL(300);

		if ($this->routeargs['democodeaction'] == 'edit') {

			// Make build service resources full URLs so they work in JSBin
			// REVIEW:AB:20140324: This should be done by the build service, ideally
			$code = preg_replace('/( (?:href|src)=)([\'\"])(\/(?!\/)[^\\2]+\\2)/i', '$1$2//'.getenv('BUILD_SERVICE_HOST').'$3', $code);
			$this->addViewData('code', $code);
			$this->renderView('page_jsbinredirect');

		} elseif (!$errors) {

			// Try to find a marked demo chunk
			$finder = new \DomXPath($dom);
			$nodes = $finder->query("//*[contains(@class, 'o-registry-demo-chunk')]");
			if ($nodes->length) {
				$chunk = $nodes->item(0);
				$class = $chunk->attributes->getNamedItem('class')->nodeValue;
				$class = trim(preg_replace('/\s*o-registry-demo-chunk\s*/i', ' ', $class));
				// Replace multiple spaces with one
				$class = preg_replace('/ {2,}/', ' ', $class);
				if (!$class) {
					$chunk->removeAttribute('class');
				} else {
					$chunk->attributes->getNamedItem('class')->nodeValue = $class;
				}

			// No demo chunk.  Show whole <body> content
			} else {

				// Reduce to body only
				$bodynodes = $dom->getElementsByTagName('body');
				$chunk = $bodynodes->item(0);

				// Remove any top level <script> or <link> tags that load from Origami endpoints. These should not be needed by developers implementing the component in their product since they will either build them into their bundles or use the build service.
				$nodes = $chunk->childNodes;
				if ($nodes && $nodes->length) {
					for ($i = $nodes->length; --$i >= 0;) {
						$el = $nodes->item($i);
						$isscript = (isset($el->tagName) and $el->tagName == 'script');
						$isstyle = (isset($el->tagName) and $el->tagName == 'link');
						if ($isstyle or $isscript) {
							$istemplate = ($el->getAttribute('type') !== 'text/template');
							$url = $el->getAttribute('href') ? $el->getAttribute('href') : $el->getAttribute('src');
							if (($isscript and !$istemplate) or ($url and preg_match('/^((https?\:)?\/\/[\w\.\/]*origami\.ft\.com|\/\w)/', $url))) {
								$chunk->removeChild($nodes->item($i));
							}
						}
					}
				}
			}
			$code = $dom->saveHTML($chunk);

			// Unwrap the body tags (no way of doing this using DOM methods that I know of)
			$code = preg_replace('/^\s*<body(?: [^>]+)?>\s*(.*?)\s*<\/body>\s*$/si', '$1', $code);
			$this->resp->setJSON(array('code' => $code));

		} else {
			$this->resp->setJSON(array('errors' => $errors));
		}
	}
}
