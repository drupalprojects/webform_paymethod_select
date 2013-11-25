<?php

namespace Drupal\webform_paymethod_select;

class WebformPaymentContext implements PaymentContextInterface {
  protected $webform;
  protected $submission;

  // ******************* construction *******************
  public function __construct(\Drupal\little_helpers\Webform $webform, \Drupal\little_helpers\WebformSubmission $submission = NULL) {
    $this->webform = $webform;
    $this->submission = $submission;
  }

  public static function fromNode($node) {
    return new static(\Drupal\little_helpers\Webform::fromNode($node));
  }

  // ****************************************************

  public function setSubmission(\Drupal\little_helpers\WebformSubmission $submission) {
    $this->submission = $submission;
  }

  public function getSubmission() {
    return $this->submission;
  }

  public function value($key) {
    return $this->submission->valueByKey($key);
  }

  public function valueByKeys(array $keys) {
    foreach ($keys as $k) {
      $v = $this->submission->valueByKey($k);
      if ($v) {
        return $v;
      }
    }
  }

  public static function getEditForm(array $component) {

    dpm($component, 'component');
    $node = node_load($component['nid']);

    $settings = drupal_array_merge_deep(
      array(
        'currency_code' => 'EUR',
        'line_items'    => NULL,
      ),
      $component['extra']['context_settings']
    );
    dpm($settings, 'settings');

    include_once drupal_get_path('module', 'webform_paymethod_select') . '/currency_codes.inc.php';

    $form['currency_code'] = array(
      '#type'          => 'select',
      '#title'         => t('Select a currency code'),
      '#multiple'      => FALSE,
      '#descriptions'  => t('Select the currency code for this payment.'),
      '#options'       => $currency_codes,
      '#default_value' => $settings['currency_code'],
    );


    $form['line_items'] = array(
      '#title'         => t('Line items'),
      '#type'          => 'payment_line_item',
      '#cardinality'   => 0,
      '#default_value' => $settings['line_items'],
      '#required'      => TRUE,
      '#currency_code' => $settings['currency_code'],
    );

    return $form;
  }

  public static function lineItemFormProcessAlter(&$element, &$form, &$form_state) {
    dpm(__FUNCTION__);
    dpm($element, 'element');
    //dpm($form, 'form');
    //dpm($form_state, 'form_state');
    $node = node_load($form['nid']['#value']);
    $webform_component_list = webform_component_list($node, FALSE);

    foreach($element as $key => $value) {

      if (strpos($key, 'container_') === 0) {
        $element[$key] = array(
          'component_or_fixed' => array(
            '#title'         => t('Choose how to set the amount for the line item(s)'),
            '#type'          => 'radios',
            '#default_value' => 'fixed', //$settings['amount']['component_or_fixed'],
            '#description'   => t('You can select the webform component from which to read the amount or specify a fixed value here.'),
            '#options'       => array(
              'fixed'  => t('Set fixed amount'),
              'component' => t('Select the component of this webform from which to read the amount'),
            ),
          ),
        ) + $element[$key];

        $element[$key]['amount'] = array(
          'fixed' => array(
            '#type' => 'texfield',
            '#default_value' => NULL, //$settings['amount']['fixed'],
            //'#weight' => 6,
          ),
          'component' => array(
            '#type' => 'select',
            '#default_value' => NULL, //$settings['amount']['component'],
            '#options' => empty($webform_component_list) ? array('' => t('No available components')) : $webform_component_list,
            '#disabled' => empty($webform_component_list) ? TRUE : FALSE,
            //'#weight' => 6,
          ),
        );
      }
    }

    return $element;
  }

  public function collectContextData() {
  }

  public function getLineItems() {
  }

  public function getSuccessUrl() {
    return $this->webform->getRedirectUrl($this->submission->unwrap());
  }

  public function getErrorUrl() {
    return NULL;
  }
}
