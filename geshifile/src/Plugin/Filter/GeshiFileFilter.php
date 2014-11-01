<?php

/**
 * @file
 * Contains \Drupal\geshifile\Plugin\Filter\GeshiFileFilter.
 */

// Namespace for filter.
namespace Drupal\geshifile\Plugin\Filter;

// Base class for filters.
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\FilterProcessResult;
use Drupal\geshifilter\GeshiFilterProcess;


/**
 * Provides a base filter for Geshi Filter.
 *
 * @Filter(
 *   id = "filter_geshifile",
 *   module = "geshifile",
 *   title = @Translation("GeSHi file"),
 *   description = @Translation("Enables syntax highlighting of a file
 *     source code using the GeSHi engine"),
 *   type = \Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   cache = TRUE,
 *   weight = 0
 * )
 */
class GeshiFileFilter extends FilterBase {
  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->config = \Drupal::config('geshifilter.settings');
  }

  /**
   * Performs the filter processing.
   *
   * @param string $text
   *   The text string to be filtered.
   * @param string $langcode
   *   The language code of the text to be filtered.
   *
   * @return \Drupal\filter\FilterProcessResult
   *   The filtered text, wrapped in a FilterProcessResult object, and possibly
   *   with associated assets, cache tags and #post_render_cache callbacks.
   *
   * @see \Drupal\filter\FilterProcessResult
   */
  public function process($text, $langcode) {
    $text = preg_replace_callback('/\[geshifile\s*(.*?)\]/s', array($this, 'replace'), $text);

    // Create the object with result.
    $result = new FilterProcessResult($text);
    return $result;
  }

  private function replace($match) {
    $attr = array(
      'file' => '',
      'linenumbers' => $this->config->get('default_line_numbering'),
      'title' => '',
      'fancy' => 0,
      'language' => 'text',
    );
    $start = true;
    $pieces = explode("=", $match[1]);
    foreach($pieces as $piece)  {
      if ($start) {
        $key = $piece;
        $start = false;
      }
      else {
        $p = explode('"', $piece);
        $attr[trim($key)] = $p[1];
        $key = trim($p[2]);
      }
    }
    // File is not set.
    if ($attr['file'] == '') {
      return '';
    }

    $filename = drupal_realpath('public://' . $attr['file']);
    $dirname =  drupal_realpath('public://');
    if((strpos($filename, $dirname) === 0) && file_exists($filename)) {
      $file = file_get_contents($filename);
      return GeshiFilterProcess::geshiProcess($file, $attr['language']);
    }
    drupal_set_message($this->t('file do not exist in geshifile !filename', array('!filename' => $filename)));
    return '';
  }
}
