<?php

namespace Drupal\geshifilter;

// Necessary for return response of generateCss().
use Symfony\Component\HttpFoundation\Response;

// Necessary for URL.
use Drupal\Core\Url;

class GeshiFilterCss {
  /**
   * Create the page that show the css in use.
   *
   * @return \Symfony\Component\HttpFoundation\Response Response
   *   Return the css to show.
   */
  public static function generateCss() {
    $headers = array();
    $headers['Content-type'] = 'text/css';
    $css = self::generateLanguagesCssRules();
    $response = new Response($css, 200, $headers);
    return $response;
  }

  /**
   * Helper for checking if an automatically managed style sheet is possible.
   *
   * @return bool
   *   Indicating if an automatically managed style sheet is possible.
   */
  public static function managedExternalStylesheetPossible() {
    $directory = self::languageCssPath(TRUE);
    return file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
  }

  /**
   * Get the path for css file.
   *
   * @param bool $dironly
   *   If wants only the dir, not the full path + file.
   *
   * @return string
   *   full path to css file.
   */
  public static function languageCssPath($dironly = FALSE) {
    $directory = file_default_scheme() . '://geshi';
    if(!$dironly) {
      $directory .= '/geshifilter-languages.css';
    }
    return $directory;
  }


  /**
   * Helper function for generating the CSS rules.
   *
   * @return string
   *   String with the CSS rules.
   */
  public static function generateLanguagesCssRules() {
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
  public static function generateLanguagesCssFile($force = FALSE) {
    $force = true;
    $config = \Drupal::config('geshifilter.settings');
    $languages = _geshifilter_get_enabled_languages();
    // Serialize the array of enabled languages as sort of hash.
    $languages_hash = serialize($languages);

    // Check if generation of the CSS file is needed.
    if ($force || $languages_hash != $config->get('cssfile_languages')) {
      // Build stylesheet.
      $stylesheet = self::generateLanguagesCssRules();
      // Save stylesheet.
      $stylesheet_filename = self::languageCssPath();

      $ret = file_save_data($stylesheet, $stylesheet_filename, FILE_EXISTS_REPLACE);
      if ($ret) {
        drupal_set_message(t('(Re)generated external CSS style sheet %file.', array('%file' => $ret->getFilename())));
      }
      else {
        drupal_set_message(t('Could not generate external CSS file. Check the settings of your <a href="!filesystem">file system</a>.', array(
              '!filesystem' => URL::fromRoute('system.file_system_settings')
                ->toString()
            )), 'error');
      }
      // Remember for which list of languages the CSS file was generated.
      $config->set('cssfile_languages', $languages_hash);
      $config->save();
    }
  }
} 