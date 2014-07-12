<?php

/**
 * @file
 * General GeSHi filter helper functions.
 */

/**
 * List of available languages.
 *
 * @return array
 *   An array mapping language code to array with the language path and
 *   full language name.
 */
function _geshifilter_get_available_languages() {
  $config = \Drupal::config('geshifilter.settings');
  // Try to get it from cache (database actually).
  $available_languages = $config->get('available_languages_cache', NULL);
  if ($available_languages === NULL) {
    // Not in cache: build the array of available_languages.
    $geshi_library = libraries_load('geshi');
    $available_languages = array();
    if ($geshi_library['loaded']) {
      $dirs = array(
        $geshi_library['library path'] . '/geshi',
        drupal_get_path('module', 'geshifilter') . '/geshi-extra',
      );
      foreach ($dirs as $dir) {
        foreach (file_scan_directory($dir, '/.[pP][hH][pP]$/i') as $filename => $fileinfo) {
          // Short name.
          $name = $fileinfo->name;
          // Get full name.
          $geshi = new GeSHi('', $name);
          $geshi->set_language_path($dir);
          $fullname = $geshi->get_language_name();
          unset($geshi);
          // Store.
          $available_languages[$name] = array('language_path' => $dir, 'fullname' => $fullname);
        }
      }
      ksort($available_languages);
      // Save array to database.
      $config->set('available_languages_cache', $available_languages);
      $config->save();
    }
  }
  return $available_languages;
}

/**
 * List of enabled languages(with caching).
 *
 * @return array
 *   Array with enabled languages mapping language code to full name.
 */
function _geshifilter_get_enabled_languages() {
  $config = \Drupal::config('geshifilter.settings');
  static $enabled_languages = NULL;
  if ($enabled_languages === NULL) {
    $enabled_languages = array();
    $languages = _geshifilter_get_available_languages();
    foreach ($languages as $language => $language_data) {
      if ($config->get('language_enabled_' . $language, FALSE)) {
        $enabled_languages[$language] = $language_data['fullname'];
      }
    }
  }
  return $enabled_languages;
}

/**
 * Helper function for gettings the tags.
 *
 * @param object $format
 *   Format is a configuration entity from type "filter_format" with the
 *   format of current filter being processed.
 */
function _geshifilter_get_tags($format) {
  static $geshifilter_tags_cache = array();
  if (!isset($geshifilter_tags_cache[$format])) {
    $generic_code_tags = _geshifilter_tag_split(geshifilter_tags($format));
    $language_tags = array();
    $tag_to_lang = array();
    $enabled_languages = _geshifilter_get_enabled_languages();
    foreach ($enabled_languages as $language => $fullname) {
      $lang_tags = _geshifilter_tag_split(geshifilter_language_tags($language, $format));
      foreach ($lang_tags as $lang_tag) {
        $language_tags[] = $lang_tag;
        $tag_to_lang[$lang_tag] = $language;
      }
    }
    $geshifilter_tags_cache[$format] = array(
      $generic_code_tags,
      $language_tags,
      $tag_to_lang,
    );
  }
  return $geshifilter_tags_cache[$format];
}

/**
 * Helper function for generating a GeSHi object.
 *
 * @param string $source_code
 *   The source code to process.
 * @param string $language
 *   The language to generate a GeSHi object for.
 */
function _geshifilter_geshi_factory($source_code, $language) {
  $available_languages = _geshifilter_get_available_languages();
  $geshi = new GeSHi($source_code, $language);
  $geshi->set_language_path($available_languages[$language]['language_path']);
  return $geshi;
}

/**
 * Helper function for splitting a string on white spaces.
 *
 * Using explode(' ', $string) is not enough because it returns empty elements
 * if $string contains consecutive spaces.
 */
function _geshifilter_whitespace_explode($string) {
  return preg_split('/\s+/', $string, -1, PREG_SPLIT_NO_EMPTY);
}

/**
 * Document this function.
 *
 * @param string $string
 *   Need documentation.
 *
 * @return string
 *   The tag split
 *
 * @todo Document this function.
 */
function _geshifilter_tag_split($string) {
  return preg_split('/\s+|<|>|\[|\]/', $string, -1, PREG_SPLIT_NO_EMPTY);
}

/**
 * Need documentation.
 *
 * @return string
 *   Need documentation.
 *
 * @todo Document this function.
 */
function geshifilter_tags() {
  $config = \Drupal::config('geshifilter.settings');
  return $config->get('tags', 'code blockcode');
}

/**
 * Need documentation.
 *
 * @param string $format
 *   Need documentation.
 *
 * @return string
 *   Need documentation
 *
 * @todo Document this function.
 */
function _geshifilter_tag_styles($format = NULL) {
  $config = \Drupal::config('geshifilter.settings');
  if (!$config->get('use_format_specific_options', FALSE) || $format === NULL) {
    $value = $config->get('tag_styles', array(
      GESHIFILTER_BRACKETS_ANGLE => GESHIFILTER_BRACKETS_ANGLE,
      GESHIFILTER_BRACKETS_SQUARE => GESHIFILTER_BRACKETS_SQUARE,
    ));
  }
  else {
    $value = $config->get('tag_styles_' . $format, _geshifilter_tag_styles());
  }
  return $value;
}

/**
 * Need documentation.
 *
 * @param string $language
 *   Need documentation.
 *
 * @return string
 *   Need Documentation.
 *
 * @todo Document this function.
 */
function geshifilter_language_tags($language) {
  $config = \Drupal::config('geshifilter.settings');
  if (!$config->get('use_format_specific_options', FALSE) || $format === NULL) {
    return $config->get("language_tags_{$language}", '');
  }
  return $config->get("language_tags_{$language}_{$format}", geshifilter_language_tags($language));
}
