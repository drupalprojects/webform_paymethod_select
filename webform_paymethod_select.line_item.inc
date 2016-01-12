<?php
/**
 * @file
 */

/**
 * Implements hook_element_info_alter().
 */
function webform_paymethod_select_element_info_alter(&$element) {
  if (isset($element['payment_line_item']) == TRUE) {
    $element['payment_line_item']['#process'][]        = 'webform_paymethod_select_element_process_line_item';
    $element['payment_line_item']['#element_validate'] = array('webform_paymethod_select_element_validate_line_item');
  }
}

/**
 * Callback function set in hook_element_info_alter() hook
 */
function webform_paymethod_select_element_process_line_item(array $element, array &$form_state, array $form) {
  module_load_include('inc', 'webform', 'includes/webform.components');
  $node                   = webform_paymethod_select_get_node($form, $form_state);

  $form_builder = isset($form_state['payment_line_item_other_components']);
  if ($form_builder) {
    $other_components = $form_state['payment_line_item_other_components'];
  }
  else {
    $other_components = webform_component_list($node, FALSE);
    foreach ($other_components as $cid => $component) {
      if ($node->webform['components'][$cid]['type'] == 'paymethod_select') {
        unset($other_components[$cid]);
      }
    }
  }
  $components_disabled = empty($other_components);
  if ($components_disabled) {
    $other_components = array('' => t('No available components'));
  }

  $parents = isset($element['#parents']) ? $element['#parents'] : array('extra', 'line_items', 'items');

  foreach($element as $key => &$line_element) {

    if (strpos($key, 'container_') === 0) {
      $index = str_replace('container_', '', $key);
      $defaults = NULL;
      if (   isset($element['#default_value'][$index]) == TRUE
          && is_object($element['#default_value'][$index]) == TRUE) {
        $defaults = $element['#default_value'][$index];
      }
      else {
        $defaults = new \Drupal\webform_paymethod_select\PaymethodLineItem(array(
          'amount_config' => ['source' => 'number'],
          'quantity_config' => ['source' => 'number', 'number' => 1],
          'tax_rate' => 0,
        ));
      }

      if ($form_builder && is_numeric($defaults->amount['component'])) {
        $defaults->amount['component'] = 'cid_' . $defaults->amount['component'];
      }
      if ($form_builder && is_numeric($defaults->quantity_component)) {
        $defaults->quantity['component'] = 'cid_' . $defaults->quantity['component'];
      }

      $line_element['amount_config'] = [
        '#type' => 'webform_component_or_number',
        '#node' => $node,
        '#title_source_number' => t('Set fixed amount'),
        '#title_number' => t('Amount'),
        '#title_source_component' => t('Select the component from which to read the amount'),
        '#weight' => -6,
        '#default_value' => $defaults->amount_config,
      ];

      $line_element['quantity_config'] = [
        '#type' => 'webform_component_or_number',
        '#node' => $node,
        '#title_source_number' => t('Set fixed quantity'),
        '#title_number' => t('Amount'),
        '#title_source_component' => t('Select the component from which to read the quantity'),
        '#weight' => -5,
        '#default_value' => $defaults->quantity_config,
      ];
      unset($line_element['amount']);
      unset($line_element['quantity']);

      $line_element['description']['#wrapper_attributes'] = array(
        'class' => array('webform-paymethod-select-line-item-description'),
      );
    }
  }
  return $element;
}

/**
 * Callback function set in hook_element_info_alter() hook
 */
function webform_paymethod_select_element_validate_line_item(array $element, array &$form_state) {

  $values = drupal_array_get_nested_value($form_state['values'], $element['#parents']);

  // Don't let the submit button's value be validated.
  unset($values['add_more']);
  $line_items = array();

  foreach ($values as $container => $data) {

  // Skip empty line items
    if (empty($data['quantity']) && empty($data['tax_rate']) && empty($data['description'])) {
      continue;
    }
    if (is_numeric($data['tax_rate']) || $data['tax_rate'] >= 0) {
      $data['tax_rate'] = (float) $data['tax_rate'] / 100;
    }
    else {
      $elem = &$element[$container]['tax_rate'];
      form_error($elem, 'Tax rate must be a positive percentage.');
    }

    if (empty($data['description'])) {
      $elem = &$element[$container]['description'];
      form_error($elem, t('The line item description must not be empty.'));
    }

    // Convert the raw input to a PaymentLineItem object.
    $line_items[] = new \Drupal\webform_paymethod_select\PaymethodLineItem($data);
  }
  form_set_value($element, $line_items, $form_state);
}

/**
 * Implements hook_webform_component_presave().
 *
 * We use a fieldset to put the line_items inside in the webform component
 * edit form; but the payment module expects the line_item data to not be
 * nested inside a fieldset -> removing 1 nesting level here
 */
function webform_paymethod_select_webform_component_presave(&$component) {
  if (isset($component['extra']['line_items']['items'])) {
    $line_items = &$component['extra']['line_items']['items'];
    unset($component['extra']['line_items']['items']);
    $component['extra']['line_items'] = &$line_items;
  }
}
