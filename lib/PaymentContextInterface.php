<?php

namespace Drupal\webform_paymethod_select;

interface PaymentContextInterface {
  public function value($key);

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
  /**
   * Return a path that can be used to re-enter the form if the payment failed.
   *
   * @return a link array
   */
  public function reenterLink(\Payment $payment);
  /**
   * Redirect user in a to a given url.
   * Parameters ar the same as for drupal_goto()
   *
   * @param $path string
   * @param $options array
   */
  public function redirect($path, array $options = array());
}
