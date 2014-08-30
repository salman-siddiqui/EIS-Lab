<?php

/**
 * @file
 * ext-grid.tpl.php
 * Default view template to display a grid
 *
 * Default variables
 * - $view: The view object.
 * - $options: Style options. See below.
 * - $rows: The output for the rows.
 * - $title: The title of this group of rows.  May be empty.
 *
 * Custom variables
 * - $columns: The headers for columns of the selected fields
 * - $data:    The data of the columns
 */
?>

<div id="extjs-grid"></div>

<?php
  ext_load_library();
  $js = theme('ext_js', $rows, $options, $id, $columns, $data, $view->name, $view->current_display, $view->args);
  drupal_add_js($js, 'inline');