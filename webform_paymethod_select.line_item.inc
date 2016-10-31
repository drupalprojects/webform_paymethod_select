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
        $defaults = new Drupal\webform_paymethod_select\PaymethodLineItem(array(
          'amount' => '',
          'amount_source' => 'fixed',
          'quantity' => 1,
          'quantity_source' => 'fixed',
          'tax_rate' => 0,
        ));
      }

      $form_builder = isset($form_state['webform_paymethod_select_other_components']);
      if ($form_builder && is_numeric($defaults->amount_component)) {
        $defaults->amount_component = 'cid_' . $defaults->amount_component;
      }
      if ($form_builder && is_numeric($defaults->quantity_component)) {
        $defaults->quantity_component = 'cid_' . $defaults->quantity_component;
      }

      $radio_id = drupal_html_id('webform-paymethod-select-' . $key . '-amount-source');
      $line_element['amount'] = array(
        '#type' => 'container',
        '#weight' => -9,
      );

      $line_element['amount']['source'] = array(
        '#title' => t('Amount'),
        '#attributes' => array('class' => array($radio_id)),
        '#type' => 'radios',
        '#options' => array(
          'fixed' => t('Set fixed amount'),
          'component' => t('Read the amount from another form element.'),
        ),
        '#default_value' => $defaults->amount_source,
      );

      $line_element['amount']['value'] = array(
        '#type'          => 'textfield',
        '#size'          => '20',
        '#maxlength'     => '20',
        '#required'      => FALSE,
        '#default_value' => $defaults->amount,
        '#wrapper_attributes' => array(
          'class' => array('webform-paymethod-select-component-or-value-option'),
        ),
        '#states'        => array(
          'visible' => array(".$radio_id" => array('value' => 'fixed')),
        ),
      );

      $line_element['amount']['component'] = array(
        '#default_value' => $defaults->amount_component,
        '#required'      => FALSE,
        '#wrapper_attributes' => array(
          'class' => array('webform-paymethod-select-component-or-value-option'),
          ),
        '#states'        => array(
          'visible' => array(".$radio_id" => array('value' => 'component')),
        ),
      ) + _webform_paymethod_select_other_component_selector($form, $form_state);

      $line_element['tax_rate']['#wrapper_attributes'] = array(
        'class' => array('webform-paymethod-select-line-item-tax-rate'),
      );

      $radio_quantity = drupal_html_id('webform-paymethod-select-' . $key . '-quantity_source');
      $line_element['quantity'] = array(
        '#type' => 'container',
        '#weight' => -8,
      );
      $line_element['quantity']['source'] = array(
        '#title' => t('Quantity'),
        '#attributes'    => array('class' => array($radio_quantity)),
        '#type'          => 'radios',
        '#options' => array(
          'fixed' => t('Set fixed quantity'),
          'component' => t('Get quantity from another form element'),
        ),
        '#default_value' => $defaults->quantity_source,
      );

      $line_element['quantity']['value'] = array(
        '#type'          => 'textfield',
        '#size'          => '20',
        '#maxlength'     => '20',
        '#required'      => FALSE,
        '#default_value' => $defaults->quantity,
        '#wrapper_attributes' => array(
          'class' => array('webform-paymethod-select-component-or-value-option'),
        ),
        '#states'        => array(
          'visible' => array(".$radio_quantity" => array('value' => 'fixed')),
        ),
      );
      $line_element['quantity']['component'] = array(
        '#default_value' => $defaults->quantity_component,
        '#required'      => FALSE,
        '#wrapper_attributes' => array(
          'class' => array('webform-paymethod-select-component-or-value-option'),
          ),
        '#states'        => array(
          'visible' => array(".$radio_quantity" => array('value' => 'component')),
        ),
        '#weight' => -2,
      ) + _webform_paymethod_select_other_component_selector($form, $form_state);
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
    if (empty($data['quantity']['amount']) && empty($data['tax_rate']) && empty($data['description'])) {
      continue;
    }

    $data['quantity_source'] = $data['quantity']['source'];
    $data['quantity_component'] = NULL;
    if ($data['quantity']['source'] == 'fixed') {
      $v = $data['quantity']['value'];
      // They're not all empty, so make sure they all contain input.
      if ((int) $v == $v && $v >= 0) {
        $data['quantity'] = (int) $data['quantity'];
      } else {
        $elem = &$element[$container]['quantity']['value'];
        form_error($elem, t('Quantity needs to be a non-negative number.'));
      }
    }
    else {
      $data['quantity_component'] = $data['quantity']['component'];
      $data['quantity'] = NULL;
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

    $data['amount_source'] = $data['amount']['source'];
    $data['amount_component'] = NULL;
    if ($data['amount']['source'] == 'fixed') {
      $v = $data['amount']['value'];
      if (is_numeric($v) && $v >= 0) {
        $data['amount'] = (float) $v;
      }
      else {
        $elem = &$element[$container]['amount']['value'];
        form_error($elem, 'Amount must be a positive number or 0.');
      }
    } else {
      $data['amount_component'] = $data['amount']['component'];
      $data['amount'] = NULL;
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
