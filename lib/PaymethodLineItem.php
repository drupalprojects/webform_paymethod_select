<?php
/**
 * @file
 */

namespace Drupal\webform_paymethod_select;

/**
 *
 */
class PaymethodLineItem extends \PaymentLineItem {
  public $amount_source    = 'fixed';
  public $amount_component = NULL;
}
