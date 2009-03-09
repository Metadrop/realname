<?php
// $Id$

/**
 * @file
 * The RealName module allows the admin to choose fields from the user profile
 * that will be used to add a "realname" element (method) to a user object.
 * Hook_user is used to automatically add this to any user object that is loaded.
 *
 * @copyright Copyright (c) 2007-2008 Nancy Wichmann.  All rights reserved.
 */

/**
 * Displays the admin settings form.
 * @TODO: move this admin stuff to separate file.
 */
function realname_admin_settings() {
  return drupal_get_form('realname_admin_fields');
}

function realname_admin_general() {
  $form = array();

  $form['realname_nodeapi'] = array(
    '#type' => 'checkbox',
    '#title' => '<strong>'. t('Show realname in nodes') .'</strong>',
    '#description' => t('If this option is selected, the "Real name" will be used on node displays.'),
    '#default_value' => variable_get('realname_nodeapi', FALSE),
    );

  $form['theme'] = array(
    '#type' => 'fieldset',
    '#title' => t('Theme_username options'),
    '#description' => t('These settings control how "theme(\'username\', ...)" will work.'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
    );

  $form['theme']['realname_theme'] = array(
    '#type' => 'checkbox',
    '#title' => '<strong>'. t('Override username theme') .'</strong>',
    '#description' => t('If this option is selected, the standard username theme function will be overriden to use the "Real name."'),
    '#default_value' => variable_get('realname_theme', TRUE),
    );

  $form['theme']['realname_max_username'] = array(
    '#type' => 'textfield',
    '#field_prefix' => '<strong>'. t('Maximum allowed username length') .'</strong> ',
    '#description' => t('Long usernames may "break" some tables or other displays; this setting limits the maximum length. Note that the core recommendation is 20.'),
    '#size' => 6,
    '#default_value' => variable_get('realname_max_username', 20),
    );

  $form['theme']['realname_notver'] = array(
    '#type' => 'checkbox',
    '#title' => '<strong>'. t('Show "Not verified" for anonymous users') .'</strong>',
    '#description' => t('Drupal core adds "Not verified" for anonymous users, this option allows that to be turned off.'),
    '#default_value' => variable_get('realname_notver', TRUE),
    );

  return system_settings_form($form);
}

function realname_admin_bypass() {
  $form = array();

  $current = variable_get('realname_bypass_forms', array(array('name' => 'comment_form', 'fields' => array('name'))));
  $rows = count($current);
  $bypass_list = NULL;
  foreach ($current as $bypass) {
    $bypass_list .= $bypass['name'] .' '. implode(' ', $bypass['fields']) ."\n";
  }

  $form['warning'] = array(
    '#type' => 'markup',
    '#value' => '<div class="messages warning">'. t('<strong>WARNING</strong>: This form may cause errors if the instructions below are not followed or if you give an invalid form name or field name. Please do not remove the "comment_form" line.') .'</div>',
    );

  $form['bypass_forms'] = array(
    '#type' => 'textarea',
    '#title' => t('Bypass these forms'),
    '#rows' => max(array($rows + 1, 5)),
    '#default_value' => $bypass_list,
    '#description' => t('Enter one form per line. Each line should start with the form name. That should be followed by the name of the field to be reset to the username. If the field is a child field, include its full parent path, separated by spaces. For example: mymodule_form fieldset_1 fieldset_2 name'),
    '#required' => TRUE,
    );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Bypass these forms'),
    );

  return $form;
}

function realname_admin_bypass_submit($form, &$form_state) {
  $bypass_list = explode("\n", $form_state['values']['bypass_forms']);
  $bypass_forms = array();
  foreach ($bypass_list as $line) {
    $line = trim($line);
    if ($line) {
      $pieces = explode(' ', $line);
      $form_info = array('name' => array_shift($pieces));
      $form_info['fields'] = $pieces;
      $bypass_forms[] = $form_info;
    }
  }
  variable_set('realname_bypass_forms', $bypass_forms);
  drupal_set_message(t('Information saved for !count forms.', array('!count' => count($bypass_forms))), 'status');
}

function realname_admin_module($form_state) {
  $form = array();
  // Get the list of modules we support.
  include_once(drupal_get_path('module', 'realname') .'/realname_supported.inc');
  $supported_modules = realname_supported_modules();

  $choices = $show_types = array();
  foreach ($supported_modules as $module => $values) {
    if (module_exists($module)) {
      $choices[$module] = check_plain($values['name']);
      $show_types[$module] = $values['types'];
    }
  }

  $form['show_types'] = array('#type' => 'value', '#value' => $show_types);

  if (isset($form_state['storage']['module_chosen'])) {
    $module = $form_state['storage']['module_chosen'];
    $form['module'] = array(
      '#type' => 'item',
      '#value' => t('You have chosen the "@module" module to provide data.', array('@module' => $module)),
      );
    // Now show the types, if appropriate.
    $button_text = t('Use this content type');
    $form['type'] = array(
      '#type' => 'radios',
      '#title' => t('Content types available to @module', array('@module' => $module)),
      '#options' => $form_state['storage']['types'],
      '#default_value' => variable_get('realname_profile_type', NULL),
      '#required' => TRUE,
      );
  }
  else {
    $button_text = t('Use this module');
    $form['module'] = array(
      '#type' => 'radios',
      '#title' => t('These modules are available for providing data to RealName'),
      '#options' => $choices,
      '#default_value' => variable_get('realname_profile_module', NULL),
      '#required' => TRUE,
      );
  }

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => $button_text,
    );
  return $form;
}

