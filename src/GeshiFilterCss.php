<?php

namespace Drupal\geshifilter;

use Symfony\Component\HttpFoundation\Response;

class GeshiFilterCss {
  public function generateCSS() {
    $css = 'Hello!';
    $response = new Response($css, 200, array());
    return $response;
  }
} 