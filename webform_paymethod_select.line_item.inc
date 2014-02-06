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
  $node                   = webform_paymethod_select_get_node($form);
  $webform_component_list = webform_component_list($node, FALSE);

  foreach ($webform_component_list as $cid => $component) {
    if ($node->webform['components'][$cid]['type'] == 'paymethod_select') {
      unset($webform_component_list[$cid]);
    }
  }

  foreach($element as $key => &$value) {

    if (strpos($key, 'container_') === 0) {
      $index = str_replace('container_', '', $key);
      $defaults = NULL;
      if (   isset($element['#default_value'][$index]) == TRUE
          && is_object($element['#default_value'][$index]) == TRUE) {
        $defaults = $element['#default_value'][$index];
      }
      else {
        $defaults = new Drupal\webform_paymethod_select\PaymethodLineItem(array());
      }

      $defaults->amount = ($defaults->amount == 0) ? '' : $defaults->amount;
      $line_item_form = array(
        'amount_source' => array(
          '#title'         => t('Set fixed amount'),
          '#type'          => 'radio',
          '#return_value'  => 'fixed',
          '#default_value' => $defaults->amount_source == 'fixed' ? 'fixed' : NULL,
          '#required'      => FALSE,
          '#parents'       => array('extra','line_items','items',$key, 'amount_source'),
        ),
      );

      $line_item_form['amount'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Amount'),
        '#size'          => '20',
        '#maxlength'     => '20',
        '#required'      => FALSE,
        '#default_value' => $defaults->amount,
      );

      $line_item_form['component'] = array(
        '#title'         => t('Select the component from which to read the amount'),
        '#type'          => 'radio',
        '#return_value'  => 'component',
        '#default_value' => $defaults->amount_source == 'component' ? 'component' : NULL,
        '#required'      => FALSE,
        '#parents'       => array('extra','line_items','items',$key, 'amount_source'),
      );

      $line_item_form['amount_component'] = array(
        '#type'          => 'select',
        '#title'         => t('Component'),
        '#title_display' => 'invisible',
        '#default_value' => $defaults->amount_component,
        '#options'       => empty($webform_component_list) ? array('' => t('No available components')) : $webform_component_list,
        '#disabled'      => empty($webform_component_list) ? TRUE : FALSE,
        '#required'      => FALSE,
      );

      $value = $line_item_form + $value;
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
  foreach ($values as $container => $line_item_data) {
    // ignore empty line items
    if (   empty($line_item_data['quantity']) == TRUE
        && empty($line_item_data['tax_rate']) == TRUE
        && empty($line_item_data['description']) == TRUE) {
      continue;
    }
    // They're not all empty, so make sure they all contain input.
    else {
      $errors = array_fill_keys(array_keys($element['#value']), FALSE);
      foreach ($line_item_data as $property => $value) {
        if (!in_array($property, array('amount', 'component', 'amount_component')) && empty($value)) {
          form_error($element[$container][$property], t('%title is required, or leave all fields for this line item empty.', array(
            '%title' => $element[$container][$property]['#title'],
          )));
        }
      }
      if ($line_item_data['amount_source'] === 'component' && empty($line_item_data['amount_component'])) {
        form_error($element[$container]['amount_component'], t('Select a component that is used as source for the amount.'));
      }
      if ($line_item_data['amount_source'] === 'fixed' && empty($line_item_data['amount']) == TRUE) {
        form_error($element[$container]['amount'], t('Amount has to be set if "Set fixed amount" was selected.'));
      }
    }

    // Validate quantity.
    if (preg_match('#\D#', $line_item_data['quantity'])) {
      form_error($element[$container]['quantity'], t('Quantity should be a positive integer.'));
    }

    // Validate tax rate.
    $tax_rate = str_replace(',', '.', $line_item_data['tax_rate']);
    if (!is_numeric($tax_rate) || $tax_rate < 0) {
      form_error($element, t('Tax rate must be a positive percentage.'));
    }
    else {
      $line_item_data['tax_rate'] = $tax_rate / 100;
    }

    // Convert the raw input to a PaymentLineItem object.
    $line_item_data['amount']           = $line_item_data['amount_source'] === 'fixed' ? (float) $line_item_data['amount'] : NULL;
    $line_item_data['amount_component'] = $line_item_data['amount_source'] === 'component' ? $line_item_data['amount_component'] : NULL;
    $line_item_data['quantity']         = (int) $line_item_data['quantity'];
    $line_item_data['tax_rate']         = (float) $line_item_data['tax_rate'];
    $line_items[]                       = new Drupal\webform_paymethod_select\PaymethodLineItem($line_item_data);
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
  $line_items = &$component['extra']['line_items']['items'];
  unset($component['extra']['line_items']['items']);
  $component['extra']['line_items'] = &$line_items;
}
