<?php

namespace Drupal\webform_paymethod_select;

use \Drupal\little_helpers\Webform\FormState;
use \Drupal\little_helpers\Webform\Submission;

class Component {
  protected $component;
  protected $payment = NULL;
  public function __construct(array $component) {
    $this->component = $component;
    $this->payment = self::createPayment($component);
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
  protected static function createPayment($component) {
    $config = $component['extra'] + array(
      'line_items' => array(),
      'payment_description' => t('Default Payment'),
      'currency_code' => 'EUR',
    );

    $payment = new \Payment(
      array(
        'currency_code'   => $config['currency_code'],
        'description'     => $config['payment_description'],
        'finish_callback' => 'webform_paymethod_select_payment_finish',
      )
    );

    foreach ($config['line_items'] as $line_item) {
      $payment->setLineItem($line_item);
    }
    return $payment;
  }

  /**
   * Get the list of available and selected payment methods.
   *
   * @param \Drupal\payment_forms\PaymentContextInterface $context
   *   The payment context used for the alter hook.
   *
   * @return array
   *   List of \PaymentMethod objects keyed by their pmids.
   */
  protected function getMethods($context) {
    $pmids = array_keys(array_filter($this->component['extra']['selected_payment_methods']));
    $methods = entity_load('payment_method', $pmids);
    // @TODO Use $payment->context instead?
    $this->payment->context_data['context'] = $context;
    if (!empty($methods)) {
      $methods = $this->payment->availablePaymentMethods($methods);
    }
    unset($this->payment->context_data['context']);
    // @TODO implement  a more straight-forward interface for the alter hook
    //       ie. use only $methods and $context as arguments.
    $methods_copy = $methods;
    drupal_alter('webform_paymethod_select_method_list', $context, $methods_copy, $methods);
    return $methods;
  }

  /**
   * Generate the fieldset for one specific payment method.
   *
   * @return array
   *   Form-API fieldset.
   */
  protected function methodForm($method, &$form_state, $context) {
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

    $form_elements_callback = $method->controller->payment_configuration_form_elements_callback;
    // @TODO Explicitly pass the payment to the form callback.
    $form_state['payment'] = $payment;
    if (function_exists($form_elements_callback) == TRUE) {
      $element += $form_elements_callback($element, $form_state, $context);
    }
    return $element;
  }

  /** 
   * Render the webform component.
   */
  public function render(&$element, &$form, &$form_state) {
    $context = new WebformPaymentContext(new FormState($form['#node'], $form, $form_state), $form_state);

    $pmid_options = array();
    $methods = $this->getMethods($context);
    foreach($methods as $pmid => $payment_method) {
      $pmid_options[$pmid] = check_plain(t($payment_method->title_generic));
    }

    unset($element['#theme']);
    $element += array(
      '#type' => 'container',
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
      if (!$this->payment->pid && isset($form['actions']['submit'])) {
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
      reset($pmid_options);
      $pmid_default = isset($this->payment->method) ? $this->payment->method->pmid : key($pmid_options);

      foreach ($pmid_options as $pmid => $method_name) {
        $element['payment_methods_all_forms'][$pmid] = $this->methodForm($methods[$pmid], $form_state, $context);
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
      // @TODO: pass the payment object directly.
      $form_state['payment'] = $payment;
      $method_element = &$element['payment_methods_all_forms'][$pmid];
      $method_validate_callback($method_element, $form_state);
    }
  }

  public function submit(&$form, &$form_state) {
    $payment = $this->payment;
    $node = $form['#node'];

    $submission = Submission::load($node->nid, $form_state['values']['details']['sid']);
    $context = new WebformPaymentContext($submission, $form_state);
    $payment->context_data['context'] = $context;

    // handle setting the amount value in line items that were configured to
    // not have a fixed amount
    foreach ($payment->line_items as $line_item) {
      if ($line_item->amount_source === 'component') {
        $amount = $submission->valueByCid($line_item->amount_component);
        $amount = str_replace(',', '.', $amount);
        $line_item->amount = (float) $amount;
      }
    }
    $values = $form_state['values']['submitted'][$this->component['cid']];
    $payment->method = entity_load_single('payment_method', $values['payment_method_selector']);
    entity_save('payment', $payment);

    // Execute the payment.
    if ($payment->getStatus()->status == PAYMENT_STATUS_NEW) {
      $payment->execute();
    }

    // Set the component value to the $payment->pid - we don't save any payment data.
    $webform = $submission->webform;
    $cids = array_keys($webform->componentsByType('paymethod_select'));
    db_query(
      "UPDATE {webform_submitted_data} SET data=:pid WHERE nid=:nid AND cid=:cid AND sid=:sid",
      array(':nid' => $node->nid, ':cid' => $this->component['cid'], ':sid' => $submission->sid, ':pid' => $payment->pid)
    );
  }
}
