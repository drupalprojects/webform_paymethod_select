<?php

/**
 * Page callback that shows a simple error page.
 */
function webform_paymethod_select_error_page(\Payment $payment) {
  $context = $payment->context_data['context'];
  $link = $context->reenterLink($payment);
  $message = t('An error occured while processing your payment. Please !tryagainlink or contact the site administrator if the problem persists.', array(
    '!tryagainlink' => l('try again', $link['path'], $link),
  ));
  $output = array(
    '#markup' => $message,
  );
  return $output;
}
