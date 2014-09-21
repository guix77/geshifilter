<?php
/**
 * @file
 * Implementation of administration functionality.
 */

require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.inc';

// Necessary for String::checkPlain().
use Drupal\Component\Utility\String;

/**
 * Validation handler for the tag styles form element.
 *
 * Covers usage in the general form of geshifilter_admin_general_settings()
 * and the format specific (sub)form of _geshifilter_filter_settings().
 */
function _geshifilter_tag_styles_validate($form, &$form_state) {
  // If we're coming from the _geshifilter_filter_settings (sub)form, we should
  // take the text format into account.
  $f = isset($form_state['values']['format']) ? '_' . $form_state['values']['format'] : '';

  // Check that at least one tag style is selected.
  if (0 == count(array_filter($form_state['values']["tag_styles{$f}"]))) {
    form_set_error("styles{$f}", t('At least one tag style should be selected.'));
  }
}

/**
 * Helper function for some settings form fields usable as general and specific.
 */
function _geshifilter_general_highlight_tags_settings() {
  $form = array();

  // Generic tags.
  $form["tags"] = array(
    '#type' => 'textfield',
    '#title' => t('Generic syntax highlighting tags'),
    '#default_value' => geshifilter_tags(),
    '#description' => t('Tags that should activate the GeSHi syntax highlighting. Specify a space-separated list of tagnames.'),
  );

  // Container tag styles.
  $form["tag_styles"] = array(
    '#type' => 'checkboxes',
    '#title' => t('Container tag style'),
    '#options' => array(
      GESHIFILTER_BRACKETS_ANGLE => '<code>' . String::checkPlain('<foo> ... </foo>') . '</code>',
      GESHIFILTER_BRACKETS_SQUARE => '<code>' . String::checkPlain('[foo] ... [/foo]') . '</code>',
      GESHIFILTER_BRACKETS_DOUBLESQUARE => '<code>' . String::checkPlain('[[foo]] ... [[/foo]]') . '</code>',
      GESHIFILTER_BRACKETS_PHPBLOCK => t('PHP style source code blocks: !php and !percent', array(
        '!php' => '<code>' . String::checkPlain('<?php ... ?>') . '</code>',
        '!percent' => '<code>' . String::checkPlain('<% ... %>') . '</code>',
      )),
    ),
    '#default_value' => _geshifilter_tag_styles(),
    '#description' => t('Select the container tag styles that should trigger GeSHi syntax highlighting.'),
  );
  return $form;
}

/**
 * Function for generating a form table for per language settings.
 */
function geshifilter_per_language_settings($view, $add_checkbox, $add_tag_option) {
  $config = \Drupal::config('geshifilter.settings');
  $form = array();
  $header = array(
    t('Language'),
    t('GeSHi language code'),
  );
  if ($add_tag_option) {
    $header[] = t('Tag/language attribute value');
  }
  $form['language'] = array(
    '#type' => 'table',
    '#header' => $header,
    '#empty' => t('Nome language is available.'),
  );

  // Table body.
  $languages = _geshifilter_get_available_languages();
  foreach ($languages as $language => $language_data) {
    $enabled = $config->get("language.{$language}.enabled", FALSE);
    // Skip items to hide.
    if (($view == 'enabled' && !$enabled) || ($view == 'disabled' && $enabled)) {
      continue;
    }
    // Build language row.
    $form['language'][$language] = array();
    // Add enable/disable checkbox.
    if ($add_checkbox) {
      $form['language'][$language]['enabled'] = array(
        '#type' => 'checkbox',
        '#default_value' => $enabled,
        '#title' => $language_data['fullname'],
      );
    }
    else {
      $form['language'][$language]['fullname'] = array(
        '#type' => 'markup',
        '#markup' => $language_data['fullname'],
      );
    }
    // Language code.
    $form['language'][$language]['name'] = array(
      '#type' => 'markup',
      '#markup' => $language,
    );
    // Add a textfield for tags.
    if ($add_tag_option) {
      $form['language'][$language]['tags'] = array(
        '#type' => 'textfield',
        '#default_value' => $config->get("language.{$language}.tags", ''),
        '#size' => 20,
      );
    }
  }
  return $form;
}

/**
 * Helper function for clearing the appropriate filter cache entries.
 */
function _geshifilter_clear_filter_cache() {
  $message = FALSE;

  global $user;

  // Iterate over text formats and clear the ones GeSHi filter is active in.
  foreach (filter_formats($user) as $format => $input_format) {
    // Get the filters in this text format
    // $filters = filter_list_format($format);
    // look if GeSHi is enabled in this text format
    // if (isset($filters['geshifilter/0'])) {
    // cache_clear_all($format .':', 'cache_filter', TRUE);
    // $message = TRUE;
    // }
    // }
    // Clear the geshifilter entries in the cache_filter table,
    // created by geshifilter_geshi_process().
    // cache_clear_all('geshifilter:', 'cache_filter', TRUE);
    // Show a message that the filter cache was cleared.
    // if ($message) {
    // drupal_set_message(t('GeSHi filter cleared the filter cache for the
    // appropriate text formats.'));
  }
}

/**
 * Helper function for generating the CSS rules.
 *
 * @return string
 *   String with the CSS rules.
 */
function _geshifilter_generate_languages_css_rules() {
  $output = '';
  $geshi_library = libraries_load('geshi');
  if ($geshi_library['loaded']) {
    require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.pages.inc';
    $languages = _geshifilter_get_enabled_languages();
    foreach ($languages as $langcode => $language_full_name) {
      // Create GeSHi object.
      $geshi = _geshifilter_GeSHi_factory('', $langcode);
      _geshifilter_override_geshi_defaults($geshi, $langcode);
      // Add CSS rules for current language.
      $output .= $geshi->get_stylesheet(FALSE) . "\n";
      // Release GeSHi object.
      unset($geshi);
    }
  }
  else {
    drupal_set_message(t('Error while generating CSS rules: could not load GeSHi library.'), 'error');
  }
  return $output;
}

/**
 * Function for generating the external stylesheet.
 *
 * @param bool $force
 *   Force the regeneration of the CSS file.
 */
function _geshifilter_generate_languages_css_file($force = FALSE) {
  $config = \Drupal::config('geshifilter.settings');
  $languages = _geshifilter_get_enabled_languages();
  // Serialize the array of enabled languages as sort of hash.
  $languages_hash = serialize($languages);

  // Check if generation of the CSS file is needed.
  if ($force || $languages_hash != $config->get('cssfile_languages', '')) {
    // Build stylesheet.
    $stylesheet = _geshifilter_generate_languages_css_rules();
    // Save stylesheet.
    $stylesheet_filename = _geshifilter_language_css_path();
    $ret = file_save_data($stylesheet, $stylesheet_filename, FILE_EXISTS_REPLACE);
    if ($ret) {
      drupal_set_message(t('(Re)generated external CSS style sheet %file.', array('%file' => $ret)));
    }
    else {
      drupal_set_message(t('Could not generate external CSS file. Check the settings of your <a href="!filesystem">file system</a>.', array('!filesystem' => url('admin/config/media/file-system'))), 'error');
    }
    // Remember for which list of languages the CSS file was generated.
    $config->set('cssfile_languages', $languages_hash);
    $config->save();
  }
}
