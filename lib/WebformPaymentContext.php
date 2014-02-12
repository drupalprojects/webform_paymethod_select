<?php
/**
 * @file
 */

namespace Drupal\webform_paymethod_select;
use Drupal\little_helpers\Webform\FormState;
use Drupal\little_helpers\Webform\Submission;

class WebformPaymentContext implements PaymentContextInterface {
  protected $submission;
  protected $nid;
  protected $sid;
  public $isRecurrent = FALSE;

  public function __construct($submission) {
    $this->submission = $submission;
  }

  public function value($key) {
    return $this->submission->valueByKey($key);
  }

  public function __sleep() {
    $this->nid = $this->submission->getNode()->nid;
    $this->sid = $this->submission->unwrap()->sid;

    return array('nid', 'sid');
  }

  public function __wakeup() {
    if (!empty($this->nid) && !empty($this->sid)) {
      $this->__construct(Submission::load($this->nid, $this->sid));
    }
  }
}