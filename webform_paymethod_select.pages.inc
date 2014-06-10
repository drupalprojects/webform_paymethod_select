<?php

/**
 * Page callback that shows a simple error page.
 */
function webform_paymethod_select_error_page(\Payment $payment) {
  $status = $payment->getStatus()->status;
  $context = $payment->context_data['context'];
  $link = $context->reenterLink($payment);
  if (payment_status_is_or_has_ancestor($status, PAYMENT_STATUS_PENDING)) {
    $message = t('The payment is still pending although it should be processed by now. Something went wrong. Please !tryagainlink or contact the site administrator if the problem persists.', array(
      '!tryagainlink' => l('try again', $link['path'], $link),
    ));
  }
  else {
    $message = t('An error occured while processing your payment. Please !tryagainlink or contact the site administrator if the problem persists.', array(
      '!tryagainlink' => l('try again', $link['path'], $link),
    ));
  }
  $output = array(
    '#markup' => $message,
  );
  return $output;
}
