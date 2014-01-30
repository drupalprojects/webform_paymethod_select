<?php
/**
 * @file
 */

namespace Drupal\webform_paymethod_select;
use Drupal\little_helpers\Interfaces;

class WebformPaymentContext extends WebformFormState {
  public $isRecurrent = FALSE;

  // ********* convenience methods  *********

  public static function fromFormState(array &$form_state) {
    $node = NULL;
    if (!empty($form_state['complete form']['#node'])) {
      $node = $form_state['complete form']['#node'];
    }
    else {
      $node = webform_paymethod_select_get_node();
    }

    if (isset($node) == TRUE) {
      return new static($node, $form_state);
    }
    else {
      return NULL;
    }
  }
}
