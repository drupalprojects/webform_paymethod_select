<?php
/**
 * @file
 */

namespace Drupal\webform_paymethod_select;
use Drupal\little_helpers\Webform\Webform;

class WebformPaymentContext implements PaymentContextInterface {
  protected $submission;
  protected $form_state;

  public function __construct($submission, &$form_state) {
    $this->submission = $submission;
    $this->form_state = &$form_state;
  }

  public function __sleep() {
    return array('submission');
  }

  public function getSubmission() {
    return $this->submission;
  }

  public function getSuccessUrl() {
    $submission = $this->submission ? $this->submission->unwrap() : NULL;
    return $this->submission->webform->getRedirectUrl($submission);
  }

  public function reenterLink(\Payment $payment) {
    $link['path'] = 'node/' . $this->submission->getNode()->nid;
    return $link;
  }

  public function getErrorUrl() {
    return NULL;
  }

  public function value($key) {
    return $this->submission->valueByKey($key);
  }

  public function valueByKeys(array $keys) {
    foreach ($keys as $k) {
      $v = $this->submission->valueByKey($k);
      if ($v) {
        return $v;
      }
    }
  }

  public function redirect($path, array $options = array()) {
    if ($this->form_state) {
      $this->form_state['redirect'] = array($path, $options);
    }
    else {
      drupal_goto($path, $options);
    }
  }

  public function redirectToStatus(\Payment $payment) {
    $options['query']['wpst'] = webform_paymethod_select_hmac($payment);
    $this->redirect('webform-payment/error/' . $payment->pid, $options);
  }

  public function statusPending(\Payment $payment) {
    // Only redirect to the status page if we are not in the form submit process.
    if (empty($this->form_state)) {
      $this->redirectToStatus($payment);
    }
  }

  public function statusSuccess(\Payment $payment) {
    if (!$this->form_state) {
      $this->redirect($this->getSuccessUrl());
    }
  }
}
