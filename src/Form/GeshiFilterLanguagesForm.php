<?php

/**
 * @file
 * Contains \Drupal\geshifilter\Form\GeshiFilterLanguagesForm.
 */

namespace Drupal\geshifilter\Form;

use Drupal\Core\Form\ConfigFormBase;

use Drupal\Core\Form\FormStateInterface;

use \Drupal\geshifilter\GeshiFilterCss;

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
  public function buildForm(array $form, FormStateInterface $form_state, $view = NULL) {
    $config = \Drupal::config('geshifilter.settings');
    // Check if GeSHi library is available.
    $geshi_library = libraries_load('geshi');
    if (!$geshi_library['loaded']) {
      drupal_set_message($geshi_library['error message'], 'error');
      return;
    }
    $add_checkbox = TRUE;
    $add_tag_option = (!$config->get('format_specific_options'));
    $form['language_settings'] = geshifilter_per_language_settings($view, $add_checkbox, $add_tag_option);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::config('geshifilter.settings');

    // Language tags should differ from each other.
    $languages = _geshifilter_get_available_languages();

    $values = $form_state->getValue('language');
    foreach ($languages as $language1 => $language_data1) {

      if ($values[$language1]['enabled'] == FALSE) {
        continue;
      }

      $tags1 = _geshifilter_tag_split($values[$language1]['tags']);

      // Check that other languages do not use these tags.
      foreach ($languages as $language2 => $language_data2) {
        // Check these tags against the tags of other enabled languages.
        if ($language1 == $language2) {
          continue;
        }
        // Get tags for $language2.
        $tags2 = _geshifilter_tag_split($values[$language2]['tags']);

        // Get generic tags.
        $generics = _geshifilter_tag_split($config->get('tags'));
        $tags2 = array_merge($tags2, $generics);

        // And now we can check tags1 against tags2.
        foreach ($tags1 as $tag1) {
          foreach ($tags2 as $tag2) {
            if ($tag1 == $tag2) {
              $name = "language[{$language2}][tags]";
              $form_state->setErrorByName($name, t('The language tags should differ between languages and from the generic tags.'));
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::config('geshifilter.settings');
    $languages = $form_state->getValue('language');
    foreach ($languages as $key => $value) {
      if ($value["enabled"] == FALSE) {
        // Remove all disabled languages from config.
        $config->clear("language.{$key}.enabled");
      }
      else {
        // Set only the enabled languages.
        $config->set("language.{$key}.enabled", TRUE);
      }
      if ($value["tags"] == '') {
        // Remove all languages without tags from config.
        $config->clear("language.{$key}.tags");
      }
      else {
        // Set only languages with tags.
        $config->set("language.{$key}.tags", $value["tags"]);
      }
    }
    $config->save();
    // Regenerate language_css.
    if ($config->get('css_mode', GESHIFILTER_CSS_INLINE) == GESHIFILTER_CSS_CLASSES_AUTOMATIC) {
      GeshiFilterCss::generateLanguagesCssFile();
    }
    \Drupal\Core\Cache\Cache::invalidateTags(array('geshifilter'));
  }
}
