<?php

namespace Drupal\webform_paymethod_select;

use \Drupal\little_helpers\Webform\FormState;
use \Drupal\little_helpers\Webform\Submission;
use \Drupal\little_helpers\Webform\Webform;

class Component {
  protected $component;
  protected $payment = NULL;
  public function __construct(array $component) {
    $this->component = $component;
  }

  /**
   * Create a payment object based on the component configuration.
   *
   * @param array $component
   *   Weform component array.
   *
   * @return \Payment
   *   Newly created payment object.
   */
  protected function createPayment($context) {
    $config = $this->component['extra'] + array(
      'line_items' => array(),
      'payment_description' => t('Default Payment'),
      'currency_code' => 'EUR',
    );

    $this->payment = entity_create('payment', array(
      'currency_code'   => $config['currency_code'],
      'description'     => $config['payment_description'],
      'finish_callback' => 'webform_paymethod_select_payment_finish',
    ));
    $this->payment->contextObj = $context;
    $this->refreshPaymentFromContext();
    return $this->payment;
  }

  /**
   * Load a payment object from the database and reset it's line items.
   *
   * @param int $pid
   *   Payment ID.
   */
  protected function reloadPayment($pid, $context) {
    $this->payment = entity_load_single('payment', $pid);
    $this->payment->contextObj = $context;
    $this->refreshPaymentFromContext();
    return $this->payment;
  }

  /**
   * Get a list of parent form keys for this component.
   *
   * @return array
   *   List of parent form keys - just like $element['#parents'].
   */
  public function parents($webform) {
    $parents = array($this->component['form_key']);
    $parent = $this->component;
    while ($parent['pid'] != 0) {
      $parent = $webform->component($parent['pid']);
      array_unshift($parents, $parent['form_key']);
    }
    return $parents;
  }

  /**
   * Get the list of selected payment methods.
   *
   * @return array
   *   List of \PaymentMethod objects keyed by their pmids.
   */
  public function selectedMethods() {
    $pmids = array_keys(array_filter($this->component['extra']['selected_payment_methods']));
    return entity_load('payment_method', $pmids);
  }

  /**
   * Get the list of available and selected payment methods.
   *
   * @param \Drupal\payment_context\PaymentContextInterface $context
   *   The payment context used for the alter hook.
   *
   * @return array
   *   List of \PaymentMethod objects keyed by their pmids.
   */
  protected function getMethods() {
    $methods = $this->selectedMethods();
    if (!empty($methods)) {
      foreach ($methods as $pmid => $method) {
        try {
          $method->validate($this->payment, TRUE);
        }
        catch (\PaymentValidationException $e) {
          unset($methods[$pmid]);
        }
      }
    }
    drupal_alter('webform_paymethod_select_method_list', $methods, $this->payment);
    return $methods;
  }

  /**
   * Generate the fieldset for one specific payment method.
   *
   * @return array
   *   Form-API fieldset.
   */
  protected function methodForm($method, &$form_state) {
    $payment = clone $this->payment;
    $payment->method = $method;

    $element = array(
      '#type'        => 'fieldset',
      '#title'       => t($method->title_generic),
      '#attributes'  => array('class' => array('payment-method-form'), 'data-pmid' => $method->pmid),
      '#collapsible' => FALSE,
      '#collapsed'   => FALSE,
      '#states' => array(
        'visible' => array(
          '#payment-method-selector input' => array('value' => (string) $method->pmid),
        ),
      ),
    );
    $js = drupal_get_path('module', 'webform_paymethod_select') . '/webform_paymethod_select.js';
    $element['#attached']['js'][] = $js;

    $form_elements_callback = $method->controller->payment_configuration_form_elements_callback;
    if (function_exists($form_elements_callback) == TRUE) {
      $form_state['payment'] = $payment;
      $element += $form_elements_callback($element, $form_state);
      unset($form_state['payment']);
    }
    return $element;
  }