function realname_admin_module_submit($form, &$form_state) {
  if (isset($form_state['storage']['module_chosen'])) {
    variable_set('realname_profile_type', $form_state['values']['type']);
  }
  else {
    // First time here, so sav ethe chosen module and prepare types.
    $module = $form_state['values']['module'];
    variable_set('realname_profile_module', $module);
    $form_state['storage']['module_chosen'] = $module;

    $show_types = $form_state['values']['show_types'];
    if ($show_types[$module]) {
      include_once(drupal_get_path('module', 'realname') .'/realname_'. $module .'.inc');
      // This module uses types, so let's see what types are allowed.
      $types = call_user_func('realname_'. $module .'_get_types');
      if (count($types) > 1) {
        $form_state['storage']['types'] = $types;
        return;
      }
      else {
        variable_set('realname_profile_type', key($types));
      }
    }
  }
  // We are done with types, so go pick fields.
  unset($form_state['storage']);
  $form_state['redirect'] = 'admin/user/realname/fields'; // Go get the fields now.
  return;
}

function realname_admin_fields() {
  $form = array();
  $current = variable_get('realname_fields', array());
  $module = variable_get('realname_profile_module', NULL);
  $type = variable_get('realname_profile_type', NULL);
  // Do we have a module set yet?
  if (!$module) {
    drupal_goto('admin/user/realname/module');
  }

  $what = t('You are using the %module module to provide fields.', array('%module' => $module));
  if ($type) {
    $what .= t('The %type type is the source of data.', array('%type' => $type));
  }

  $form['heading'] = array(
    '#type' => 'item',
    '#value' => $what,
    );

  include_once(drupal_get_path('module', 'realname') .'/realname_'. $module .'.inc');
  $profile_fields = call_user_func('realname_'. $module .'_get_fields', $current, $type);
  $fields = $profile_fields['fields'];
  $links = $profile_fields['links'];
  uasort($fields, '_realname_sort');

  $form['start_table'] = array(
    '#type' => 'markup',
    '#value' => '<table><tr><th>Select</th><th>Field name</th><th>Weight</th></tr>',
    );

  $i = 0;
  foreach ($fields as $f_name => $values) {
    $form['field_select_'. $i] = array(
      '#type' => 'checkbox',
      '#default_value' => $values['selected'],
      '#prefix' => '<tr><td align="center">',
      '#suffix' => '</td>',
      );

    $form['field_name_'. $i] = array(
      '#type' => 'hidden',
      '#value' => $f_name,
      );

    $form['field_title_'. $i] = array(
      '#type' => 'item',
      '#value' => $values['title'],
      '#prefix' => '<td>',
      '#suffix' => '</td>',
      );

    $form['field_weight_'. $i] = array(
      '#type' => 'weight',
      '#delta' => 10,
      '#default_value' => $values['weight'],
      '#prefix' => '<td>',
      '#suffix' => '</td></tr>',
      );

    ++$i;
  }

  $form['end_table'] = array(
    '#type' => 'markup',
    '#value' => '</table>',
    );

  $form['realname_pattern'] = array(
    '#type' => 'textfield',
    '#field_prefix' => '<strong>'. t('Name Pattern') .'</strong> ',
    '#description' => t('The determines how the fields will be used to create the "Real name." Use "%1" to refer to the first field, "%2" to refer to the second, etc..'),
    '#size' => 30,
    '#default_value' => variable_get('realname_pattern', '%1'),
    );

  // If there were any URL fields, give a home page option.
  if ($links) {
    $links[''] = t('');
    asort($links);
    $form['homepage'] = array(
      '#type' => 'fieldset',
      '#title' => t('Homepage options'),
      '#description' => t('There were URL fields in the profile. If one of these is a personal homepage link, you may choose to link to it rather than the user profile. Choose which field to use.'),
      '#collapsible' => TRUE,
      '#collapsed' => count($links) == 0,
      );

    $form['homepage']['realname_homepage'] = array(
      '#type' => 'select',
      '#options' => $links,
      '#title' => t('Link to homepage'),
      '#description' => t('Select a personal homepage link, if appropriate.'),
      '#default_value' => variable_get('realname_homepage', NULL),
      );

    $form['homepage']['realname_nofollow'] = array(
      '#type' => 'checkbox',
      '#title' => t('Spam link deterrent'),
      '#description' => t('If enabled, Drupal will add rel="nofollow" to all links, as a measure to reduce the effectiveness of spam links. Note: this will also prevent valid links from being followed by search engines, therefore it is likely most effective when enabled for anonymous users.'),
      '#default_value' => variable_get('realname_nofollow', FALSE),
      );
  }
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
    );

  return $form;
}

/**
 * Form submit handler.
 */
function realname_admin_fields_submit($form, &$form_state) {
  $i = 0;
  $fields = array();
  // Run the form values to get all the fields they want.
  while (isset($form_state['values']['field_select_'. $i])) {
    if ($form_state['values']['field_select_'. $i]) {
      $fields[] = array('title' => $form_state['values']['field_name_'. $i], 'weight' => $form_state['values']['field_weight_'. $i]);
    }
    ++$i;
  }
  // A little hoop jumping to sort right.
  uasort($fields, '_realname_sort');
  $realname_fields = array();
  foreach ($fields as $key => $values) {
    $realname_fields[$values['title']] = $values['weight'];
  }

  // Okay, save the stuff.
  variable_set('realname_fields', $realname_fields);
  variable_set('realname_pattern', $form_state['values']['realname_pattern']);
  variable_set('realname_homepage', $form_state['values']['realname_homepage']);
  variable_set('realname_nofollow', $form_state['values']['realname_nofollow']);

  drupal_set_message(t('Configuration has been updated.'), 'status');
}