<?php

namespace Drupal\webform_paymethod_select;

use Drupal\little_helpers\Webform\Webform;

/**
 * The payment form component.
 *
 * - Renders the payment forms.
 * - Prepares the payment object based on the form submission.
 * - Initiates the payment process.
 */
class Component {

  protected $component;
  protected $payment = NULL;

  /**
   * Construct a new instance from a webform component array.
   *
   * @param array $component
   *   The webform component.
   */
  public function __construct(array $component) {
    $defaults = _webform_defaults_paymethod_select();
    $this->component = $component + $defaults;
    $this->component['extra'] += $defaults['extra'];
  }

  /**
   * Create a payment object based on the component configuration.
   *
   * @param \Drupal\webform_paymethod_select\WebformPaymentContext $context
   *   The current payment context.
   *
   * @return \Payment
   *   Newly created payment object.
   */
  protected function createPayment(WebformPaymentContext $context) {
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
   * @param \Drupal\webform_paymethod_select\WebformPaymentContext $context
   *   The current payment context.
   */
  protected function reloadPayment($pid, WebformPaymentContext $context) {
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
      '#title'       => $method->title_generic,
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
   * The element array includes the data put there
   * by @see _webform_render_paymethod_select().
   * Especially:
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
          '#markup' => t('The previous payment attempt seems to have failed. The current payment status is "!status". Please try again!', ['!status' => $status]),
        );
      }
    }
    else {
      $payment = $this->createPayment($context);
    }

    $pmid_options = array();
    $methods = $this->getMethods();
    foreach ($methods as $pmid => $payment_method) {
      $pmid_options[$pmid] = check_plain($payment_method->title_generic);
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

    $selector = [
      '#title' => $element['#title'],
      '#title_display' => $element['#title_display'],
      '#required' => $element['#required'],
    ];
    $element = array(
      '#theme' => 'webform_paymethod_select_component',
      // This is displayed as the radios title.
      '#title' => NULL,
      '#title_display' => 'none',
      '#tree' => TRUE,
      '#element_validate' => array('webform_paymethod_select_component_element_validate'),
      '#cid' => $this->component['cid'],
    ) + $element;
    $element['#wrapper_attributes']['class'][] = 'paymethod-select-wrapper';
    $element['payment_method_all_forms'] = array(
      '#type'        => 'container',
      '#id'          => 'payment-method-all-forms',
      '#weight'      => 2,
      '#attributes'  => [
        'class' => [
          'payment-method-all-forms',
          'webform-prefill-exclude',
        ],
      ],
    );

    if (!count($pmid_options)) {
      if (!$payment->pid && isset($form['actions']['submit'])) {
        // When no payment method is selected (or available) disable submit
        // button.
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

      $element['payment_method_selector'] = $selector + array(
        '#type'          => 'radios',
        '#id'            => 'payment-method-selector',
        '#weight'        => 1,
        '#options'       => $pmid_options,
        '#default_value' => $pmid_default,
        '#attributes'    => array('class' => array('paymethod-select-radios')),
        '#access'        => count($pmid_options) > 1,
      );
    }
    unset($payment->contextObj);
    $this->payment = $payment;
  }

  /**
   * Validate the form input for the paymethod select element.
   *
   * Give the selected payment method a chance to do its own validation.
   */
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

  /**
   * Re-read configuration from component and context and update the payment.
   */
  protected function refreshPaymentFromContext() {
    $payment = $this->payment;
    $submission = $payment->contextObj->getSubmission();

    $extra = $this->component['extra'];
    if ($extra['currency_code_source'] === 'component') {
      $payment->currency_code = $submission->valueByCid($extra['currency_code_component']);
    }

    // Set the payment up for a (possibly repeated) payment attempt.
    // Handle setting the amount value in line items that were configured to
    // read their amount from a component.
    foreach ($extra['line_items'] as $line_item) {
      if (isset($line_item->amount_source) && $line_item->amount_source === 'component') {
        $amount = $submission->valueByCid($line_item->amount_component);
        $amount = str_replace(',', '.', $amount);
        $line_item->amount = (float) $amount;
      }
      if (isset($line_item->quantity_source) && $line_item->quantity_source === 'component') {
        $quantity = $submission->valueByCid($line_item->quantity_component);
        $line_item->quantity = (int) $quantity;
      }
      $payment->setLineItem($line_item);
    }
  }

  /**
   * Form submit callback: Initiate the payment.
   */
  public function submit(&$form, &$form_state, $submission) {
    $payment = $this->payment;
    if ($this->statusIsOneOf(PAYMENT_STATUS_SUCCESS)) {
      return;
    }
    $context = new WebformPaymentContext($submission, $form_state, $this->component);
    $payment->contextObj = $context;
    $this->refreshPaymentFromContext();

    if ($payment->getStatus()->status != PAYMENT_STATUS_NEW) {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_NEW));
    }
    entity_save('payment', $payment);

    // Set component value to the pid - we don't save any payment data.
    $node = $submission->webform->node;
    db_query(
      "UPDATE {webform_submitted_data} SET data=:pid WHERE nid=:nid AND cid=:cid AND sid=:sid",
      [
        ':nid' => $node->nid,
        ':cid' => $this->component['cid'],
        ':sid' => $submission->unwrap()->sid,
        ':pid' => $payment->pid,
      ]
    );
    $form_state['values']['submitted'][$this->component['cid']] = array($payment->pid);

    // Execute the payment.
    $payment->execute();
  }

  /**
   * Helper function to check the current payment’s status.
   *
   * This function accepts a variable number of status strings.
   *
   * @return bool
   *   TRUE if the payment status is an ancestor of one of the passed statuses.
   */
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
