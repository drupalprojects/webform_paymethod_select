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
 * Implements _webform_form_builder_map_<form builder type>().
 */
function _webform_form_builder_map_paymethod_select() {
  return array(
    'form_builder_type' => 'paymethod_select',
    'properties' => array(
      'paymethod_options' => array(
        'storage_parents' => array('value'),
      ),
    ),
  );
}

/**
 * Callback referenced in _webform_form_builder_properties_<webform-component>
 */
function webform_paymethod_select_form_builder_property_paymethod_options_form(&$form_state, $form_type, $element, $property) {
  $form['paymethod_options'] = array(
    '#form_builder' => array('property_group' => 'options'),
    '#tree'         => TRUE,
  );
  // we need to redo the mapping here because storage_parents arent resolved until
  // the form is saved for good.
  if (isset($element['#paymethod_options'])) {
    $element['#webform_component']['value'] = $element['#paymethod_options'];
  }
  $form['paymethod_options'] += _webform_edit_paymethod_select($element['#webform_component']);

  return $form;
}

/*
 * Callback referenced in _webform_form_builder_properties_<webform-component>
 */
function webform_paymethod_select_form_builder_property_paymethod_options_submit(&$form, &$form_state) {
  $form_state['values']['paymethod_options'] = $form_state['values']['options']['value'];
}

/**
 * Implements _webform_form_builder_properties_<webform-component>().
 */
function _webform_form_builder_properties_paymethod_select() {
  return array(
    'paymethod_options' => array(
      'form'   => 'webform_paymethod_select_form_builder_property_paymethod_options_form',
      'submit' => array('webform_paymethod_select_form_builder_property_paymethod_select_submit'),
    ),
  );
}
