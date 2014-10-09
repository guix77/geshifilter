<?php

/**
 * @file
 * Contains \Drupal\geshifield\Plugin\Field\FieldWidget\GeshiFieldDefaultWidget.
 */

namespace Drupal\geshifield\Plugin\Field\FieldWidget;

require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.inc';

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * Plugin implementation of the 'geshifield_default' widget.
 *
 * @FieldWidget(
 *   id = "geshifield_default",
 *   label = @Translation("GeshiField default"),
 *   field_types = {
 *     "geshifield"
 *   }
 * )
 */
class GeshiFieldDefaultWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $enabled_languages = \_geshifilter_get_enabled_languages();

    $element['sourcecode'] = array(
      '#title' => t('Code'),
      '#type' => 'textarea',
      '#default_value' => isset($items[$delta]->sourcecode) ? $items[$delta]->sourcecode : NULL,
    );
    $element['language'] = array(
      '#title' => t('Language'),
      '#type' => 'select',
      '#default_value' => isset($items[$delta]->language) ? $items[$delta]->language : NULL,
      '#options' => $enabled_languages,
    );
    return $element;
  }
}