<?php

/**
 * @file
 * Functions that handle the actual GeSHi processing.
 *
 * The actual GeSHi filter stuff: parsing the text to filter,
 * configure the GeSHi parser, feed the code to the GeSHi parser and put the
 * result back in the text.
 */

require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.inc';

/**
 * Helper function for parsing the attributes of GeSHi code tags.
 *
 * To get the settings for language, line numbers, etc.
 *
 * @param string $attributes
 *   String with the attributes.
 * @param string $filter
 *   The filter in use.
 *
 * @return array
 *   An array of settings with fields 'language', 'line_numbering',
 *   'linenumbers_start' and 'title'.
 */
function _geshifilter_parse_attributes($attributes, $filter) {
  // Initial values.
  $lang = NULL;
  $line_numbering = NULL;
  $linenumbers_start = NULL;
  $title = NULL;

  // Get the possible tags and languages.
  list($generic_code_tags, $language_tags, $tag_to_lang) = $filter->getTags();

  $language_attributes = _geshifilter_whitespace_explode(GESHIFILTER_ATTRIBUTES_LANGUAGE);
  $attributes_preg_string = implode('|', array_merge(
    $language_attributes,
    array(
      GESHIFILTER_ATTRIBUTE_LINE_NUMBERING,
      GESHIFILTER_ATTRIBUTE_LINE_NUMBERING_START,
      GESHIFILTER_ATTRIBUTE_FANCY_N,
      GESHIFILTER_ATTRIBUTE_TITLE,
    )
  ));
  $enabled_languages = _geshifilter_get_enabled_languages();

  // Parse $attributes to an array $attribute_matches with:
  // $attribute_matches[0][xx] fully matched string, e.g. 'language="python"'
  // $attribute_matches[1][xx] param name, e.g. 'language'
  // $attribute_matches[2][xx] param value, e.g. 'python'
  preg_match_all('#(' . $attributes_preg_string . ')="?([^"]*)"?#', $attributes, $attribute_matches);

  foreach ($attribute_matches[1] as $a_key => $att_name) {
    // Get attribute value.
    $att_value = $attribute_matches[2][$a_key];

    // Check for the language attributes.
    if (in_array($att_name, $language_attributes)) {
      // Try first to map the attribute value to geshi language code.
      if (in_array($att_value, $language_tags)) {
        $att_value = $tag_to_lang[$att_value];
      }
      // Set language if extracted language is an enabled language.
      if (array_key_exists($att_value, $enabled_languages)) {
        $lang = $att_value;
      }
    }

    // Check for line numbering related attributes.
    // $line_numbering defines the line numbering mode:
    // 0: no line numbering.
    // 1: normal line numbering.
    // n>= 2: fancy line numbering every nth line.
    elseif ($att_name == GESHIFILTER_ATTRIBUTE_LINE_NUMBERING) {
      switch (strtolower($att_value)) {
        case "off":
          $line_numbering = 0;
          break;

        case "normal":
          $line_numbering = 1;
          break;

        case "fancy":
          $line_numbering = 5;
          break;
      }
    }
    elseif ($att_name == GESHIFILTER_ATTRIBUTE_FANCY_N) {
      $att_value = (int) ($att_value);
      if ($att_value >= 2) {
        $line_numbering = $att_value;
      }
    }
    elseif ($att_name == GESHIFILTER_ATTRIBUTE_LINE_NUMBERING_START) {
      if ($line_numbering < 1) {
        $line_numbering = 1;
      }
      $linenumbers_start = (int) ($att_value);
    }
    elseif ($att_name == GESHIFILTER_ATTRIBUTE_TITLE) {
      $title = $att_value;
    }
  }
  // Return parsed results.
  return array(
    'language' => $lang,
    'line_numbering' => $line_numbering,
    'linenumbers_start' => $linenumbers_start,
    'title' => $title,
  );
}

