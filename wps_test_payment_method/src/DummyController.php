<?php

namespace Drupal\wps_test_method;

use \Drupal\payment_forms\PaymentContextInterface;

class DummyController extends \PaymentMethodController {
  public $payment_configuration_form_elements_callback = '\\Drupal\\wps_test_method\\form_elements';
  public function __construct() {
    $this->title = 'Dummy payment method.';
    $this->description = 'This payment method allows to mock payment provider behavior.';
  }

  /**
   * Implements PaymentMethodController::validate().
   */
  function validate(\Payment $payment, \PaymentMethod $payment_method, $strict) {
    if (!$strict)
      return;

    sleep($payment->context_data['method_data']['validate_timeout']);
  }

  /**
   * Implements PaymentMethodController::execute().
   */
  function execute(\Payment $payment) {
    $data = &$payment->context_data['method_data'];
    $redirect = NULL;

    if (!empty($data['redirect'])) {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_PENDING));
      $path = WPS_TEST_PAYMENT_REDIRECT_URL . (int) $payment->pid;
      $redirect = array($path, array());
    }
    else {
      $payment->setStatus(new \PaymentStatusItem($data['status']));
    }

    if ($redirect) {
      $payment->contextObj->redirect($redirect[0], $redirect[1]);
    }
  }
}

function form_elements(array $element, array &$form_state) {
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

  return $element;
}

function form_elements_validate(array $element, array &$form_state) {
  $values =& $form_state['values'];
  foreach ($element['#parents'] as $key) {
    $values =& $values[$key];
  }
  $form_state['payment']->context_data['method_data'] = $values;
  $form_state['payment']->form_state = &$form_state;
}
