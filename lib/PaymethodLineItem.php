<?php
/**
 * @file
 */

namespace Drupal\webform_paymethod_select;

/**
 *
 */
class PaymethodLineItem extends \PaymentLineItem {
  public $component_or_fixed = 'fixed';
  public $amount_component   = NULL;
}
