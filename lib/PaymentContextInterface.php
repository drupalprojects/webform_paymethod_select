<?php

namespace Drupal\webform_paymethod_select;

interface PaymentContextInterface {

  public function setData(&$data);

  public function value($key);

  public function values(array $keys);

}
