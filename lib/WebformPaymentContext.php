<?php
/**
 * @file
 */

namespace Drupal\webform_paymethod_select;
use Drupal\little_helpers\Webform\FormState;
use Drupal\little_helpers\Webform\Submission;

class WebformPaymentContext implements PaymentContextInterface {
  public $submission;
  public $nid;
  public $sid;
  public $methodData;
  public $isRecurrent = FALSE;

  public function __construct($submission) {
    $this->submission = $submission;
  }

  public function value($key) {
    $result = FALSE;
    if (   ($result = $this->submission->valueByKey($key)) == FALSE
         && isset($this->methodData[$key]) == TRUE) {
      $result = $this->methodData[$key];
    }

    return $result;
  }

  public function __sleep() {
    $this->nid = $this->submission->getNode()->nid;
    $this->sid = $this->submission->unwrap()->sid;

    return array('nid', 'sid', 'methodData');
  }

  public function __wakeup() {
    if (!empty($this->nid) && !empty($this->sid)) {
      $this->__construct(Submission::load($this->nid, $this->sid));
    }
  }
}