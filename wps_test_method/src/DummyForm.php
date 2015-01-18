<?php

namespace Drupal\wps_test_method;

use \Drupal\payment_forms\FormInterface;

class DummyForm implements FormInterface {
  public function getForm(array &$element, array &$form_state, \Payment $payment) {
    $options = array();
    foreach (payment_statuses_info() as $info) {
      $options[$info->status] = $info->title;
    }
    $element['status'] = array(
      '#type' => 'select',
      '#title' => 'Target status',
      '#description' => 'The payment processing will finish with this status.',
      '#options' => $options,
    );

    $element['validate_timeout'] = array(
      '#type' => 'select',
      '#title' => 'Validate timeout.',
      '#description' => 'Timeout before redirecting to another URL.',
      '#options' => array(0 => 'Zero', 1 => '1 second', 5 => '5 seconds', 30 => '30 seconds', 120 => '2 minutes'),
    );

    $element['redirect'] = array(
      '#type' => 'checkbox',
      '#title' => 'Redirect',
      '#description' => 'Simulate a payment method that needs to redirect the user to an external page.',
    );
  }

  public function validateForm(array &$element, array &$form_state, \Payment $payment) {
    $values =& $form_state['values'];
    foreach ($element['#parents'] as $key) {
      $values =& $values[$key];
    }
    $payment->context_data['method_data'] = $values;
    $payment->form_state = &$form_state;
  }
}
