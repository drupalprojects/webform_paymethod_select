<?php
/**
 * @file
 */

namespace Drupal\webform_paymethod_select;
use Drupal\little_helpers\Webform\Webform;

class WebformPaymentContext implements PaymentContextInterface {
  protected $submission;

  public function __construct($submission) {
    $this->submission = $submission;
  }

  public function getSubmission() {
    return $this->submission;
  }

  public function getSuccessUrl() {
    $submission = $this->submission ? $this->submission->unwrap() : NULL;
    return $this->submission->webform->getRedirectUrl($submission);
  }

  public function reenterLink(\Payment $payment) {
    $link['path'] = 'node/' . $this->submission->weform->nid;
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
}
