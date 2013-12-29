<?php

namespace Drupal\webform_paymethod_select;

interface PaymentContextInterface {

  public function setData(&$data);

  public function dataValue($key);

  public function dataValues(array $keys);

}
