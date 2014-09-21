<?php
/**
 * @file
 * Contains \Drupal\example\Controller\ExampleController.
 */

namespace Drupal\geshifilter\Controller;

use Drupal\Core\Controller\ControllerBase;

class GeshiFilterConflicts extends ControllerBase  {
  /**
   * {@inheritdoc}
   */
  public function content() {
    $conflicts = \Drupal\geshifilter\GeshiFilterConflicts::listConflicts();
    if (count($conflicts) == 0) {
      $build = array(
        '#type' => 'markup',
        '#markup' => t('No conflicts found.'),
      );
      return $build;
    }
  }
}
