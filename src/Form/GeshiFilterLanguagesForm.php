<?php

/**
 * @file
 * Contains \Drupal\geshifilter\Form\GeshiFilterLanguagesForm.
 */

namespace Drupal\geshifilter\Form;

use Drupal\Core\Form\ConfigFormBase;

// Need this for _geshifilter_general_highlight_tags_settings().
require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.admin.inc';

// Need this for constants.
require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.module';

/**
 * Form used to set enable/disabled for languages.
 */
class GeshiFilterLanguagesForm extends ConfigFormBase {

  /**
   * List of modules to enable.
   */
  public static $modules = array('libraries', 'geshifilter');

  protected $config;
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geshifilter_languages';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, $view = NULL) {
    $config = \Drupal::config('geshifilter.settings');
    // Check if GeSHi library is available.
    $geshi_library = libraries_load('geshi');
    if (!$geshi_library['loaded']) {
      drupal_set_message($geshi_library['error message'], 'error');
      return;
    }
    $add_checkbox = TRUE;
    $add_tag_option = (!$config->get('format_specific_options', FALSE));
    $form['language_settings'] = geshifilter_per_language_settings($view, $add_checkbox, $add_tag_option);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    $config = \Drupal::config('geshifilter.settings');

    // If we're coming from the _geshifilter_filter_settings (sub)form, we
    // should take the text format into account.
    $format = isset($form_state['values']['format']) ? $form_state['values']['format'] : NULL;
    $f = ($format === NULL) ? '' : "_$format";

    // Language tags should differ from each other.
    $languages = _geshifilter_get_available_languages();
    foreach ($languages as $language1 => $language_data1) {
      // Iterate over the enabled languages: skip disabled ones.
      $field = "language_enabled_{$language1}";
      if (!(isset($form_state['values'][$field]) ? $form_state['values'][$field] : $config->get($field, FALSE))) {
        continue;
      }
      // Get the associated tags as $tags1.
      $field = "language_tags_{$language1}{$f}";
      if (isset($form_state['values'][$field])) {
        $tags1 = _geshifilter_tag_split($form_state['values'][$field]);
      }
      else {
        $tags1 = _geshifilter_tag_split(geshifilter_language_tags($language1, $format));
      }
      // Also include the generic tags.
      $field = "tags";
      $generic_tags = isset($form_state['values'][$field]) ? $form_state['values'][$field] : geshifilter_tags($format);
      $tags1 = array_merge($tags1, _geshifilter_tag_split($generic_tags));

      // Check that other languages do not use these tags.
      foreach ($languages as $language2 => $language_data2) {
        // Check these tags against the tags of other enabled languages.
        $field = "language_enabled_{$language2}";
        if ($language1 == $language2 || !(isset($form_state['values'][$field]) ? $form_state['values'][$field] : $config->get($field, FALSE))) {
          continue;
        }
        // Get tags for $language2, or skip when not in $form_state['values'].
        $field = "language_tags_{$language2}";
        if (isset($form_state['values'][$field])) {
          $tags2 = _geshifilter_tag_split($form_state['values'][$field]);
        }
        else {
          continue;
        }
        // And now we can check tags1 against tags2.
        foreach ($tags1 as $tag1) {
          foreach ($tags2 as $tag2) {
            if ($tag1 == $tag2) {
              form_set_error("language_tags_{$language2}", $form_state, t('The language tags should differ between languages and from the generic tags.'));
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $config = \Drupal::config('geshifilter.settings');
    foreach ($form_state['values']['languages'] as $key => $value) {
      if ($value["language_enabled_{$key}"] == FALSE) {
        // Remove all disabled languages from config.
        $config->clear("language_enabled_{$key}");
      }
      else {
        // Set only the enabled languages.
        $config->set("language_enabled_{$key}", TRUE);
      }
      if ($value["language_tags_{$key}"] == '') {
        // Remove all languages without tags from config.
        $config->clear("language_tags_{$key}");
      }
      else {
        // Set only languages with tags.
        $config->set("language_tags_{$key}", $value["language_tags_{$key}"]);
      }
    }
    $config->save();
    // Regenerate language_css.
    if ($config->get('css_mode', GESHIFILTER_CSS_INLINE) == GESHIFILTER_CSS_CLASSES_AUTOMATIC) {
      _geshifilter_generate_languages_css_file();
    }
    _geshifilter_clear_filter_cache();
  }
}
