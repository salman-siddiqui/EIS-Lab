<?php


/*
 *  @file
 *  ext.views.inc
 *  Defines a grid view style
 */

/*
 * Implementation of hook_views_plugin().
 */
function ext_views_plugins() {
  return array(
    'module' => 'ext',
    'style' => array(
      'ext_grid' => array(
        'title' => t('ExtJS Grid'),
        'theme' => 'ext_grid',
        'help' => t('Format the data as an ExtJS Grid'),
        'handler' => 'ext_style_plugin',
        'uses row plugin' => FALSE,
        'uses fields' => TRUE,
        'uses options' => TRUE,
        'type' => 'normal',
      ),
    )
  );
}