/**
 * Callback for _geshifilter_prepare for preparing input text.
 *
 * Replaces the code tags brackets with geshifilter specific ones to prevent
 * possible messing up by other filters, e.g.
 *   '[python]foo[/python]' to '[geshifilter-python]foo[/geshifilter-python]'.
 * Replaces newlines with "&#10;" to prevent issues with the line break filter
 * Escapes the tricky characters like angle brackets with check_plain() to
 * prevent messing up by other filters like the HTML filter.
 */
function _geshifilter_prepare_callback($match, $format) {
  $config = \Drupal::config('geshifilter.settings');
  // $match[0]: complete matched string
  // $match[1]: opening bracket ('<' or '[')
  // $match[2]: tag
  // $match[3]: and
  // $match[4]: attributes
  // $match[5]: closing bracket
  // $match[6]: source code
  // $match[7]: closing tag.
  $tag_name = $match[2];
  $tag_attributes = $match[3];
  $content = $match[6];

  // Get the default highlighting mode.
  $lang = $config->get('default_highlighting', GESHIFILTER_DEFAULT_PLAINTEXT);
  if ($lang == GESHIFILTER_DEFAULT_DONOTHING) {
    // If the default highlighting mode is GESHIFILTER_DEFAULT_DONOTHING
    // and there is no language set (with language tag or language attribute),
    // we should not do any escaping in this prepare phase,
    // so that other filters can do their thing.
    $enabled_languages = _geshifilter_get_enabled_languages();

    // Usage of language tag?
    list($generic_code_tags, $language_tags, $tag_to_lang) = _geshifilter_get_tags($format);
    if (isset($tag_to_lang[$tag_name]) && isset($enabled_languages[$tag_to_lang[$tag_name]])) {
      $lang = $tag_to_lang[$tag_name];
    }
    // Usage of language attribute?
    else {
      // Get additional settings from the tag attributes.
      $settings = _geshifilter_parse_attributes($tag_attributes, $format);
      if ($settings['language'] && isset($enabled_languages[$settings['language']])) {
        $lang = $settings['language'];
      }
    }
    // If no language was set: prevent escaping and return original string.
    if ($lang == GESHIFILTER_DEFAULT_DONOTHING) {
      return $match[0];
    }
  }
  // Return escaped code block.
  return '[geshifilter-' . $tag_name . $tag_attributes . ']'
    . str_replace(array("\r", "\n"), array('', '&#10;'), check_plain($content))
    . '[/geshifilter-' . $tag_name . ']';
}

/**
 * Callback for _geshifilter_prepare for < ?php ... ? > blocks.
 */
function _geshifilter_prepare_php_callback($match) {
  return '[geshifilter-questionmarkphp]'
    . str_replace(array("\r", "\n"), array('', '&#10;'), check_plain($match[2]))
    . '[/geshifilter-questionmarkphp]';
}


/**
 * Helper function for overriding some GeSHi defaults.
 */
function _geshifilter_override_geshi_defaults(&$geshi, $langcode) {
  $config = \Drupal::config('geshifilter.settings');
  // Override the some default GeSHi styles (e.g. GeSHi uses Courier by default,
  // which is ugly).
  $geshi->set_line_style('font-family: monospace; font-weight: normal;', 'font-family: monospace; font-weight: bold; font-style: italic;');
  $geshi->set_code_style('font-family: monospace; font-weight: normal; font-style: normal');
  // Overall class needed for CSS.
  $geshi->set_overall_class('geshifilter-' . $langcode);
  // Set keyword linking.
  $geshi->enable_keyword_links($config->get('enable_keyword_urls', TRUE));
}

/**
 * General geshifilter processing function for a chunk of source code.
 */
function geshifilter_process_sourcecode($source_code, $lang, $line_numbering = 0,
    $linenumbers_start = 1, $inline_mode = FALSE, $title = NULL) {
  $config = \Drupal::config('geshifilter.settings');
  // Process.
  if ($lang == 'php' && $config->get('use_highlight_string_for_php', FALSE)) {
    return geshifilter_highlight_string_process($source_code, $inline_mode);
  }
  else {
    // Process with GeSHi.
    return geshifilter_geshi_process($source_code, $lang, $line_numbering, $linenumbers_start, $inline_mode, $title);
  }
}

