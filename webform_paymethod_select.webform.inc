<?php
/**
 * @file
 *
 * @author    Matthias Weiss <matthias@more-onion.com>
 */


/**
 * Implements _webform_defaults_[component]().
 */
function _webform_defaults_paymethod_select() {
  $element = array(
    'name'      => 'Payment Method Selector',
    'form_key'  => NULL,
    'mandatory' => 1,
    'value'     => '',
    'pid'       => 0,
    'weight'    => 0,
    'extra' => array(
      'payment_description'      => 'Default Payment',
      'selected_payment_methods' => NULL,
      'payment_line_items'       => array(),
      'private'                  => FALSE,
    ),
  );

  return $element;
}

//function _webform_paymethod_select_payment_method_options(&$component) {
function _webform_paymethod_select_payment_method_options() {
  /*if (isset($component['extra']['payment']) == TRUE) {
    $payment = $component['extra']['payment'];
  }
  else {
    $component['extra']['payment'] = $payment = webform_paymethod_select_create_payment();
    }*/

  $payment = new Payment(
    array(
      'currency_code'   => 'EUR',
      'description'     => t('Default Payment'),
      'finish_callback' => 'webform_paymethod_select_payment_finish',
    )
  );
  //$payment = webform_paymethod_select_create_payment();

  foreach ($payment->availablePaymentMethods() as $pmid => $payment_method) {
    $payment_method_options[(int) $pmid] = $payment_method->title_generic;
  }

  return $payment_method_options;
}

/**
 * Implements _webform_edit_[component]().
 */
function _webform_edit_paymethod_select($component) {

  $form = array(
    'extra' => array(
      'payment_description' => array(
        '#type'          => 'textfield',
        '#title'         => t('Payment Description'),
        '#default_value' => $component['extra']['payment_description'],
        '#required'      => TRUE,
      )
    )
  );

  $form['extra']['selected_payment_methods'] = array(
    '#title'         => t('Selected Payment Methods'),
    '#type'          => 'select',
    '#multiple'      => TRUE,
    '#description'   => 'Select 1 or more payment methods that will be available for seletion to the user.',
    //'#options'       => _webform_paymethod_select_payment_method_options($component),
    '#options'       => _webform_paymethod_select_payment_method_options(),
    '#default_value' => array_keys($component['extra']['selected_payment_methods']),
  );

  $form['extra']['payment_line_items'] = array(
    '#title' => t('Line Item(s)'),
    '#type'  => 'fieldset',
  );

  $form['extra']['payment_line_items']['fixed_or_hook'] = array(
    '#title' => t('Choose how to handle line items and currency code'),
    '#type'  => 'radios',
    '#default_value' => isset($component['extra']['payment_line_items']['fixed_or_hook']) ? $component['extra']['payment_line_items']['fixed_or_hook'] : 'fixed',
    '#description' => t('You can set 1 or more fixed line items and currency code here or don\'t set any and set both via hook'),
  );

  $form['extra']['payment_line_items']['fixed_or_hook']['#options'] = array(
    'hook'  => t('Use hook to set line items'),
    'fixed' => t('Fixed set of line items'),
  );

  $form['extra']['payment_line_items']['line_items'] = array(
    '#title'         => t('Line items'),
    '#type'          => 'payment_line_item',
    '#cardinality'   => 0,
    '#default_value' => $component['extra']['payment_line_items'],
    '#required'      => TRUE,
  );

  include_once drupal_get_path('module', 'webform_paymethod_select') . '/currency_codes.inc.php';

  $form['extra']['payment_line_items']['currency_code'] = array(
    '#type'          => 'select',
    '#title'         => t('Select a currency code'),
    '#multiple'      => FALSE,
    '#descriptions'  => t('Select the currency code for this payment.'),
    '#options'       => $currency_codes,
    '#default_value' => 'EUR',
  );

  $form['extra']['amount'] = array(
    '#title' => t('Payment Amount'),
    '#type'  => 'fieldset',
  );

  $form['extra']['amount']['component_or_hook'] = array(
    '#title' => t('Choose how to set the amount for the line item(s)'),
    '#type'  => 'radios',
    '#default_value' => isset($component['extra']['amount']['component_or_hook']) ? $component['extra']['amount']['component_or_hook'] : 'component',
    '#description' => t('You can select the webform component from which to read the amount here or don\'t use the webform and set the amount via hook'),
  );

  $form['extra']['amount']['component_or_hook']['#options'] = array(
    'hook'  => t('Use hook to set the amount'),
    'component' => t('Select the component of this webform from which to read the amount'),
  );

  $options = webform_component_list($node, $field == 'from_address' || $field == 'email' ? 'email_address' : 'email_name', FALSE);

  $form['extra']['amount']['component'] = array(
    '#type' => 'select',
    '#default_value' =>  isset($component['extra']['amount']['component']) ? $component['extra']['amount']['component'] : NULL,
    '#options' => empty($options) ? array('' => t('No available components')) : $options,
    '#disabled' => empty($options) ? TRUE : FALSE,
    //'#weight' => 6,
  );

  return $form;
}

/**
 * Implements _webform_render_[component]().
 */
function _webform_render_paymethod_select($component, $value = NULL, $filter = TRUE) {

  //dpm($component, __FUNCTION__ . ': component');

  $pmids = array();

  if (!empty($component['extra']['selected_payment_methods'])) {
    foreach ($component['extra']['selected_payment_methods'] as $pmid) {
      $pmids[] = (int) $pmid;
    }
  }

  /*if (empty($component['extra']['payment']) == TRUE) {
    dpm(__FUNCTION__ . ': kein payment objekt');
    $payment = $component['extra']['payment'] = webform_paymethod_select_create_payment();
  }
  else {
    dpm(__FUNCTION__ . ': payment objekt existiert');
    $payment = &$component['extra']['payment'];
    }*/

  /*if (!empty($component['extra']['payment'])) {
    $access = payment_status_is_or_has_ancestor($component['extra']['payment']->getStatus()->status, PAYMENT_STATUS_NEW);
  }
  else {
    $access = TRUE;
    }*/

  /*
  $form_state            = form_state_defaults();
  $form_state['payment'] = &$payment;*/

  $element['#type']                     = 'paymethod_select';
  //$element['#access']                   = $access;
  $element['#title']                    = t('Available payment methods');
  $element['#required']                 = TRUE;
  $element['#selected_payment_methods'] = $pmids;
  $element['#weight']                   = isset($component['weight']) == TRUE ? $component['weight'] : 0;
  $element['#default_value']            = array_shift($pmids);

  /*$element['#payment_description']       = !empty($component['extra']['payment_description']) ? $component['extra']['payment_description'] : '';
    $element['#available_payment_methods'] = !empty($component['extra']['available_payment_methods']) ? _webform_paymethod_select_payment_method_options($component) : t('There are no payment methods available for selection. Please also check /admin/config/services/payment/method wether you need to define a payment method.');*/


  return $element;
}

/*
function _webform_submit_paymethod_select($component, $value) {

  dpm($component, __FUNCTION__ . ': component');
  dpm($value, __FUNCTION__ . ': value');

  return $value;
}
*/

