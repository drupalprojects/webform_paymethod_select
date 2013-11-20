<?php

namespace Drupal\webform_paymethod_select;

class WebformPaymentContext implements PaymentContextInterface {
  protected $webform;
  protected $submission;

  public function __construct(\Drupal\little_helpers\Webform $webform, \Drupal\little_helpers\WebformSubmission $submission = NULL) {
    $this->webform = $webform;
    $this->submission = $submission;
  }

  public function setSubmission(\Drupal\little_helpers\WebformSubmission $submission) {
    $this->submission = $submission;
  }

  public function getSubmission() {
    return $this->submission;
  }

  public static function fromNode($node) {
    return new static(\Drupal\little_helpers\Webform::fromNode($node));
  }

  public function getSuccessUrl() {
    return $this->webform->getRedirectUrl($this->submission->unwrap());
  }

  public function getErrorUrl() {
    return NULL;
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

  /* *********** Interface: PaymentContextInterface ************** */

  public function getEditForm(array &$component) {
    $form['extra']['payment_line_items'] = array(
      '#title' => t('Line Item(s)'),
      '#type'  => 'fieldset',
    );

    $form['extra']['payment_line_items']['fixed_or_hook'] = array(
      '#title' => t('Choose how to handle line items and currency code'),
      '#type'  => 'radios',
      '#default_value' => isset($component['extra']['payment_line_items']['fixed_or_hook']) ? $component['extra']['payment_line_items']['fixed_or_hook'] : 'fixed',
      '#description' => t('You can set 1 or more fixed line items and currency code here or don\'t set any and set both via hook'),
    );

    $form['extra']['payment_line_items']['fixed_or_hook']['#options'] = array(
      'hook'  => t('Use hook to set line items'),
      'fixed' => t('Fixed set of line items'),
    );

    $form['extra']['payment_line_items']['line_items'] = array(
      '#title'         => t('Line items'),
      '#type'          => 'payment_line_item',
      '#cardinality'   => 0,
      '#default_value' => $component['extra']['payment_line_items'],
      '#required'      => TRUE,
    );

    include_once drupal_get_path('module', 'webform_paymethod_select') . '/currency_codes.inc.php';

    $form['extra']['payment_line_items']['currency_code'] = array(
      '#type'          => 'select',
      '#title'         => t('Select a currency code'),
      '#multiple'      => FALSE,
      '#descriptions'  => t('Select the currency code for this payment.'),
      '#options'       => $currency_codes,
      '#default_value' => 'EUR',
    );

    $form['extra']['amount'] = array(
      '#title' => t('Payment Amount'),
      '#type'  => 'fieldset',
    );

    $form['extra']['amount']['component_or_hook'] = array(
      '#title' => t('Choose how to set the amount for the line item(s)'),
      '#type'  => 'radios',
      '#default_value' => isset($component['extra']['amount']['component_or_hook']) ? $component['extra']['amount']['component_or_hook'] : 'component',
      '#description' => t('You can select the webform component from which to read the amount here or don\'t use the webform and set the amount via hook'),
    );

    $form['extra']['amount']['component_or_hook']['#options'] = array(
      'hook'  => t('Use hook to set the amount'),
      'component' => t('Select the component of this webform from which to read the amount'),
    );

    $options = webform_component_list($node, $field == 'from_address' || $field == 'email' ? 'email_address' : 'email_name', FALSE);

    $form['extra']['amount']['component'] = array(
      '#type' => 'select',
      '#default_value' =>  isset($component['extra']['amount']['component']) ? $component['extra']['amount']['component'] : NULL,
      '#options' => empty($options) ? array('' => t('No available components')) : $options,
      '#disabled' => empty($options) ? TRUE : FALSE,
      //'#weight' => 6,
    );

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
