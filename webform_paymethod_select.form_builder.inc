<?php
/**
 * @file
 */

/**
 * Implements hook_form_builder_types().
 */
function webform_paymethod_select_form_builder_types() {
  $map = _form_builder_webform_property_map('paymethod_select');
  unset($map['properties']['default_value']);
  $fields['paymethod_select'] = array(
    'title'         => t('Payment method'),
    'palette_group' => 'donation',
    'properties'    => array_keys($map['properties']),
    'default'       => array('#form_builder' => array('element_type' => 'paymethod_select')),
    'unique'        => TRUE,
  );

  $fields['paymethod_select']['default'] += _form_builder_webform_default('paymethod_select');

  return array('webform' => $fields);

}

/**
 * Implements _webform_form_builder_map_<webform-component>().
 */
function _webform_form_builder_map_paymethod_select() {
  return array(
    'form_builder_type' => 'paymethod_select',
    'properties' => array(
      'payment_description' => array(
        'storage_parents' => array('extra', 'payment_description'),
      ),
      'selected_payment_methods' => array(
        'storage_parents' => array('extra', 'selected_payment_methods'),
      ),
      'currency_code' => array(
        'storage_parents' => array('extra', 'currency_code'),
      ),
      /*'line_items' => array(
        'storage_parents' => array('extra', 'line_items'),
        ),*/
    ),
  );
}

/**
 * Callback referenced in _webform_form_builder_properties_<webform-component>
 */
function webform_paymethod_select_form_builder_property_paymethod_options_form(&$form_state, $form_type, $element, $property) {

  $form[$property] = array(
    '#form_builder' => array('property_group' => 'options'),
    '#tree'         => TRUE,
  );

  if (isset($element['#' . $property])) {
    $element['#webform_component']['extra'][$property] = $element['#' . $property];
  }
  $component_edit_form = _webform_edit_paymethod_select($element['#webform_component']);
  $form[$property] += $component_edit_form['extra'][$property];

  //dpm($property, 'property');
  //dpm($element, 'element');
  //dpm($form, 'form');
  return $form;
}

/*
 * Callback referenced in _webform_form_builder_properties_<webform-component>
 */
function webform_paymethod_select_form_builder_property_paymethod_options_submit(&$form, &$form_state) {
  dpm($form_state, 'form state');
}

/**
 * Implements _webform_form_builder_properties_<webform-component>().
 */
function _webform_form_builder_properties_paymethod_select() {
  return array(
    'payment_description' => array(
      'form'   => 'webform_paymethod_select_form_builder_property_paymethod_options_form',
      //'submit' => array('webform_paymethod_select_form_builder_property_paymethod_options_submit'),
    ),
    'selected_payment_methods' => array(
      'form'   => 'webform_paymethod_select_form_builder_property_paymethod_options_form',
      //'submit' => array('webform_paymethod_select_form_builder_property_paymethod_options_submit'),
    ),
    'currency_code' => array(
      'form'   => 'webform_paymethod_select_form_builder_property_paymethod_options_form',
      //'submit' => array('webform_paymethod_select_form_builder_property_paymethod_options_submit'),
    ),
    /*'line_items' => array(
      'form'   => 'webform_paymethod_select_form_builder_property_paymethod_options_form',
      //'submit' => array('webform_paymethod_select_form_builder_property_paymethod_options_submit'),
      ),*/
  );
}