  /** 
   * Render the webform component.
   *
   * The element array includes the data put there by
   * @see _webform_render_paymethod_select(). Especially:
   * - #value: The previous value for this submission. The format of the data
   *   provided depends on where it came from:
   *   - From a previous submission: [0 => $payment->pid].
   *   - From navigating the webform steps: $form_state['values'][…].
   */
  public function render(&$element, &$form, &$form_state) {
    unset($element['#theme']);

    $s = Webform::fromNode($form['#node'])->formStateToSubmission($form_state);
    $context = new WebformPaymentContext($s, $form_state, $this->component);

    if (isset($element['#value'][0]) && is_numeric($element['#value'][0])) {
      $payment = $this->reloadPayment($element['#value'][0], $context);

      if ($this->statusIsOneOf(PAYMENT_STATUS_SUCCESS)) {
        $element['#theme'] = 'webform_paymethod_select_already_paid';
        $element['#payment'] = $payment;
        unset($payment->contextObj);
        return;
      }
      elseif (!$this->statusIsOneOf(PAYMENT_STATUS_NEW)) {
        $status = payment_status_info($payment->getStatus()->status)->title;
        $element['error'] = array(
          '#markup' => t('The previous payment attempt seems to have failed. The current payment status is "!status". Please try again!', array('!status' => $status))
        );
      }
    }
    else {
      $payment = $this->createPayment($context);
    }

    $pmid_options = array();
    $methods = $this->getMethods();
    foreach($methods as $pmid => $payment_method) {
      $pmid_options[$pmid] = check_plain(t($payment_method->title_generic));
    }
    if ($payment->method) {
      $pmid_default = $payment->method->pmid;
    }
    elseif (!empty($element['#value']['payment_method_selector'])) {
      $pmid_default = $element['#value']['payment_method_selector'];
    }
    else {
      $pmid_default = key($pmid_options);
    }

    $element += array(
      '#type' => 'container',
      '#theme' => 'webform_paymethod_select_component',
      '#tree' => TRUE,
      '#theme_wrappers' => array('container'),
      '#id' => drupal_html_id('paymethod-select-wrapper'),
      '#element_validate' => array('webform_paymethod_select_component_element_validate'),
      '#cid' => $this->component['cid'],
    );
    $element['#attributes']['class'][] = 'paymethod-select-wrapper';
    $element['payment_method_all_forms'] = array(
      '#type'        => 'container',
      '#id'          => 'payment-method-all-forms',
      '#weight'      => 2,
      '#attributes'  => array('class' => array('payment-method-all-forms')),
    );

    if (!count($pmid_options)) {
      if (!$payment->pid && isset($form['actions']['submit'])) {
        // when no payment method is selected (or available) disable submit
        // button
        $form['actions']['submit']['#disabled'] = TRUE;
      }
      $element['pmid_title'] = array(
        '#type'   => 'item',
        '#title'  => isset($element['#title']) ? $element['#title'] : NULL,
        '#markup' => t('There are no payment methods, check the options of this webform element to enable methods.'),
      );
    }
    else {
      foreach ($pmid_options as $pmid => $method_name) {
        $element['payment_method_all_forms'][$pmid] = $this->methodForm($methods[$pmid], $form_state);
      }

      $element['payment_method_selector'] = array(
        '#type'          => 'radios',
        '#id'            => 'payment-method-selector',
        '#weight'        => 1,
        '#title'         => isset($element['#title']) ? $element['#title'] : NULL,
        '#options'       => $pmid_options,
        '#default_value' => $pmid_default,
        '#required'      => $element['#required'],
        '#attributes'    => array('class' => array('paymethod-select-radios')),
        '#access'        => count($pmid_options) > 1,
      );
    }
    unset($payment->contextObj);
    $this->payment = $payment;
  }

  public function validate(array $element, array &$form_state) {
    $payment = $this->payment;
    $values  = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    $pmid    = (int) $values['payment_method_selector'];

    $payment->method = $method = entity_load_single('payment_method', $pmid);
    if ($payment->method->name === 'payment_method_unavailable') {
      form_error($element, t('Invalid Payment Method selected.'));
    }

    $method_validate_callback = $method->controller->payment_configuration_form_elements_callback . '_validate';
    if (function_exists($method_validate_callback)) {
      $method_element = &$element['payment_method_all_forms'][$pmid];
      $form_state['payment'] = $payment;
      $method_validate_callback($method_element, $form_state);
      unset($form_state['payment']);
    }
  }

  protected function refreshPaymentFromContext() {
    $payment = $this->payment;
    $submission = $payment->contextObj->getSubmission();

    $extra = $this->component['extra'];
    if ($extra['currency_code_source'] == 'component') {
      $payment->currency_code = $submission->valueByCid($extra['currency_code_component']);
    }

    // Set the payment up for a (possibly repeated) payment attempt.
    // Handle setting the amount value in line items that were configured to
    // read their amount from a component.
    foreach ($extra['line_items'] as $i => $line_item) {
      if ($line_item->amount_source === 'component') {
        $amount = $submission->valueByCid($line_item->amount_component);
        $amount = str_replace(',', '.', $amount);
        $line_item->amount = (float) $amount;
      }
      if ($line_item->quantity_source === 'component') {
        $quantity= $submission->valueByCid($line_item->quantity_component);
        $line_item->quantity = (int) $quantity;
      }
      $payment->setLineItem($line_item);
    }
  }

  public function submit(&$form, &$form_state, $submission) {
    $payment = $this->payment;
    if ($this->statusIsOneOf(PAYMENT_STATUS_SUCCESS)){
      return;
    }
    $context = new WebformPaymentContext($submission, $form_state, $this->component);
    $payment->contextObj = $context;
    $this->refreshPaymentFromContext();


    if ($payment->getStatus()->status != PAYMENT_STATUS_NEW) {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_NEW));
    }
    entity_save('payment', $payment);

    // Set the component value to the $payment->pid - we don't save any payment data.
    $node = $submission->webform->node;
    db_query(
      "UPDATE {webform_submitted_data} SET data=:pid WHERE nid=:nid AND cid=:cid AND sid=:sid",
      array(':nid' => $node->nid, ':cid' => $this->component['cid'], ':sid' => $submission->unwrap()->sid, ':pid' => $payment->pid)
    );
    $form_state['values']['submitted'][$this->component['cid']] = array($payment->pid);

    // Execute the payment.
    $payment->execute();
  }

  public function statusIsOneOf() {
    $statuses = func_get_args();
    $status = $this->payment->getStatus()->status;
    foreach ($statuses as $s) {
      if (payment_status_is_or_has_ancestor($status, $s)) {
        return TRUE;
      }
    }
    return FALSE;
  }
}
