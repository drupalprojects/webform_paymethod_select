<?php

namespace Drupal\webform_paymethod_select;

class WebformPaymentContext implements PaymentContextInterface {
  public $nid;
  public $sid;
  protected $data;
  protected $cid_2_form_key;

  // ******************* construction *******************
  public function __construct($nid, $sid = NULL, $data = NULL, $cid_2_form_key = NULL) {
    $this->nid            = $nid;
    $this->sid            = $sid;
    $this->data           = $data;
    $this->cid_2_form_key = $cid_2_form_key;
  }

  public static function fromWebformData(&$form_state) {
    $node = $form_state['complete form']['#node'];
    dpm($node, __FUNCTION__ . ': node');
    dpm($form_state, __FUNCTION__ . ': form_state');
    if (isset($node) == TRUE) {
      $sid     = isset($node->webform['sid']) ? $node->webform['sid'] : NULL;
      $context = new static($node->nid, $sid);

      foreach ($node->webform['components'] as $component) {
        $form_key                      = $component['form_key'];
        $cid                           = (int) $component['cid'];
        $context->cid_2_form_key[$cid] = $form_key;

        if (isset($form_state['values'][$form_key])) {
          $context->data[$form_key] = $form_state['values'][$form_key];
        }
        elseif (isset($form_state['values']['submitted'][$cid]) == TRUE) {
          $context->data[$form_key] = $form_state['values']['submitted'][$cid];
        }
        elseif (isset($form_state['values']['submitted'][$form_key]) == TRUE) {
          $context->data[$form_key] = $form_state['values']['submitted'][$form_key];
        }
        elseif (   isset($form_state['storage']) == TRUE
                && isset($form_state['storage']['submitted'][$cid]) == TRUE) {
          $context->data[$form_key] = $form_state['storage']['submitted'][$cid];
        }
      }
      return $context;
    }
    else {
      return NULL;
    }
  }

  // ****************************************************

  public function setCid2FormKey($cid_2_form_key) {
    $this->cid_2_form_key = $cid_2_form_key;
  }

  public function cid2FormKey($cid) {
    return isset($this->cid_2_form_key[$cid]) ? $this->cid_2_form_key[$cid] : FALSE;
  }

  public function setData(&$data) {
    return $this->data = $data;
  }

  public function dataValue($key) {
    return isset($this->data[$key]) ? $this->data[$key] : FALSE;
  }

  public function dataValues(array $keys) {
    $values = array();
    foreach ($keys as $key) {
      $values[$key] = isset($this->data[$key]) ? $this->data[$key] : FALSE;
    }
    return $values;
  }

  public function setDataValue($key, $data) {
    $this->data[$key] = $data;
  }
}
