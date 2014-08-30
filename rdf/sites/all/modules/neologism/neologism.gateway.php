<?php
/**
 * Construct a tree structure for an specific vocabulary. This' the gateway for the treeview
 * @return json with the tree structure 
 */
function neologism_gateway_get_classes_tree() {
  $voc['id'] = $_POST['voc_id'];
  $voc['title'] = $_POST['voc_title'];
  $node = $_POST['node'];
  $store = array();
  $nodes = array();
  $references = array();
  if ( $node == 'root' ) {
    $classes = db_query(db_rewrite_sql("select * from {evoc_rdf_classes} where prefix = '%s'"), $voc['title']);  
    while ( $class = db_fetch_object($classes) ) {
      $qname = $class->prefix.':'.$class->id;
      
      $store[$qname]['comment'] = $class->comment;
      $store[$qname]['label'] = $class->label;
      if ( $class->superclasses > 0 ) {
      	$superclasses = db_query(db_rewrite_sql("select superclass from {evoc_rdf_superclasses} where prefix = '%s' and reference = '%s'"), $class->prefix, $class->id);
      	while ( $object = db_fetch_object($superclasses) ) {
      		$store[$object->superclass]['subclasses'][] = $qname; 
      		$store[$qname]['rdfs:subClassOf'][] = $object->superclass; 
      	}
      }
    }
    
    $config = array(
      'property' => 'subclasses',
      'icon_class_samevoc' => 'class-samevoc',
    	'icon_class_diffvoc' => 'class-diffvoc',
      'text_class_currentvoc' => 'currentvoc'
    );
    
    foreach ($store as $key => $val ) {  
    	if ( !isset($val['rdfs:subClassOf']) ) {
  			$nodes[] = _neologism_build_tree_in_order($store, $config, $key, $voc['title'], $references);
    	}
	  }
  }
  
  drupal_json($nodes);
}

/**
 * Build a tree structure for ExtJS TreePanel using a store previously constructed.
 * @param $store
 * @param $config
 * @param $term
 * @param $vocabulary
 * @param $references
 * @return unknown_type
 */
function _neologism_build_tree_in_order(array &$store, array &$config, $term, $vocabulary, &$references) {
	$stack = array();
	$nodes = array();
	$ancestors_and_self = array($term);
	$stack[] = array($term, &$nodes, $ancestors_and_self);
	
	// config
	$store_property = $config['property']; // this is the property selected when the store was created.
	$icon_class_samevoc = $config['icon_class_samevoc'];
	$icon_class_diffevoc = $config['icon_class_diffvoc'];
	$text_class_currentvoc = $config['text_class_currentvoc'];
	
	$i = 0;
	while( count($stack) ) {
		$arr = &_neologism_array_rpop($stack);
		$current = $arr[0]; 
		$node = &$arr[1]; 
		$ancestors_and_self = $arr[2]; 
		
		$term_qname_parts = explode(':', $current);
  	$prefix = $term_qname_parts[0];
  	$id = $term_qname_parts[1];
  	
  	$sameVocabulary = ($prefix == $vocabulary);
  	$qtip = '<b>'.$store[$current]['label'].'</b><br/>'.$store[$current]['comment'];
  	
  	$id_name = $current;
  	if (isset($references[$current])) {
  	  $references[$current] += 1;
  	  $id_name = $references[$current].'_'.$current;
  	}
  	else {
  	  $references[$current] = 0;
  	}
		
		if( count($store[$current][$store_property]) ) {
			if( $node ) {
				$node['leaf'] = false;
				$node['children'][] = array(
					'text' => $current,
					'id' => $id_name,
					'children' => null,
					'leaf' => true,
					'iconCls' => $sameVocabulary ? $icon_class_samevoc : $icon_class_diffevoc,
					'cls' => $sameVocabulary ? $text_class_currentvoc : '',
					'qtip' => $qtip,
				);
				
				$node = &$node['children'][count($node['children'])-1];
			}
			else {
				$node = array(
					'text' => $current,
					'id' => $id_name,
					'children' => null,
					'leaf' => true,
					'iconCls' => $sameVocabulary ? $icon_class_samevoc : $icon_class_diffevoc,
					'cls' => $sameVocabulary ? $text_class_currentvoc : '',
					'qtip' => $qtip,
				);
			}
			
			foreach( $store[$current][$store_property] as $key => $val ) {
        if (!in_array($val, $ancestors_and_self)) {
        	$copy = $ancestors_and_self;
        	$copy[] = $val;
        	$stack[] = array($val, &$node, $copy);
        }
			}
			
			continue;
		}
		
		if( $node ) {
			$node['leaf'] = false;
			$node['children'][] = array(
				'text' => $current,
				'id' => $id_name,
				'children' => null,
				'leaf' => true,
				'iconCls' => $sameVocabulary ? $icon_class_samevoc : $icon_class_diffevoc,
				'cls' => $sameVocabulary ? $text_class_currentvoc : '',
				'qtip' => $qtip,
			);
			
			$node = &$node['children'][count($node['children'])-1];
		}
		else {
			$node = array(
				'text' => $current,
				'id' => $id_name,
				'children' => null,
				'leaf' => true,
				'iconCls' => $sameVocabulary ? $icon_class_samevoc : $icon_class_diffevoc,
				'cls' => $sameVocabulary ? $text_class_currentvoc : '',
				'qtip' => $qtip,
			);
		}
	} // while
	
	return $nodes;
}

