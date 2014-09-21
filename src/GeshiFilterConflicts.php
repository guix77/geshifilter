<?php

/**
 * @file
 * Implementation of the conflict detection feature of the GeSHi filter.
 */

// Namespace for the module.
namespace Drupal\geshifilter;

require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.inc';

class GeshiFilterConflicts {

  /**
   * Menu callback for filter conflicts page.
   *
   * @return array
   *   An array with the filter conflics found, or an empty array if there is
   *   no conflics.
   */
  public static function listConflicts() {
    return array();
  }

  /**
   * Conflict detection for html filter.
   */
  protected function htmlfilter($format, $cfilter, $geshifilter) {
    $conflicts = array();
    return $conflicts;
  }

  /**
   * Conflict detection for codefilter.
   */
  protected function codefilter($format, $cfilter, $geshifilter) {
    $conflicts = array();
    return $conflicts;
  }

}
