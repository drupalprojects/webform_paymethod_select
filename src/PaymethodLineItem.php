<?php
/**
 * @file
 */

namespace Drupal\webform_paymethod_select;

/**
 *
 */
class PaymethodLineItem extends \PaymentLineItem {
  public $amount_config;
  public $quantity_config;

  public function export() {
    $serialized = serialize($this);
    return "unserialize('$serialized')";
  }

  public function set_values($submission) {
    if ($this->amount_config['source'] == 'component') {
      $amount = $submission->valueByCid($this->amount_config['component']);
      $amount = str_replace(',', '.', $amount);
      $this->amount = $amount;
    }
    else {
      $this->amount = $this->amount_config['number'];
    }
    if ($this->quantity_config['source'] == 'component') {
      $quantity = $submission->valueByCid($this->quantity_config['component']);
      $quantity = str_replace(',', '.', $quantity);
      $this->quantity = $quantity;
    }
    else {
      $this->quantity = $this->quantity_config['number'];
    }
  }
}
