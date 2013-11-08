<?php

namespace Drupal\webform_component_paymethod_select;

class WebformPaymentContext implements PaymentContextInterface {
  protected $webform;
  protected $submission;

  public function __construct(\Drupal\little_helpers\Webform $webform, \Drupal\little_helpers\WebformSubmission $submission = NULL) {
    $this->webform = $webform;
    $this->submission = $submission;
  }

  public function setSubmission(\Drupal\little_helpers\WebformSubmission $submission) {
    $this->submission = $submission;
  }

  public function getSubmission() {
    return $this->submission;
  }

  public static function fromNode($node) {
    return new static(\Drupal\little_helpers\Webform::fromNode($node));
  }

  public function getSuccessUrl() {
    return $this->webform->getRedirectUrl($this->submission->unwrap());
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
