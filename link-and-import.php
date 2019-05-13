<?php
	// Ajouter standardisation des URL: "toto/" == "toto" afin de simplifier la réalisation des fichiers de config
	
	class WebsiteDeployer {
		private $config = NULL;
		private $variable = NULL;
		private $EOL = ''; 
		
		private $regExpHttp = '/^(www\.)/i';
		private $extToMeth = Array(
			'html' 	=> 'applyHTMLConfig',
			'js' 	=> 'applyJSConfig',
			'css' 	=> 'applyCSSConfig',
			'json' 	=> 'applyJSONConfig',
			'php'	=> 'applyPHPConfig'
		);
		
		public function __construct($configFile = NULL, $configPath = NULL, $configVariable = NULL) {
			libxml_use_internal_errors(true);
			
			if(php_sapi_name() == 'cli') {
				$this->EOL = PHP_EOL;
			}
			else {
				$this->EOL = '<br>';
			}
			
			if($configFile && $configPath) {
				if($this->loadConfig($configFile, $configPath, $configVariable)) {
					echo 'Config loaded.' . $this->EOL;
				}
				else {
					echo 'An error occured during config loading.' . $this->EOL;
				}
			}
		}
		
		
		private function getConfig($property, $from) {
			if(isset($from[$property])) {
				if(is_string($from[$property]) && isset($this->variable[$from[$property]])) {
					return $this->variable[$from[$property]];
				}
				else {
					return $from[$property];
				}
			}
			else {
				return FALSE;
			}
		}
		
		private function createDirectory($path) {
			if(!is_dir($path)) {
				if(mkdir($path, 0755)) {
					echo 'Directory "' . $path . '" creation success.' . $this->EOL;
				
					return TRUE;
				}
				else {
					echo 'Directory "' . $path . '" creation FAILED.' . $this->EOL;
					
					return FALSE;
				}
			}
			else {
				echo 'Directory "' . $path . '" already exists.' . $this->EOL;
				
				return TRUE;
			}
		}
		
		
		public function loadConfig($configFile, $configPath, $configVariable = NULL) {
			if(is_file($configFile)) {
				if(is_file($configPath)) {
					if($configVariable) {
						if(is_file($configVariable)) {
							$this->variable = json_decode(file_get_contents($configVariable), TRUE);
							
							if(!$this->variable) {
								echo 'Cannot read "config-variable" file. Pleade verify the json format.' . $this->EOL;
								
								return FALSE;
							}
						}
						else {
							echo 'Cannot found "config-variable" file "' . $variablePath . '".' . $this->EOL;
							
							return FALSE;
						}
					}
					else {
						$this->variable = Array();
					}
					
					$this->config = json_decode(file_get_contents($configFile), TRUE);
					
					if(!$this->config) {
						echo 'Cannot read "config-file" file. Please verify the json format.' . $this->EOL;
						
						return FALSE;
					}
					
					if(!isset($this->config['file'])) {
						$this->config['file'] = Array();
					}
					
					if(!isset($this->config['ignore'])) {
						$this->config['ignore'] = Array();
					}
					else {
						for($i = 0, $length = sizeof($this->config['ignore']); $i < $length; $i++) {
							$this->config['ignore'][$i] = $this->getConfig($i, $this->config['ignore']);
						}
					}
					
					$configPath = json_decode(file_get_contents($configPath), TRUE);
					
					if(!$configPath) {
						echo 'Cannot read "config-path" file. Please verify the json format.' . $this->EOL;
					}
					else {
						$this->config['root'] 		= str_replace('\\', '', $configPath['root']);
						$this->config['source'] 	= str_replace('\\', '', $configPath['source']);
						$this->config['location'] 	= str_replace('\\', '', $configPath['location']);
						
						return TRUE;
					}
				}
				else {
					echo 'Cannot found "config-path" file "'. $configPath . '".' . $this->EOL;
				}
			}
			else {
				echo 'Cannot found "config-file" file "'. $configFile . '".' . $this->EOL;
			}
			
			return FALSE;
		}
		
		
		public function applyConfig() {
			if($this->config) {
				return $this->createDirectory($this->getConfig('location', $this->config)) && $this->applyConfigToDir($this->getConfig('source', $this->config));
			}
			else {
				echo 'No config found.' . $this->EOL;
				
				return FALSE;
			}
		}
		
		private function applyConfigToDir($path, $parentPath = '/') {
			$dir = new DirectoryIterator($path);
			$source = $this->getConfig('source', $this->config);
			$location = $this->getConfig('location', $this->config);
			
			foreach($dir as $target) {
				if(!$target->isDot() && !$target->isLink()) {
					$path = str_replace('\\', '/', str_replace($source, '', $target->getPathname()));
					
					echo 'Act path: "' . $target->getPathname() . '" (' . $path . ').' . $this->EOL;
					
					if(!in_array($path, $this->config['ignore'])) {
						if($target->isFile()) {
							$ext = strtolower($target->getExtension());
							$name = $location . $parentPath . $target->getBaseName();
							
							if(isset($this->extToMeth[$ext])) {
								$fromConfig = $this->getConfig($ext, $this->config['file']);
								
								if(!$fromConfig) {
									$fromConfig = Array();
								}
								
								$result = $this->{$this->extToMeth[$ext]}($target, $fromConfig);
								
								if($result) {
									if(file_put_contents($name, $result)) {
										echo 'File created.' . $this->EOL;
									}
									else {
										echo 'Error occured during file creation.' . $this->EOL;
										
										return FALSE;
									}
								}
								else {
									echo 'Error occured during file generation.' . $this->EOL;
									
									return FALSE;
								}
							}
							else {
								copy($target->getPathname(), $name);
							}
						}
						else if($target->isDir()) {
							if(!$this->createDirectory($location . $parentPath . $target->getBaseName()) || !$this->applyConfigToDir($target->getPathname(), $parentPath . $target->getBaseName() . '/')) {
								return FALSE;
							}
						}
					}
					else {
						echo 'Path is ignored.' . $this->EOL;
					}
					
					echo $this->EOL;
				}
			}
			
			return TRUE;
		}
		
		
		private function applyHTMLConfig($target, $htmlConfig) {
			$pathName = str_replace('\\', '/', str_replace($this->getConfig('source', $this->config), '', $target->getPathname()));
			
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
					'replaceLink' 		=> TRUE,
					'replaceAttribute' 	=> $htmlConfig['replaceAttribute'],
					'replaceString'		=> $htmlConfig['replaceString']
				);
			}
			
			$doc = $this->createDocFrom($target['path']);
			
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
				
				return $buffer;
			}
			else {
				return FALSE;
			}
		}
		
		
		private function createDocFrom($path, $isFragment = FALSE) {
			if(preg_match($this->regExpHttp, $path)) {
				$curl = curl_init();
			
				curl_setopt_array($curl, Array(
					CURLOPT_URL				=> $path,
					CURLOPT_HTTPGET			=> TRUE,
					CURLOPT_RETURNTRANSFER	=> TRUE
				));
				
				$htmlStr = curl_exec($curl);
				$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
				
				curl_close($curl);
				
				if($code < 200 || $code >= 300) {
					echo 'Cannot load ressource at "' . $path . '". Error code: ' . $code . '; ' . curl_error($curl) . $this->EOL;
					
					return FALSE;
				}
			}
			else if(is_file($path)) {
				$htmlStr = file_get_contents($path);
			}
			else {
				echo 'Cannot load file "' . $path . '".' . $this->EOL;
				
				return FALSE;
			}
		
			$doc = new DOMDocument;
			
			// LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD empêchent l'ajout du Doctype et des balises html+body
			// <div></div> résou un bug, lorsqu'un fragment de document est chargé, celui-ci ne peut pas avoir plusieurs noeuds à sa racine, il doit obligatoirement avoir un noeud général parent
			$doc->loadHTML(mb_convert_encoding($isFragment ? '<div>' . $htmlStr . '</div>' : $htmlStr, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			
			echo 'Ressource from "' . $path . '" loaded.' . $this->EOL;
			
			return $doc;
		}
		
		
		private function replaceLinkFrom(&$doc, $parentDir) {
			$xpath = new DOMXPath($doc);
			$link = $xpath->query("//link[@rel='import']");
			
			foreach($link as $domLink) {
				$url = $domLink->getAttribute('href');
				
				if(strlen($url)) {
					if($url[0] == '/') {
						$url = $this->getConfig('root', $this->config) . $url;
					}
					else if(!preg_match($this->regExpHttp, $url)) {
						$url = $parentDir . '/' . $url;
					}
				}
				
				$childDoc = $this->createDocFrom($url, TRUE);
				
				if($childDoc) {
					if($this->replaceLinkFrom($childDoc, dirname($url))) {
						$childNodes = $childDoc->childNodes[0]->childNodes;	// [0] est le div ajouté par createDocFrom
						
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
									$strReplace = $this->getConfig($strFind, $strList);
									
									echo 'In string: "' . $actAttr . '"' . $this->EOL;
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
					$strReplace = $this->getConfig($strFind, $replace);
					
					echo 'Searching to replace "' . $strFind . '" by "' . $strReplace . '".' . $this->EOL;
					
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
		
		private function applyPHPConfig($target, $htmlConfig) {
			return $this->applyJSConfig($target, $htmlConfig);
		}
	}
	
	// Example: http://localhost/internal/website-deployer/link-and-import.php?file=../website/deploy/config-file.json&path=../website/deploy/config-path.json&variable=../website/deploy/config-variable.json
	
	if(php_sapi_name() == 'cli') {
		$deployer = new WebsiteDeployer($argv[1], $argv[2], $argv[3]);
	}
	else if(sizeof($_GET)) {
		if(isset($_GET['file']) && isset($_GET['path']) && isset($_GET['variable'])) {
			$deployer = new WebsiteDeployer($_GET['file'], $_GET['path'], $_GET['variable']);
		}
		else {
			throw new Error('One or more GET argument is missing. Arguments "file", "path" and "variable" are mandatory.');
		}
	}
	else {
		$deployer = new WebsiteDeployer('config-file.json', 'config-path.json', 'config-variable.json');
	}
	
	$deployer->applyConfig();
?>
