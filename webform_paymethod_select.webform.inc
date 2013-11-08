<?php

/**
 * @file
 *
 * @author    Matthias Weiss <matthias@more-onion.com>
 * @copyright Copyright (c) 2013 copyright
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

//function _webform_component_paymethod_select_payment_method_options(&$component) {
function _webform_component_paymethod_select_payment_method_options() {
  /*if (isset($component['extra']['payment']) == TRUE) {
    $payment = $component['extra']['payment'];
  }
  else {
    $component['extra']['payment'] = $payment = webform_component_paymethod_select_create_payment();
    }*/

  $payment = new Payment(
    array(
      'currency_code'   => 'EUR',
      'description'     => t('Default Payment'),
      'finish_callback' => 'webform_component_paymethod_select_payment_finish',
    )
  );
  //$payment = webform_component_paymethod_select_create_payment();

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
    //'#options'       => _webform_component_paymethod_select_payment_method_options($component),
    '#options'       => _webform_component_paymethod_select_payment_method_options(),
    '#default_value' => array_keys($component['extra']['selected_payment_methods']),
  );

  $form['extra']['payment_line_items'] = array(
    '#title'         => t('Line items'),
    '#type'          => 'payment_line_item',
    '#cardinality'   => 0,
    '#default_value' => $component['extra']['payment_line_items'],
    '#required'      => TRUE,
    );

  include_once drupal_get_path('module', 'webform_component_paymethod_select') . '/currency_codes.inc.php';

  $form['extra']['currency_code'] = array(
    '#type'          => 'select',
    '#title'         => t('Select a currency code'),
    '#multiple'      => FALSE,
    '#descriptions'  => t('Select the currency code for this payment.'),
    '#options'       => $currency_codes,
    '#default_value' => 'EUR',
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
    $payment = $component['extra']['payment'] = webform_component_paymethod_select_create_payment();
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
    $element['#available_payment_methods'] = !empty($component['extra']['available_payment_methods']) ? _webform_component_paymethod_select_payment_method_options($component) : t('There are no payment methods available for selection. Please also check /admin/config/services/payment/method wether you need to define a payment method.');*/


  return $element;
}

/*
function _webform_submit_paymethod_select($component, $value) {

  dpm($component, __FUNCTION__ . ': component');
  dpm($value, __FUNCTION__ . ': value');

  return $value;
}
*/

