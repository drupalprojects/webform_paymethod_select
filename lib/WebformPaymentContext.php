<?php
/**
 * @file
 */

namespace Drupal\webform_paymethod_select;
use Drupal\little_helpers\Webform\FormState;
use Drupal\little_helpers\Webform\Submission;

class WebformPaymentContext implements PaymentContextInterface {
  protected $submission;
  public $isRecurrent = FALSE;

  public function __construct($submission) {
    $this->submission = $submission;
  }

  public static function fromFormState($node, &$form_state) {
    return new static(new FormState($node, $form_state));
  }

  public function value($key) {
    return $this->submission->valueByKey($key);
  }

  public function transform() {
    if ($this->submission instanceof FormState) {
      if ($submission = $this->submission->getSubmission()) {
        $this->submission = $submission;
      }
    }
  }

  public function submission() {
    return $this->submission;
  }
}
