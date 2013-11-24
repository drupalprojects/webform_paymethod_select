<?php

namespace Drupal\webform_paymethod_select;

interface PaymentContextInterface {

  public function collectContextData();

  public function getLineItems();

 /**
   * Return an absolute URL to redirect the user to
   * when the payment was successfull.
   *
   * @return url
   */
  public function getSuccessUrl();

  /**
   * Return an absolute URL to redirect the user to
   * when the payment was not successfull.
   *
   * @return url
   */
  public function getErrorUrl();

  public static function getEditForm(array $component);
}
