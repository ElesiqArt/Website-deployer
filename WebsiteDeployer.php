<?php

	class WebsiteDeployer {
		private $config = NULL;
		private $EOL = ''; 
		
		private $extToMeth = Array(
			'html' 	=> 'applyHTMLConfig',
			'js' 	=> 'applyJSConfig',
			'css' 	=> 'applyCSSConfig',
			'json' 	=> 'applyJSONConfig'
		);
		
		public function __construct($configPath = NULL, $serverPath = NULL) {
			libxml_use_internal_errors(true);
			
			if(php_sapi_name() == 'cli') {
				$this->EOL = PHP_EOL;
			}
			else {
				$this->EOL = '<br>';
			}
			
			if($configPath && $serverPath) {
				$this->loadConfig($configPath, $serverPath);
			}
		}
		
		
		public function loadConfig($filePath, $configPath) {
			if(is_file($filePath)) {
				if(is_file($configPath)) {
					$this->config = json_decode(file_get_contents($filePath), TRUE);
					
					if(!$this->config) {
						echo 'Cannot read config file. Please verify the json format.';
					}
					else {
						if(!isset($this->config['ignore'])) {
							$this->config['ignore'] = Array();
						}
						
						if(!isset($this->config['file'])) {
							$this->config['file'] = Array();
						}
					}
					
					$configPath = json_decode(file_get_contents($configPath), TRUE);
					
					if(!$configPath) {
						echo 'Cannot read server config file. Please verify the json format.';
					}
					else {
						$this->config['root'] 		= str_replace('\\', '', $configPath['root']);
						$this->config['source'] 	= str_replace('\\', '', $configPath['source']);
						$this->config['location'] 	= str_replace('\\', '', $configPath['location']);
					}
				}
				else {
					echo 'Cannot load server config file "'. $configPath . '".';
					
					return FALSE;
				}
			}
			else {
				echo 'Cannot load config file "'. $filePath . '".';
				
				return FALSE;
			}
		}
		
		
		public function applyConfig() {
			if($this->config) {
				mkdir($this->config['location'], 777);
				
				return $this->applyConfigToDir($this->config['source']);
			}
			else {
				echo 'No config found.';
				
				return FALSE;
			}
		}
		
		private function applyConfigToDir($path, $parentPath = '/') {
			$dir = new DirectoryIterator($path);
			
			foreach($dir as $target) {
				if(!$target->isDot() && !$target->isLink()) {
					$path = str_replace('\\', '/', str_replace($this->config['source'], '', $target->getPathname()));
					
					echo 'Act path: "' . $target->getPathname() . '" (' . $path . ').' . $this->EOL;
					
					if(!in_array($path, $this->config['ignore'])) {
						if($target->isFile()) {
							$ext = strtolower($target->getExtension());
							$name = $this->config['location'] . $parentPath . $target->getBaseName();
							
							if(isset($this->extToMeth[$ext])) {
								$fromConfig = isset($this->config['file'][$ext]) ? $this->config['file'][$ext] : Array();
								
								$result = $this->{$this->extToMeth[$ext]}($target, $fromConfig);
								
								if($result) {
									file_put_contents($name, $result);
								}
								else {
									echo 'Error occured during file creation.';
								}
							}
							else {
								copy($target->getPathname(), $name);
							}
						}
						else if($target->isDir()) {
							mkdir($this->config['location'] . $parentPath . $target->getBaseName());
							
							$this->applyConfigToDir($target->getPathname(), $parentPath . $target->getBaseName() . '/');
						}
					}
				}
			}
			
			return TRUE;
		}
		
		
		private function applyHTMLConfig($target, $htmlConfig) {
			$pathName = str_replace('\\', '/', str_replace($this->config['source'], '', $target->getPathname()));
			
			if(isset($htmlConfig['fileParam']) && isset($htmlConfig['fileParam'][$pathName])) {
				$target = $htmlConfig['fileParam'][$pathName];
				
				if($target['mergeReplace']) {
					$target['replaceAttribute'] = array_merge_recursive($globalReplace, $target['replaceAttribute']);
				}
			}
			else {
				$target = Array(
					'path' 				=> $target->getPathname(),
					'type' 				=> $target->getExtension(),
					'minify' 			=> TRUE,
					'createGzip' 		=> TRUE,
					'replaceLink' 		=> TRUE,
					'replaceAttribute' 	=> $htmlConfig['replaceAttribute'],
					'replaceString'		=> $htmlConfig['replaceString']
				);
			}
			
			$doc = $this->createDocFromSource($target['path']);
			
			if($doc) {
				if(isset($target['replaceLink']) && $target['replaceLink']) {
					$this->replaceLinkFrom($doc, dirname($target['path']));
				}
				
				if($target['replaceAttribute']) {
					$this->replaceAttribute($doc, $target['replaceAttribute']);
				}
				
				$buffer = $doc->saveHTML($doc->documentElement);
				
				if($target['replaceString']) {
					$buffer = $this->replaceString($buffer, $target['replaceString']);
				}
				
			/*	ini_set('xdebug.var_display_max_depth', -1);
				ini_set('xdebug.var_display_max_children', -1);
				ini_set('xdebug.var_display_max_data', -1);
				var_dump($doc->saveHTML());*/
				
				return utf8_decode($buffer);
			}
			else {
				return FALSE;
			}
		}
		
		
		private function createDocFromSource($path, $isFragment = FALSE) {
			if(is_file($path)) {
				$htmlStr = file_get_contents($path);
				
				$doc = new DOMDocument;
				
				// LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD empêchent l'ajout du Doctype et des balises html+body
				// <div></div> résou un bug, lorsqu'un fragment de document est chargé, celui-ci ne peut pas avoir plusieurs noeuds à sa racine, il doit obligatoirement avoir un noeud général parent
				$doc->loadHTML($isFragment ? '<div>' . $htmlStr . '</div>' : $htmlStr, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
				
				return $doc;
			}
			else {
				echo 'Cannot found file "' . $path . '".' . $this->EOL;
				
				return FALSE;
			}
		}
		
		
		private function replaceLinkFrom(&$doc, $parentDir) {
			$xpath = new DOMXPath($doc);
			$link = $xpath->query("//link[@rel='import']");
			
			foreach($link as $domLink) {
				$url = $domLink->getAttribute('href');
				
				// Add support of curl for http|https
				// Add support of relative href
				// Add optional "root" var in config-file
				if(!preg_match('/^(http|https|\/)/i', $url)) {
					$url = $parentDir . '/' . $url;
				}
				else {
					$url = $this->config['root'] . $url;
				}
				
				$childDoc = $this->createDocFromSource($url, TRUE);
				
				if($childDoc) {
					if($this->replaceLinkFrom($childDoc, dirname($url))) {
						$childNodes = $childDoc->childNodes[0]->childNodes;	// [0] est le div ajouté par createDocFromSource
						
						foreach($childNodes as $child) {
							$clone = $doc->importNode($child, TRUE);
							
							$domLink->parentNode->insertBefore($clone, $domLink);
						}
						
						$domLink->parentNode->removeChild($domLink);
					}
					else {
						return FALSE;
					}
				}
				else {
					return FALSE;
				}
			}
			
			return TRUE;
		}
		
		private function replaceAttribute($doc, $objt) {
			$xpath = new DOMXPath($doc);
			
			foreach($objt as $cssSelector => $attrList) {
				$domList = $xpath->query($cssSelector);
				
				for($i = 0, $length = $domList->length; $i < $length; $i++) {
					foreach($attrList as $attr => $strList) {
						$actAttr = $domList[$i]->getAttribute($attr);
						
						if(strlen($actAttr)) {
							foreach($strList as $strFind => $strReplace) {
								if(is_string($strReplace)) {
									echo 'In: "' . $actAttr . '"' . $this->EOL;
									echo 'Search for: "' . $strFind . '"' . $this->EOL;
									echo 'Replace By: "' . $strReplace . '"' . $this->EOL;
									echo 'Result: "' . str_replace($strFind, $strReplace, $actAttr) . '"' . $this->EOL . $this->EOL;
									
									$actAttr = str_replace($strFind, $strReplace, $actAttr);
								}
								else if(!isset($strReplace['type']) || strtolower($strReplace['type']) == 'regexp') {
									$actAttr = preg_replace($strFind, $strReplace['replaceBy'], $actAttr);
								}
								
								$domList[$i]->setAttribute($attr, $actAttr);
							}
						}
					}
				}
			}
		}
		
		private function replaceString($string, $replace) {
			foreach($replace as $strFind => $strReplace) {
				if(is_string($strReplace)) {
					$string = str_replace(str_replace('\\', '', $strFind), $strReplace, $string);
				}
				else if(!isset($strReplace['type']) || strtolower($strReplace['type']) == 'regexp') {
					$string = preg_replace($strFind, $strReplace['replaceBy'], $string);
				}
			}
			
			return $string;
		}
		
		private function applyJSConfig($target, $htmlConfig) {
			$buffer = file_get_contents($target->getPathname());
			
			if(isset($htmlConfig['replaceString'])) {
				return $this->replaceString($buffer, $htmlConfig['replaceString']);
			}
			else {
				return $buffer;
			}
		}
		
		private function applyCSSConfig($target, $htmlConfig) {
			return $this->applyJSConfig($target, $htmlConfig);
		}
		
		private function applyJSONConfig($target, $htmlConfig) {
			return $this->applyJSConfig($target, $htmlConfig);
		}
	}
	
	
	if(php_sapi_name() == 'cli') {
		$deployer = new WebsiteDeployer($argv[1], $argv[2]);
	}
	else {
		$deployer = new WebsiteDeployer('config-file.json', 'config-path.json');
	}
	
	$deployer->applyConfig();
?>
