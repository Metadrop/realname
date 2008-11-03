<?php
// $Id$
/**
 * @file
 * Realname module support for Content Profile module.
 */

/**
 * Implementation of hook_profile_load();
 */
function content_profile_load_profile(&$account, $type = NULL) {
  $profile = content_profile_load($type, $account->uid);
  if (!$profile) {
    return;
  }
  $fields = content_fields(NULL, $type);
  foreach ($fields as $field_name => $field_attributes) {
    $values = array();
      $contents = $profile->$field_name;
      foreach ($contents as $content) {
        if (isset($content['value'])) {
          $values[] = $content['value'];
        }
        else {
          $values[] = content_format($field_name, $content);
        }
      }
    if (empty($account->{$field_name})) {
      switch (count($values)) {
        case 0:
          $account->{$field_name} = NULL;
          break;
        case 1:
          $account->{$field_name} = $values[0];
          break;
        default:
          $account->{$field_name} = $values;
      }
    }
  }
}

function realname_content_profile_get_types() {
  return content_profile_get_types('names');
}

function realname_content_profile_get_fields($current) {
  $fields = $links = array();
  $all_fields = content_fields(NULL, $type);
  foreach ($all_fields as $field_name => $field_attributes) {
    switch ($field_attributes['type']) {
      case 'text':
        if (!$field_attributes['multiple']) {
          $selected = array_key_exists($field_name, $current);
          $fields[$field_name] = array(
            'title' => $field_attributes['widget']['label'],
            'weight' => $selected ? $current[$field_name] : 0,
            'selected' => $selected,
            );
        }
        break;

      case 'link':
        $links[$field_name] = $field_attributes['widget']['label'];
    }
  }
  return array('fields' => $fields, 'links' => $links);
}
