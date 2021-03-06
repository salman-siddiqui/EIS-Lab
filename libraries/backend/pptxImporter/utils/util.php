<?php
	require_once ROOT . DS .'libraries'. DS .'backend'. DS .'pptxImporter'. DS .'utils'. DS .'tbszip.php';

	class Util {
	
		// files' paths (constants)
		static public $CORE_XML = 'docProps/core.xml';
		static public $PRESENTATION_XML = 'ppt/presentation.xml';
		static public $PRESENTATION_XML_RELS = 'ppt/_rels/presentation.xml.rels';
		static public $APP_XML = 'docProps/app.xml';
		static public $SLIDE_PATH = 'ppt/slides/';
		static public $LAYOUT_PATH = 'ppt/slideLayouts/';
		static public $SLIDE_RELS_PATH = 'ppt/slides/_rels/';
		static public $LAYOUT_RELS_PATH = 'ppt/slideLayouts/_rels/';
		
		// types constants
		static public $LAYOUT_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout';
		static public $SLIDEMASTER_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster';

                // shape placeholder types
                static public $ST_PLACEHOLDERTYPE = array('body', 'chart', 'clipArt', 'ctrTitle', 'dgm', 'dt', 'ftr',
                    'hdr', 'media', 'obj', 'pic', 'sldImg', 'sldNum', 'subTitle', 'tbl', 'title');
		
		// General presentation properties	
		static public $_pptx_uri;
		static public $_canvas;
		static public $_import_with_style;
		
		// Auxilary utilities
		static private $_zip;
		static private $_domxpath;
		
		// Canvas parameters
		static public $_canvas_width = 600;
		static public $_canvas_height = 450;
	
		static public function readFileToDOMDocument($file_uri) {
			self::$_zip = new clsTbsZip();
			self::$_zip->Open(self::$_pptx_uri);
			$dom_document = new DOMDocument('1.0');
			$dom_document->loadXML(self::$_zip->FileRead($file_uri), LIBXML_NOBLANKS);
			self::$_zip->Close();
			
			return $dom_document;
		}
		
		static public function evaluateQueryOn(	DOMDocument $dom_document, DOMElement $dom_element, $query, $namespaces = array()) {
			self::$_domxpath = new DOMXpath($dom_document);
			if(!empty($namespaces)) {
				foreach($namespaces as $namespace) {
					self::$_domxpath->registerNamespace($namespace['abbr'], $namespace['uri']);
				}
			}
			return self::$_domxpath->evaluate($query, $dom_element);
		}
		
		static public function countFilesIn($directory, $file) {
			self::$_zip = new clsTbsZip();
			self::$_zip->Open(self::$_pptx_uri);
			
			$directory = str_replace('/','\/',$directory);
			$regex = '/' . $directory . $file . '/';
			
			$numberof_files = 0;
			foreach(self::$_zip->CdFileLst as $file_num => $attr) {
				if(preg_match($regex,$attr['v_name'])) {
					$numberof_files++;
				}
			}
			return $numberof_files;
		}
		
		static public function readParagraph(DOMDocument $slide_xml, DOMElement $paragraph) {
			
			$par = array();
			$runs = array();
			$level = 0;
			$bullet;
			$ol;
			
			if ($paragraph->hasChildNodes()) {
				$par_children = $paragraph->childNodes;
				$i = 0; // running through the runs here
				foreach($par_children as $par_child) {
				
					// look if the paragraph has got the datetime or slidenum field
					if($par_child->nodeName == "a:fld") {
						if($par_child->hasAttributes()) {
							$field_type = (string) $par_child->getAttribute('type');
							if(preg_match('/datetime/', $field_type)) {
								
							} elseif(preg_match('/slidenum/', $field_type)) {
								
							}
						}
						
						$query = 'string(a:t)';
						$text = self::evaluateQueryOn($slide_xml, $par_child, $query);
                                                
						$runs[$i]['content'] = $text;
					}
					
					if($par_child->nodeName == "a:pPr") {
						if($par_child->hasAttributes()) {
							$level = (int) $par_child->getAttribute('lvl');
						}						
						if( $par_child->getElementsByTagName('buNone')->length != 0) {
							$bullet = false;
						} elseif ($par_child->getElementsByTagName('buAutoNum')->length != 0) {
							$ol = true;
						}
					}
					
					if($par_child->nodeName == 'a:r') {
						$query = 'string(a:t)';
						$text = self::evaluateQueryOn($slide_xml,
														$par_child, 
														$query);
						$runs[$i]['content'] = $text;
						$query = 'a:rPr/@*';
						$parameters_list = self::evaluateQueryOn($slide_xml,
																	$par_child, 
																	$query);
							if ($parameters_list->length != 0) {
								foreach($parameters_list as $parameter) {
									$runs[$i][$parameter->nodeName] = $parameter->nodeValue;
								}
							}
					} elseif($par_child->nodeName == 'a:br') {
						$runs[$i]['content'] = '<br/>';
					} else {}
					$i++;
				}
			} else {$runs = array();}
			
			array_push($par, $runs);
			array_push($par, $level);
			@array_push($par, $bullet); // can be NULL
			@array_push($par, $ol); // can be NULL
			
			return $par;
		}
		
		static public function offsetToPercents($offset) {
			$offset_percent = array();
			$offset_percent['x'] = (100 / self::$_canvas['cx']) * $offset['x'];
			$offset_percent['y'] = (100 / self::$_canvas['cy']) * $offset['y'];
			return $offset_percent;
		}
		
		static public function extentToPercents($extent) {
			$extent_percent = array();
			$extent_percent['cx'] = (100 / self::$_canvas['cx']) * $extent['cx'];
			$extent_percent['cy'] = (100 / self::$_canvas['cy']) * $extent['cy'];
			return $extent_percent;
		}
		
		static public function paragraphsToString($paragraphs, $shape_num = 1) {
			
			$first_time = true;
			$runs = array();
			$levels = array();
			$nobullet = array();
			$ol = array();
			$first_ol = array();
			$last_ol = array();
			$numberof_paragraphs = sizeof($paragraphs);
			
			foreach($paragraphs as $par_num => $par) {
				$runs = array();
				foreach($par[0] as $run_num => $attr) {
					if(array_key_exists('content',$attr)) {
						if( ($attr['content'] == '<br/>') && $first_time && ($par_num == 0)) {
							$string = '</h2>' . $attr['content'];
							$first_time = false;
						} else {
							$string = $attr['content'];
						}
					} else {
						$string = '';
					}
					// check for bold
					if(array_key_exists('b',$attr) && ( (int) $attr['b'] == 1) ) {
						$string = '<b>' . $string . '</b>';
					}
					// check for italic
					if(array_key_exists('i',$attr) && ( (int) $attr['i'] == 1) ) {
						$string = '<i>' . $string . '</i>';
					}
					// check for underlined
					if(array_key_exists('u',$attr) && ( (int) $attr['u'] == 1) ) {
						$string = '<u>' . $string . '</u>';
					}
					
					array_push($runs, $string);
				}
				
				if(!empty($runs)) {				
					if($shape_num == 0 && ($par_num == 0)) { 
						if($first_time) {
							$paragraphs[$par_num] = '<p><h2>' . implode($runs) . '</h2></p>';
						} else {
							$paragraphs[$par_num] = '<p><h2>' . implode($runs) . '</p>';
						}
					} else {
						$paragraphs[$par_num] = '<p>' . implode($runs) . '</p>';
					}
				} else {
						$paragraphs[$par_num] = '';
				}
				
				array_push($levels, (int) $par[1]);
				array_push($nobullet, $par[2]);
				array_push($ol, $par[3]);
			}			
			
			$list_start = false;
			foreach($ol as $num => $attr) {
				//var_dump($num);
				if($attr) {
					if(!$list_start) {
						$first_ol[$num] = true;
						$list_start = true;
					} 
				} else {
					if($list_start) {
							$list_start = false;
							$last_ol[$num] = true;
							
						}
				}
				if( ($num == ($numberof_paragraphs - 1) ) && $list_start) {
					$last_ol[$num] = true;
				}
			}
			
			// do the level work on the paragraphs
			$ordered_list = array();
			$level_0 = false;
			$level_1 = false;
			
			$ordered_list = array();
			foreach($paragraphs as $par_num => $text) {
				
				if(!empty($paragraphs[$par_num])) {
				
					// ordered list
					if($ol[$par_num]) {
						switch($levels[$par_num]) {
						case 0:
							if(!$level_0 && !$level_1 && $first_ol[$par_num]) {
								$level_0 = true;
								// open tag
								@$ordered_list[$par_num] .= '<OL>';
							}
							
							
							
							if($level_1) {
								//close level one tag
								$ordered_list[$par_num] .= '</OL>';
								$level_1 = false;
							}
							
							// add item
							@$ordered_list[$par_num] .= '<LI>' . $text;
							
							if(@$last_ol[$par_num]) {
								@$ordered_list[$par_num] .= '</OL>';
							}
							break;
						case 1:
							if(!$level_1) {
								$level_1 = true;
								$level_0 = false;
								// open tag
								@$ordered_list[$par_num] .= '<OL>';
							}
							// add item
							@$ordered_list[$par_num] .= '<LI>' . $text;
							
							if(@$last_ol[$par_num]) {
								$ordered_list[$par_num] .= '</OL></OL>';
							}
							break;
						default:
							break;
						}

					} else {
						if(!$nobullet[$par_num]) {
							switch($levels[$par_num]) {
							case 0:
								$paragraphs[$par_num] = '<UL><LI>' . $text . '</LI></UL>';
								break;
							case 1:
								$paragraphs[$par_num] = '<UL><UL><LI>' . $text . '</LI></UL></UL>';
								break;
							case 2:
								$paragraphs[$par_num] = '<UL><UL><UL><LI>' . $text . '</LI></UL></UL></UL>';
								break;
							case 3:
								$paragraphs[$par_num] = '<UL><UL><UL><UL><LI>' . $text . '</LI></UL></UL></UL></UL>';
								break;
							/*if($par_num == 0) {
								//first_paragraph
								$paragraphs[$par_num] = str_repeat('<UL>', $levels[$par_num] + 1) . '<LI>' . $paragraphs[$par_num] . '</LI>';
								if(empty($paragraphs[$par_num + 1])) {
									$paragraphs[$par_num] = $paragraphs[$par_num] . str_repeat('</UL>', $levels[$par_num] + 1);
								}
							} elseif($levels[$par_num] >= $levels[$par_num - 1]) {
								//level up!
								$paragraphs[$par_num] = str_repeat('<UL>', $levels[$par_num] - $levels[$par_num - 1]) . '<LI>' . $paragraphs[$par_num] . '</LI>';
								if(empty($paragraphs[$par_num + 1]) || $ol[$par_num + 1]) {
									//last paragraph!
									$paragraphs[$par_num] = $paragraphs[$par_num] . str_repeat('</UL>', $levels[$par_num] + 1);
								}
							} elseif($levels[$par_num] <= $levels[$par_num - 1]) {
								//level down!
								$paragraphs[$par_num] = str_repeat('</UL>', $levels[$par_num - 1] - $levels[$par_num]) . '<LI>' . $paragraphs[$par_num] . '</LI>';
								if(empty($paragraphs[$par_num + 1]) || $ol[$par_num + 1]) {
									// last paragraph!
									$paragraphs[$par_num] = $paragraphs[$par_num] . str_repeat('</UL>', $levels[$par_num] + 1);
								}
							}*/
							}
						} else {
							$paragraphs[$par_num] = $text;
						}
					}
				}
			}
			
			if(!empty($ordered_list)) {
				for($i = 0; $i < $numberof_paragraphs; $i++) {
					if(array_key_exists($i, $ordered_list)) {
						$paragraphs[$i] = $ordered_list[$i];
					}
				}
			}
			
			$string = implode($paragraphs);
			
			return $string;
		}
		
		static public function saveFileFromLocationToDisc($location, $file_ext, $uid, $path = './upload/media/images/', $db_id = '') {
			
			if(!file_exists($path . $_SESSION['uid'] .'/')) {
				mkdir($path . $uid .'/');
			}
			
			// extracting the image size!
			
			self::$_zip->Open(self::$_pptx_uri);
			if($db_id == '') {
				$id = microtime();
			} else {
				$id = $db_id;
			}
			
			$uri = $path . $uid . '/' . $id . '.' . $file_ext;
			
			$fh = fopen($uri, 'w') or die('can\'t save file to '. $path .' location');
			fwrite($fh, self::$_zip->FileRead($location));
			fclose($fh);
			
			self::$_zip->Close();
			
			return $uri;
		}		
		
		static public function extractOriginalSizeOf($img_location) {
			$img_size = getimagesize($img_location);
			return $img_size;
		}
		
		function png2jpg($originalFile, $outputFile, $quality) {
			$image = imagecreatefrompng($originalFile);
			imagejpeg($image, $outputFile, $quality);
			imagedestroy($image);
		}
		
		static public function getStyleList($list_style_element) {
			$style_list = array();
			if(@$list_style_element->childNodes->length != 0) {
				foreach($list_style_element->childNodes as $a_lvlnpPr) {
					if($a_lvlnpPr->nodeName == 'a:defPPr') { // default paragraph style
					
					} elseif($a_lvlnpPr->nodeName == 'a:extLst') { // extension list
					
					} else { // List level N text style
					$level = (int) substr($a_lvlnpPr->nodeName, 5, 1) - 1;
					// by default bullet exists
					$style_list[$level]['a:buNone'] = false;
						foreach($a_lvlnpPr->childNodes as $list_parameter) {
							if($list_parameter->nodeName == 'a:buNone') {
								$style_list[$level]['a:buNone'] = true;
							}
						}
					}				
				}
			}
			return $style_list;
		}
		
		static public function compareWithSlidemaster($shapes, $slidemaster) {
			$shapes;
			foreach($shapes as $shape_num => $shape) {
				if(!empty($shape->_type)) {
					$shapes[$shape_num] = self::applySMFormatting($shape, $slidemaster->_title_style);	
				} elseif(!empty($shape->_idx) ) {
					$shapes[$shape_num] = self::applySMFormatting($shape, $slidemaster->_body_style);
				}  else {
					$shapes[$shape_num] = self::applySMFormatting($shape, $slidemaster->_other_style);
				}
			}	
			return $shapes;
		}
		
		function applySMFormatting($shape, $slidemaster_style) {
			foreach($shape->_paragraphs as $paragraph_num => $paragraph) {
				foreach($slidemaster_style as $level_num => $level) {
					if(($paragraph[1] == $level_num) && $level['a:buNone']) {
						$shape->_paragraphs[$paragraph_num][2] = true;
					} else {
						$shape->_paragraphs[$paragraph_num][2] = false;
					}
				}
			}
			return $shape;
		}
				
		static public function compareShapeWithShape($shapes, $pattern_shapes) {
			foreach($shapes as $shape) {
				foreach($pattern_shapes as $pattern_shape) {
					if((!empty($shape->_type) && ($shape->_type == $pattern_shape->_type)) || 
						(!empty($shape->_idx) && ($shape->_idx == $pattern_shape->_idx))) {
						$shape = self::copyShapeToShape($shape, $pattern_shape);						
					}  else {
					
					}
				}
			}
			return $shapes;
		}
		
		function copyShapeToShape($shape, $pattern_shape) {
			foreach($shape->_paragraphs as $sh_paragraph_num => $sh_paragraph) {
				foreach($pattern_shape->_paragraphs as $psh_paragraph_num => $psh_paragraph) {
					// find paragraphs with equal levels
					if($sh_paragraph[1] == $psh_paragraph[1]) {
						// make buNone match
						$shape->_paragraphs[$sh_paragraph_num][2] = $pattern_shape->_paragraphs[$psh_paragraph_num][2];
					}
				}				
			}
			return $shape;
		}
		
		static public function applyFormatting($shapes) {
			foreach($shapes as $shape) {
				foreach($shape->_paragraphs as $paragraph_num => $paragraph) {
					foreach($shape->_list_style as $level_num => $level) {
						if($paragraph[1] == $level_num && $level['a:buNone']) {
							$shape->_paragraphs[$paragraph_num][2] = true;
						}
					}
				}
			}
			return $shapes;
		}
		
		static public function shapeToString($paragraphs, $id, $shape_num, $offset, $extent) {
			$string = '';
			$string .= '<div ';
			//$string .= '  id="'. $id .'" ';
			$string .= 'style="';
			
			if(!empty($offset) || !empty($extent)) { 
				$string .= 'position:absolute;';
			}
			
			if($offset['x'] != 0) {
				$string .=  'left:'. $offset['x'] .'%;';
			}
			
			if($offset['y'] != 0) {
				$string .=  'top:'. $offset['y'] .'%;';
			}
			if($extent['cx'] != 0) {
				$right = 100 - $offset['x'] - $extent['cx'];
				$string .= 'right:'. $right .'%;';
			}
			if($extent['cx'] != 0) {
				$bottom = 100 - $offset['y'] - $extent['cy'];
				$string .= 'bottom:'. $bottom .'%;';
			}
			$string .= '">';
			$string .= Util::paragraphsToString($paragraphs, $shape_num);
			$string .= '</div>';
			return $string;
		}
	}
?>