/**
 * 
 * @param $class
 * @return unknown_type
 */
function neologism_gateway_get_root_superclasses($class) {
 
  static $root_superclasses = array();
  
  $term_qname_parts = explode(':', $class);
  $prefix = $term_qname_parts[0];
  $id = $term_qname_parts[1];
  
  $object = db_fetch_object(db_query(db_rewrite_sql("select superclasses from {evoc_rdf_classes} where prefix = '%s' and id = '%s'"), $prefix, $id));
  if ( $object->superclasses > 0 ) {
    $superclass = db_query(db_rewrite_sql("SELECT superclass FROM {evoc_rdf_superclasses} where prefix = '%s' and reference = '%s'"), $prefix, $id);
    while ( $term = db_fetch_object($superclass) ) {
      $term->superclass = trim($term->superclass);
      $root_superclasses = neologism_gateway_get_root_superclasses($term->superclass);  
    }
  }
  else {
    if( !_neologism_gateway_in_array($class, $root_superclasses) ) {
      $root_superclasses[] = $class;  
    }
  }
  
  return $root_superclasses;
}

/**
 * Construct the tree structure for a Tree using ExtJS Tree structure. This structure normally is shown in a termtree component.
 * @return json with the tree structure 
 */
function neologism_gateway_get_full_classes_tree() {
  $nodes = array();
  $node = $_REQUEST['node'];
  
  // TODO we could pass as a parameter when to use the function to infer the disjointwith
  $array_disjointwith = array();
  $array_references = array(); 
  
  $count = 0;
    
  if ( $node == 'root' ) {
    $classes = db_query(db_rewrite_sql("SELECT * FROM {evoc_rdf_classes} where superclasses = '0'"));

    while ($class = db_fetch_object($classes)) {
      $qname = $class->prefix.':'.$class->id;

    	// fetch the disjointwith for root classes
      $disjointwith = array();
      if( $class->ndisjointwith > 0 ) {
				$disjointwith = _neologism_get_class_disjoinwith_terms($class->prefix, $class->id);
				// add disjointwith to the main array to use it after build the nodes
				$array_disjointwith[$qname] = $disjointwith;
      }
      
      // create the node for the class 
      _neologism_gateway_create_class_treenode(&$nodes, $node, $class, $disjointwith, $array_disjointwith, $array_references);
    }
    
    // fetch all the external superclasses
    $classes = db_query(db_rewrite_sql("SELECT DISTINCT superclass FROM {evoc_rdf_superclasses sc} WHERE 
    	NOT EXISTS (SELECT * FROM {evoc_rdf_classes} c WHERE sc.superclass = CONCAT(c.prefix, ':', c.id))"));
    
    while ($class = db_fetch_object($classes)) {
      _neologism_gateway_create_class_treenode($nodes, $node, $class, array(), $array_disjointwith, $array_references);
    }
    
    _neologism_filter_references($array_references);
    $nodes[0]['references'] = $array_references;
     
    // infer disjointness between classes
	  _neologism_infer_disjointness($nodes, $array_disjointwith);
  }

  drupal_json($nodes);
}

/**
 * Create a treenode for a Treepanel based on ExtJS treenode structure.
 * 
 * @param $nodes
 *   The reference where the complete tree structure is bing storing.
 * @param $node
 *   The tree root node.
 * @param $class
 *   The object containing the fetched class information.
 * @param $disjointwith
 *   The array containing all the classes disjoinwith this.
 * @param $array_disjointwith
 *   Reference to the array of disjointwith found.
 * @param $array_references
 *   Reference to an array of all classes that have more that one 
 *   dependency (reference) in the tree.
 * @return 
 *   none
 */
function _neologism_gateway_create_class_treenode(&$nodes, $node, $class,  
  array $disjointwith, &$array_disjointwith, &$array_references) {

  // check if the data for the creation comes from the classes table
  if (!isset($class->superclass)) {
    $qname = $class->prefix.':'.$class->id; 
    $qtip = '<b>'.$class->label.'</b><br/>'.$class->comment;  
  } else {
    $qname = $class->superclass;
    $qtip = '<em>This class is external and a description has not been provided.</em>'; 
  }
  
  $parent_path = '/'.$node;
  $children = neologism_gateway_get_class_children($qname, NULL, TRUE, $array_disjointwith, $parent_path, $array_references);
  
  $leaf = count($children) == 0;
  $nodes[] = array(
    'text' => $qname, 
    'id' => $qname, 
    'leaf' => $leaf, 
    'iconCls' => 'class-samevoc', 
    'children' => $children, 
    'checked' => false,
    'qtip' => $qtip,
  	'disjointwith' => $disjointwith,
  	//'inferred_disjointwith' => $inferred_disjointwith,
  	'nodeStatus' => 0	// send to the client this attribute that represent the  NORMAL status for a node
  ); 
}

/**
 * This recurive function search for chindren of $node return class from the same $voc. 
 * If the parent does not belong to the $voc but has children that does, this parent is added as well.
 * @param object $node
 * @param object $voc
 * @param object $add_checkbox [optional]
 * @return 
 * @version 1.1
 */
function neologism_gateway_get_class_children($node, $voc = NULL, $add_checkbox = FALSE, array &$array_disjointwith = NULL, $rootName, array &$references) {
  $nodes = array();
  $stack = array();
  $referenceToCurrentNode = &$nodes;
  $ancestors_and_self = array($node);
  
  $stack[] = array($node, &$referenceToCurrentNode, $ancestors_and_self);
  
  while ( count($stack) ) {
  	$array = &_neologism_array_rpop($stack);
  	$classname = $array[0];
		$currentNode = &$array[1];
		$ancestors_and_self = $array[2];
		$parentNode = $currentNode;
		$indexCount = 0;
		
 	  $children = db_query(db_rewrite_sql("select prefix, reference from {evoc_rdf_superclasses} where superclass = '%s'"), $classname);
	   
	  while ($child = db_fetch_object($children)) {
	    $class = db_fetch_object(db_query(db_rewrite_sql("select * from {evoc_rdf_classes} where prefix = '%s' and id = '%s'"), $child->prefix, $child->reference));
	    if ( $class ) {
	    	$class->prefix = trim($class->prefix);
	      $class->id = trim($class->id); 
	      $qname = $class->prefix.':'.$class->id;
	      
	      // extra information needed by the treeview. This extra information is needed when there node has more than 
	      // one parent.
        $extra_information = true;
        $realId = '';
        if ( isset($references[$qname]) ) {
          $references[$qname]['references'] += 1;
          $modified_id = $references[$qname]['references'].'_'.$qname;
        	$references[$qname]['paths'][] = $rootName.'/'.implode('/',$ancestors_and_self).'/'.$modified_id; 
        	$realId = $qname;
        }
        else {
          $references[$qname]['paths'] = array($rootName.'/'.implode('/',$ancestors_and_self).'/'.$qname);
        	$references[$qname]['references'] = 0;	
        	$extra_information = false;
        }
	      
	      //-----------------------------------------
	      // fetch extra information
	      
	      // fetch the disjointwith
	      $disjointwith = array();
        if( $class->ndisjointwith > 0 ) {
  				$disjointwith = _neologism_get_class_disjoinwith_terms($class->prefix, $class->id);
  				// add disjointwith to the main array to use it after build the nodes
  				$array_disjointwith[$qname] = $disjointwith;
	      }
	      
	      // fetch the superclasses
	    	$superclasses = array();
	      if( $class->superclasses > 0 ) {
					$superclasses = _neologism_get_superclasses_terms($class->prefix, $class->id);
	      }
	      
	      //$children_nodes = neologism_gateway_get_class_children($qname, $voc, $add_checkbox, $disjointwith_array); 
				
	      if( $parentNode ) {
		      $qtip = '<b>'.$class->label.'</b><br/>'.$class->comment;
		      $currentNode['leaf'] = false;
					$currentNode['children'][] = array(
		          'text' => $qname, 
		          'id' => (!$extra_information) ? $qname : $modified_id, 
		          'leaf' => true, 
		          'iconCls' => 'class-samevoc', 
		          'children' => NULL, 
		          'qtip' => $qtip,
		        	'disjointwith' => $disjointwith,
		        	'superclasses' => $superclasses,
		        	'nodeStatus' => 0	// send to the client this attribute that represent the  NORMAL status for a node
		        );
		        
		        // note: count($currentNode['children'])-1 is replaced by $indexCount
		        
		      	if( $extra_information ) {
		        	//$currentNode['children'][count($currentNode['children'])-1]['realid'] = $qname; 
		        	$currentNode['children'][$indexCount]['realid'] = $qname;
		        }
		          
		        if( $add_checkbox ) {
		          $currentNode['children'][$indexCount]['checked'] = false;
		        }
	        
		        $referenceToCurrentNode = &$currentNode['children'][$indexCount];
		        $indexCount++;
	      }
	      else {
	      	$qtip = '<b>'.$class->label.'</b><br/>'.$class->comment;
					$currentNode[] = array(
		          'text' => $qname, 
							'id' => $qname,
		          'id' => (!$extra_information) ? $qname : $modified_id, 
		          'leaf' => true, 
		          'iconCls' => 'class-samevoc', 
		          'children' => NULL, 
		          'qtip' => $qtip,
		        	'disjointwith' => $disjointwith,
		        	'superclasses' => $superclasses,
		        	'nodeStatus' => 0	// send to the client this attribute that represent the  NORMAL status for a node
		        );	
		        
		      // count($currentNode)-1 is replaced by $indexCount 
		      
	      	if( $extra_information ) {
	        	//$currentNode[count($currentNode)-1]['realid'] = $qname; 
	        	$currentNode[$indexCount]['realid'] = $qname; 
	        }
	          
	        if( $add_checkbox ) {
	          $currentNode[$indexCount]['checked'] = false;
	        }
		        
		      $referenceToCurrentNode = &$currentNode[$indexCount];
		      $indexCount++;
	      }
	        
	      if (!in_array($qname, $ancestors_and_self)) {
					$copy = $ancestors_and_self;
					$copy[] = $qname;
					$stack[] = array($qname, &$referenceToCurrentNode, $copy);
	      }
	    }
	  } // while
	  
  }
  
  return $nodes;
}

//-----------------------------------------------------------------------------------------------------------------
// functions for objectproperty_tree
function neologism_gateway_get_properties_tree() {
  $voc['id'] = $_POST['voc_id'];
  $voc['title'] = $_POST['voc_title'];
  $references = array();
  $array_inverses = array();
  
  $node = $_POST['node'];
  $nodes = array();
  
  $store = array();
  if ( $node == 'root' ) {
    $properties = db_query(db_rewrite_sql("select * from {evoc_rdf_properties} where prefix = '%s'"), $voc['title']);
    
    while ( $property = db_fetch_object($properties) ) {
      $qname = $property->prefix.':'.$property->id;
      $store[$qname]['comment'] = $property->comment;
      $store[$qname]['label'] = $property->label;
      if ( $property->superproperties > 0 ) {
      	$superproperties = db_query(db_rewrite_sql("select superproperty from {evoc_rdf_superproperties} where prefix = '%s' and reference = '%s'"), $property->prefix, $property->id);
      	while ( $object = db_fetch_object($superproperties) ) {
      		$store[$object->superproperty]['superproperties'][] = $qname; 
      		$store[$qname]['rdfs:subPropertyOf'][] = $object->superproperty; 
      	}
      }
    }
    
     $config = array(
      'property' => 'superproperties',
      'icon_class_samevoc' => 'property-samevoc',
    	'icon_class_diffvoc' => 'property-diffvoc',
      'text_class_currentvoc' => 'currentvoc'
    );
    
    foreach ($store as $key => $val ) {  
    	if ( !isset($val['rdfs:subPropertyOf']) ) {
  			$nodes[] = _neologism_build_tree_in_order($store, $config, $key, $voc['title'], $references);
    	}
	  }
  }
  
  drupal_json($nodes);
}

function neologism_gateway_get_root_superproperties($property) {
  static $root_superproperties = array();
  
  $term_qname_parts = explode(':', $property);
  $prefix = $term_qname_parts[0];
  $id = $term_qname_parts[1];
  
  $object = db_fetch_object(db_query(db_rewrite_sql("select superproperties from {evoc_rdf_properties} where prefix = '%s' and id = '%s'"), $prefix, $id));
  if ( $object->superproperties > 0 ) {
    $superproperty = db_query(db_rewrite_sql("SELECT superproperty FROM {evoc_rdf_superproperties} where prefix = '%s' and reference = '%s'"), $prefix, $id);
    while ( $term = db_fetch_object($superproperty) ) {
      $term->superproperty = trim($term->superproperty);
      //$root_superproperties = neologism_gateway_get_root_superclasses($term->superproperty);  
      $root_superproperties = neologism_gateway_get_root_superproperties($term->superproperty);
    }
  }
  else {
    if( !_neologism_gateway_in_array($property, $root_superproperties) ) {
      $root_superproperties[] = $property;  
    }
  }
  
  return $root_superproperties;
}

/**
 * Construct the tree structure for a Tree using ExtJS Tree structure
 * @return json with the tree structure 
 */
function neologism_gateway_get_full_properties_tree() {
  $nodes = array();
  $node = $_REQUEST['node'];
  $array_references = array();
  $array_inverses = array();
    
  if ( $node == 'root' ) {
    $properties = db_query(db_rewrite_sql("SELECT * FROM {evoc_rdf_properties} where superproperties = '0'"));
    
    while ($property = db_fetch_object($properties)) {
      $qname = $property->prefix.':'.$property->id;
      
      $array_domain = array();
      if( $property->domains > 0 ) {
        $array_domain = _neologism_get_domain_terms($property->prefix, $property->id);
      }
      
    	$array_ranges = array();
      if( $property->ranges > 0 ) {
        $array_ranges = _neologism_get_range_terms($property->prefix, $property->id);
      }
      
      // fetch the inverses
      $inverses = array();
      if( $property->inverses > 0 ) {
				$inverses = _neologism_get_inverseof_terms($property->prefix, $property->id);
				// add inverses to the main array to use it after build the nodes
				$array_inverses[$qname] = $inverses;
      }
      
      _neologism_gateway_create_property_treenode($nodes, $node, $property, $array_domain, $array_ranges, $inverses, $array_inverses, $array_references);
    }
    
    $properties = db_query(db_rewrite_sql("SELECT DISTINCT superproperty FROM {evoc_rdf_superproperties sp} WHERE 
    	NOT EXISTS (SELECT * FROM {evoc_rdf_properties} p WHERE sp.superproperty = CONCAT(p.prefix, ':', p.id))"));
    
    while ($row = db_fetch_object($properties)) {
      _neologism_gateway_create_property_treenode($nodes, $node, $row, array(), array(), array(), $array_inverses, $array_references);
    }
    
    $nodes[0]['references'] = $array_references; 
    
    // infer disjointness between classes
	  _neologism_infer_inverses($nodes, $array_inverses);
  }
  
  drupal_json($nodes);
}

function _neologism_gateway_create_property_treenode(&$nodes, $node, $property,  
  array $array_domain, array $array_ranges, array $inverses, &$array_inverses, &$array_references) {

  // check if the data for the creation comes from the classes table
  if (!isset($property->superproperty)) {
    $qname = $property->prefix.':'.$property->id; 
    $qtip = '<b>'.$property->label.'</b><br/>'.$property->comment;  
  } else {
    $qname = $property->superproperty;
    $qtip = '<em>This property is external and a description has not been provided.</em>'; 
  }
  
  // extra information needed by the treeview
  $parent_path = '/'.$node;
  $children = neologism_gateway_get_property_children($qname, NULL, TRUE, $array_inverses, $parent_path, $array_references);
  
  $leaf = count($children) == 0;
  $nodes[] = array(
    'text' => $qname, 
    'id' => $qname, 
    'leaf' => $leaf, 
    'iconCls' => 'property-samevoc', 
    'children' => $children, 
    'checked' => false,
    'qtip' => $qtip,
  	'domain' => $array_domain,
    'range' => $array_ranges,
    'inverses' => $inverses
  );   

  if( $extra_information ) {
    $nodes[count($nodes)-1]['realid'] = $qname; 
  }
}

function neologism_gateway_get_property_children($node, $voc = NULL, $add_checkbox = FALSE, array &$array_inverses, $parent_path, &$references) {
  $nodes = array();
  $children = db_query(db_rewrite_sql("select prefix, reference from {evoc_rdf_superproperties} where superproperty = '%s'"), $node);
    
  while ($child = db_fetch_object($children)) {
    $property = db_fetch_object(db_query(db_rewrite_sql("select * from {evoc_rdf_properties} where prefix='%s' and id = '%s'"), $child->prefix, $child->reference));
    if ( $property ) {
      $property->prefix = trim($property->prefix);
      $property->id = trim($property->id); 
      $qname = $property->prefix.':'.$property->id;
      
      $array_domain = array();
      if( $property->domains > 0 ) {
        $array_domain = _neologism_get_domain_terms($property->prefix, $property->id);
      }
      
    	$array_ranges = array();
      if( $property->ranges > 0 ) {
        $array_ranges = _neologism_get_range_terms($property->prefix, $property->id);
      }
      
      // fetch the superproperties
    	$superproperties = array();
      if( $property->superproperties > 0 ) {
				$superproperties = _neologism_get_superproperties_terms($property->prefix, $property->id);
      }
      
      
      
      // fetch the inverses
      $inverses = array();
      if( $property->inverses > 0 ) {
				$inverses = _neologism_get_inverseof_terms($property->prefix, $property->id);
				// add inverses to the main array to use it after build the nodes
				$array_inverses[$qname] = $inverses;
      }
	      
    	// extra information needed by the treeview
      $extra_information = true;
      $realId = '';
      if( isset($references[$qname]) ) {
        $references[$qname]['references'] += 1;
      	$modified_id = $references[$qname]['references'].'_'.$qname;
      	$references[$qname]['paths'][] = $parent_path.'/'.$modified_id;  
      	$realId = $qname;
      }
      else {
      	$references[$qname]['paths'] = array($parent_path.'/'.$qname);
      	$references[$qname]['references'] = 0;	
      	$extra_information = false;
      }
      
      $real_parent_path = $parent_path.'/'.((!$extra_information) ? $qname : $modified_id);
      $children_nodes = neologism_gateway_get_property_children($qname, $voc, $add_checkbox, $array_inverses, $real_parent_path, $references);

      if( $voc ) {
        if( $property->prefix == $voc || _neologism_gateway_in_nodes($voc, $children_nodes) ) {
          $leaf = count($children_nodes) == 0;
          $qtip = '<b>'.$property->label.'</b><br/>'.$property->comment;
          $nodes[] = array(
            'text' => $qname, 
            'id' => (!$extra_information) ? $qname : $modified_id,
            'leaf' => $leaf, 
            'iconCls' => ($property->prefix == $voc) ? 'property-samevoc' : 'property-diffvoc', 
            'cls' => ($property->prefix == $voc) ? 'currentvoc' : '',
            'children' => $children_nodes, 
            'qtip' => $qtip,
          	'domain' => $array_domain,
      			'range' => $array_ranges,
            'superproperties' => $superproperties,
            'inverses' => $inverses
          );
          
          if( $add_checkbox ) {
            $nodes[count($nodes)-1]['checked'] = false;
          }
          
         	if( $extra_information ) {
          	$nodes[count($nodes)-1]['realid'] = $qname; 
          }
        }
      } else {
        $leaf = count($children_nodes) == 0;
        $qtip = '<b>'.$property->label.'</b><br/>'.$property->comment;
        $nodes[] = array(
          'text' => $qname, 
          'id' => (!$extra_information) ? $qname : $modified_id,  
          'leaf' => $leaf, 
          'iconCls' => 'property-samevoc', 
          'children' => $children_nodes, 
          'qtip' => $qtip,
	        'domain' => $array_domain,
	      	'range' => $array_ranges,
        	'superproperties' => $superproperties,
          'inverses' => $inverses
        );
          
        if( $add_checkbox ) {
          $nodes[count($nodes)-1]['checked'] = false;
        }
        
      	if( $extra_information ) {
         	$nodes[count($nodes)-1]['realid'] = $qname; 
        }
      }
      
    }
  }
  
  return $nodes;
}

//-----------------------------------------------------------------------------------------------------------
// These functions are more completed that the above. I planned to fix the function above for tunning process
//

/**
 * Query for prefix:id term disjoinwith classes 
 * @param string $prefix
 * @param string $id
 * @return Array of disjoinewith classes
 */
function _neologism_get_element_property_terms($property, $prefix, $id) {
  $array_terms = array();
  $table_name = '';
  $column_name = '';
  
  if ($property == 'rdfs:domain') {
    $table_name = 'evoc_rdf_propertiesdomains';  
    $column_name = 'rdf_domain';
  } elseif ($property == 'rdfs:range') {
    $table_name = 'evoc_rdf_propertiesranges';
    $column_name = 'rdf_range';
  } elseif ($property == 'rdfs:subPropertyOf') {
    $table_name = 'evoc_rdf_superproperties';
    $column_name = 'superproperty';
  } elseif ($property == 'owl:inverseOf') {
    $table_name = 'evoc_rdf_inversesproperties';
    $column_name = 'inverseof';
  } elseif ($property == 'owl:disjointWith') {
    $table_name = 'evoc_rdf_disjointwith';
    $column_name = 'disjointwith';
  } elseif ($property == 'rdfs:subClassOf') {
    $table_name = 'evoc_rdf_superclasses';
    $column_name = 'superclass';
  }
  
  $result = db_query(db_rewrite_sql("select %s from {%s} where prefix = '%s' and reference = '%s'"), $column_name, $table_name, $prefix, $id);
	while( $obj = db_fetch_object($result) ) {
	  $result = array_values($c);
		$array_terms[] = $result[0];
	}
	return $array_terms;
}

function _neologism_get_class_disjoinwith_terms($prefix, $id) {
  $array_disjointwith = array();
  $result = db_query(db_rewrite_sql("select disjointwith from {evoc_rdf_disjointwith} where prefix = '%s' and reference = '%s'"), $prefix, $id);
	while( $obj = db_fetch_object($result) ) {
		$array_disjointwith[] = $obj->disjointwith;
	}
	return $array_disjointwith;
}

function _neologism_get_superclasses_terms($prefix, $id) {
  $array_superclasses = array();
  $result = db_query(db_rewrite_sql("select superclass from {evoc_rdf_superclasses} where prefix = '%s' and reference = '%s'"), $prefix, $id);
	while( $obj = db_fetch_object($result) ) {
		$array_superclasses[] = $obj->superclass;
	}
	return $array_superclasses;
}

function _neologism_get_inverseof_terms($prefix, $id) {
  $array_inverses = array();
  $result = db_query(db_rewrite_sql("select inverseof from {evoc_rdf_inversesproperties} where prefix = '%s' and reference = '%s'"), $prefix, $id);
	while( $obj = db_fetch_object($result) ) {
		$array_inverses[] = $obj->inverseof;
	}
	return $array_inverses;
}

function _neologism_get_superproperties_terms($prefix, $id) {
  $array_superproperties = array();
  $result = db_query(db_rewrite_sql("select superproperty from {evoc_rdf_superproperties} where prefix = '%s' and reference = '%s'"), $prefix, $id);
	while( $obj = db_fetch_object($result) ) {
		$array_superproperties[] = $obj->superproperty;
	}
	return $array_superproperties;
}
      	
function _neologism_get_domain_terms($prefix, $id) {
  $array_domains = array();
  $result = db_query(db_rewrite_sql("select rdf_domain from {evoc_rdf_propertiesdomains} where prefix = '%s' and reference = '%s'"), $prefix, $id);
	while( $obj = db_fetch_object($result) ) {
		$array_domains[] = $obj->rdf_domain;
	}
	return $array_domains;
}

function _neologism_get_range_terms($prefix, $id) {
  $array_ranges = array();
  $result = db_query(db_rewrite_sql("select rdf_range from {evoc_rdf_propertiesranges} where prefix = '%s' and reference = '%s'"), $prefix, $id);
	while( $obj = db_fetch_object($result) ) {
		$array_ranges[] = $obj->rdf_range;
	}
	return $array_ranges;
}

/**
 * Remove element from the $references where references are equal to zero. 
 * @param $references keyed array containing the terms with its references if there is more than one.
 * @return none
 */
function _neologism_filter_references(&$references) {
  foreach ($references as $key => $reference) {
    if ($reference['references'] == 0) {
      unset($references[$key]);
    }
  }
}

/**
 * Check for the known disjointwith to infer unknown disjointwith, cause
 * this is a simetric property
 * @param unknown_type $nodes
 * @param array $disjointwith_array
 * @return unknown_type
 */
function _neologism_infer_disjointness(&$nodes, array &$array_disjointwith, array &$parent_disjointwith = NULL) {
	for ($i = 0; $i < count($nodes); $i++ ) {
		
	  $node = &$nodes[$i];
		// if A disjointwith B and C subset of B => C disjointwith A
	  if ($parent_disjointwith != NULL ) {
			$node['inferred_disjointwith'] = $parent_disjointwith;	
		}
		
		foreach ($array_disjointwith as $qname => $array_of_disjoinwith ) {
			foreach ($array_of_disjoinwith as $value ) {
				if ($node['text'] == $value ) {
				  if (is_array($node['disjointwith']) && in_array($qname, $node['disjointwith']) || (is_array($node['inferred_disjointwith']) && in_array($qname, $node['inferred_disjointwith']))) {
				    continue;
				  }  
					$node['inferred_disjointwith'][] = $qname;	
				}
			}
		}
		
		if (count($node['children']) > 0) {
		  $delegated_disjointness = is_array($node['disjointwith']) && is_array($node['inferred_disjointwith']) ? array_merge($node['disjointwith'], $node['inferred_disjointwith']) : $node['disjointwith'];
		  _neologism_infer_disjointness($node['children'], $array_disjointwith, $delegated_disjointness);
		}
		
	}
}

/**
 * 
 * @param $nodes
 * @param $array_inverses
 * @return unknown_type
 */
function _neologism_infer_inverses(&$nodes, array &$array_inverses) {
	for ($i = 0; $i < count($nodes); $i++ ) {
		
	  $node = &$nodes[$i];
		foreach ($array_inverses as $qname => $array_of_inverses ) {
			foreach ($array_of_inverses as $value ) {
				if ($node['text'] == $value ) {
				  if (is_array($node['inverses']) && in_array($qname, $node['inverses']) || (is_array($node['inferred_inverses']) && in_array($qname, $node['inferred_inverses']))) {
				    continue;
				  }  
					$node['inferred_inverses'][] = $qname;	
				}
			}
		}
		
		if (count($node['children']) > 0) {
			_neologism_infer_disjointness($node['children'], $array_inverses);
		}
		
	}
}

function _neologism_gateway_in_array($strproperty, array $strarray_values) {
  foreach ($strarray_values as $str) {
    if( $str == $strproperty ) {
      return true;
    }
  }
  
  return false; 
}

/**
 * Compare if in $nodes array exists some qname with prefix $prefix.
 * Using the $node['text'] attribute because the $node['id'] attr changes sometimes
 * @param unknown_type $prefix
 * @param array $nodes
 * @return unknown_type
 */
function _neologism_gateway_in_nodes($prefix, array $nodes) {
  $result = false;
  foreach ($nodes as $node) {    
  	$qterm_splited = explode(':', $node['text']);
    if( $prefix == $qterm_splited[0] ) {
      $result = true;
    } elseif( !empty($node['children']) ) {
      $result = _neologism_gateway_in_nodes($prefix, $node['children']); 
    }
  }
  return $result;  
}

/**
 * Pop a reference of the last element of the array $a.
 * @param $a
 * @return return the reference object of the last element of the array $a
 */
function &_neologism_array_rpop(&$a){
    end($a);
    $k=key($a);
    $v=&$a[$k];
    unset($a[$k]);
    return $v;
}