/**
 * Geshifilter wrapper for GeSHi processing.
 */
function geshifilter_geshi_process($source_code, $lang, $line_numbering = 0, $linenumbers_start = 1, $inline_mode = FALSE, $title = NULL) {
  $config = \Drupal::config('geshifilter.settings');
  // Load GeSHi library (if not already).
  $geshi_library = libraries_load('geshi');
  if (!$geshi_library['loaded']) {
    drupal_set_message($geshi_library['error message'], 'error');
    return $source_code;
  }

  // Check for a cached version of this source code and return it if available.
  // @todo: Use a dedicated table instead of using cache_filter? If so,
  // also take care of the flushing in _geshifilter_clear_filter_cache().
  // $cac_id="geshifilter:$lang:$line_numbering:$linenumbers_start:$inline_mode"
  // . md5($title . $source_code);
  // if ($cached = cache_get($cac_id, 'cache_filter')) {
  // return $cached->data;
  // }.
  // Remove leading/trailing newlines.
  $source_code = trim($source_code, "\n\r");
  // Create GeSHi object.
  $geshi = _geshifilter_geshi_factory($source_code, $lang);

  // CSS mode.
  $ccs_mode = $config->get('geshifilter_css_mode', GESHIFILTER_CSS_INLINE);
  if ($ccs_mode == GESHIFILTER_CSS_CLASSES_AUTOMATIC || $ccs_mode == GESHIFILTER_CSS_CLASSES_ONLY) {
    $geshi->enable_classes(TRUE);
  }
  _geshifilter_override_geshi_defaults($geshi, $lang);
  // Some more GeSHi settings and parsing.
  if ($inline_mode) {
    // Inline source code mode.
    $geshi->set_header_type(GESHI_HEADER_NONE);
    // To make highlighting work we have to manually set a class on the code
    // element we will wrap the code in.
    // To counter a change between GeSHi version 1.0.7.22 and 1.0.8 (svn
    // commit 1610), we use both the language and overall_class for the class,
    // to mimic the 1.0.8 behavior, which is backward compatible.
    $code_class = "{$geshi->language} {$geshi->overall_class}";
    $source_code = '<span class="geshifilter"'
      . (isset($title) ? ' title="' . check_plain($title) . '"' : '')
      . '><code class="' . $code_class . '">' . $geshi->parse_code() . '</code></span>';
  }
  else {
    // Block source code mode.
    $geshi->set_header_type((int) $config->get('code_container', GESHI_HEADER_PRE));
    if ($line_numbering == 1) {
      $geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
      $geshi->start_line_numbers_at($linenumbers_start);
    }
    elseif ($line_numbering >= 2) {
      $geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS, $line_numbering);
      $geshi->start_line_numbers_at($linenumbers_start);
    }
    if (isset($title)) {
      $source_code = '<div class="geshifilter-title">' . check_plain($title) . '</div>';
    }
    else {
      $source_code = '';
    }
    $source_code .= '<div class="geshifilter">' . $geshi->parse_code() . '</div>';
  }

  // Store in cache with a minimum expiration time of 1 day.
  // cache_set($cache_id, $source_code, 'cache_filter', time() +
  // (60 * 60 * 24));
  return $source_code;
}

/**
 * Geshifilter wrapper for highlight_string() processing of PHP.
 */
function geshifilter_highlight_string_process($source_code, $inline_mode) {
  // Make sure that the source code starts with < ?php and ends with ? >
  $text = trim($source_code);
  if (substr($text, 0, 5) != '<?php') {
    $source_code = '<?php' . $source_code;
  }
  if (substr($text, -2) != '?>') {
    $source_code = $source_code . '?>';
  }
  // Use the right container.
  $container = $inline_mode ? 'span' : 'div';
  // Process with highlight_string()
  $text = '<' . $container . ' class="codeblock geshifilter">' . highlight_string($source_code, TRUE) . '</' . $container . '>';
  // Remove newlines (added by highlight_string()) to avoid issues with the
  // linebreak filter.
  $text = str_replace("\n", '', $text);
  return $text;
}
