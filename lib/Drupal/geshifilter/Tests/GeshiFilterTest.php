<?php

/**
 * @file
 * Definition of Drupal\geshifilter\Tests\GeshiFilterTest.
 */

// Namespace of tests.
namespace Drupal\geshifilter\Tests;

// Use of base class for the tests.
use Drupal\simpletest\WebTestBase;


// Include module, used for constants.
require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.module';

// Include GeSHi filtering functions.
require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.pages.inc';

/**
 * Tests for GeshiFilter.
 *
 * Those tests are for the content of the node, to make sure they are
 * processed by geshi library.
 */
class GeshiFilterTest extends WebTestBase {

  /**
   * A global filter adminstrator.
   */
  protected $filterAdminUser;

  /**
   * A global user for adding pages.
   */
  protected $normalUser;

  /**
   * Object with configuration.
   */
  protected $config;

  /**
   * List of modules to enable.
   */
  public static $modules = array('libraries', 'geshifilter');

  /**
   * The number of current node.
   */
  protected $node;

  /**
   * Return metadata about the test.
   */
  public static function getInfo() {
    return array(
      'name'  => t('GeSHi input filter'),
      'description'  => t('Test the administration settings for GeSHi filter.'),
      'group' => t('GeSHi filter module'),
    );
  }

  /**
   * Code run before each and every test method.
   */
  public function setUp() {
    parent::setUp();

    // Restore node to default value.
    $this->node = 1;

    // Create object with configuration.
    $this->config = \Drupal::config('geshifilter.settings');

    // And set the path to the geshi library.
    $this->config->set('geshi_dir', 'sites/all/libraries/geshi');

    // Create a content type, as we will create nodes on test.
    $settings = array(
      // Override default type (a random name).
      'type' => 'geshifilter_content_type',
    );
    $this->drupalCreateContentType($settings);

    // Create a filter admin user.
    $permissions = array(
      'administer filters',
      'administer nodes',
      'access administration pages',
      'create geshifilter_content_type content',
      'edit any geshifilter_content_type content',
      'administer site configuration',
    );
    $this->filterAdminUser = $this->drupalCreateUser($permissions);

    // Create a normal user for page creation.
    $permissions = array(
      'create geshifilter_content_type content',
      'edit any geshifilter_content_type content',
    );
    $this->normalUser = $this->drupalCreateUser($permissions);

    // Log in with filter admin user.
    $this->drupalLogin($this->filterAdminUser);

    // Add an text format with only geshi filter.
    $this->createTextFormat('geshifilter_text_format', array('filter_geshifilter'));

    // Set some default GeSHi filter admin settings.
    // Set default highlighting mode to "do nothing".
    $this->config->set('default_highlighting', GESHIFILTER_DEFAULT_PLAINTEXT);
    $this->config->set('format_specific_options', FALSE);
    $this->config->set('tag_styles', array(
      GESHIFILTER_BRACKETS_ANGLE => GESHIFILTER_BRACKETS_ANGLE,
      GESHIFILTER_BRACKETS_SQUARE => GESHIFILTER_BRACKETS_SQUARE,
    ));
    $this->config->set('default_line_numbering', GESHIFILTER_LINE_NUMBERS_DEFAULT_NONE);
    $this->config->save();

    // Log out as filter admin.
    $this->drupalGet('logout');

    // Log in as the normal user for adding pages.
    $this->drupalLogin($this->normalUser);
  }

  /**
   * Create a new text format.
   *
   * @param string $format_name
   *   The name of new text format.
   * @param array $filters
   *   Array with the machine names of filters to enable.
   */
  protected function createTextFormat($format_name, array $filters) {
    $edit = array();
    $edit['format'] = $format_name;
    $edit['name'] = $this->randomName();
    $edit['roles[' . DRUPAL_AUTHENTICATED_RID . ']'] = 1;
    foreach ($filters as $filter) {
      $edit['filters[' . $filter . '][status]'] = TRUE;
    }
    $this->drupalPostForm('admin/config/content/formats/add', $edit, t('Save configuration'));
    $this->assertRaw(t('Added text format %format.', array('%format' => $edit['name'])), 'New filter created.');
    $this->drupalGet('admin/config/content/formats');
  }

  /**
   * Assert function for testing if GeSHi highlighting works.
   *
   * @param string $body
   *   The body text of the node.
   * @param array $check_list
   *   List of items that should be in rendered output (assertRaw).
   *   An item is something like array($source_code, $lang, $line_numbering,
   *   $linenumbers_start, $inline_mode). If $lang is set, GeSHifilter syntax
   *   highlighting is applied to $sourcecode. If $lang is false, $sourcecode is
   *   directly looked for.
   * @param string $description
   *   Description of the assertion.
   * @param bool $invert
   *   If assertNoRaw should be used instead of assertRaw.
   */
  protected function assertGeshiFilterHighlighting($body, array $check_list, $description, $invert = FALSE) {
    // Create a node.
    $node = array(
      'title' => 'Test for GeShi Filter',
      'body' => array(
        array(
          'value' => $body . "\n" . $this->randomName(100),
          'format' => 'geshifilter_text_format',
        ),
      ),
      'type' => 'geshifilter_content_type',
    );
    $this->drupalCreateNode($node);

    $this->drupalGet('node/' . $this->node);
    $this->node++;
    // $format = entity_load('filter_format', 'geshifilter_text_format');
    // $filter = $format->filters('geshifilter');
    // $format->settings['format'];
    foreach ($check_list as $fragment) {
      list($source_code, $lang, $line_numbering, $linenumbers_start, $inline_mode) = $fragment;
      if ($lang) {
        // Apply syntax highlighting.
        $source_code = geshifilter_geshi_process($source_code, $lang, $line_numbering, $linenumbers_start, $inline_mode);
      }
      debug($source_code);
      if ($invert) {
        $this->assertNoRaw($source_code, $description);
      }
      else {
        $this->assertRaw($source_code, $description);
      }
    }
  }

  /**
   * Test the standard functionality of the generic tags.
   */
  public function testGenericTags() {
    $this->config->set('tags', 'code');
    $this->config->set('language_enabled_c', TRUE);
    $this->config->set('language_enabled_cpp', TRUE);
    $this->config->set('language_enabled_csharp', TRUE);
    $this->config->set('language_enabled_java', TRUE);
    $this->config->save();

    // Body material.
    $source_code = "//C++-ish source code\nfor (int i=0; i<10; ++i) {\n  fun(i);\n  bar.foo(x, y);\n server->start(&pool); \n}";

    // Check language argument.
    $this->assertGeshiFilterHighlighting('<code type="cpp">' . $source_code . '</code>',
      array(array($source_code, 'cpp', 0, 1, FALSE)),
      t('Checking type="..." argument'));
    $this->assertGeshiFilterHighlighting('<code lang="cpp">' . $source_code . '</code>',
      array(array($source_code, 'cpp', 0, 1, FALSE)),
      t('Checking lang="..." argument'));
    $this->assertGeshiFilterHighlighting('<code language="cpp">' . $source_code . '</code>',
      array(array($source_code, 'cpp', 0, 1, FALSE)),
      t('Checking language="..." argument'));

    // Check some languages.
    $languages = array('c', 'cpp', 'java');
    foreach ($languages as $lang) {
      $this->assertGeshiFilterHighlighting('<code language="' . $lang . '">' .
        $source_code . '</code>', array(array($source_code, $lang, 0, 1, FALSE)),
        t('Checking language="@lang"', array('@lang' => $lang)));
    }

    // Check line_numbering argument.
    $this->assertGeshiFilterHighlighting('<code type="cpp" linenumbers="off">' . $source_code . '</code>',
      array(array($source_code, 'cpp', 0, 1, FALSE)),
      t('Checking linenumbers="off" argument'));
    $this->assertGeshiFilterHighlighting('<code type="cpp" linenumbers="normal">' . $source_code . '</code>',
      array(array($source_code, 'cpp', 1, 1, FALSE)),
      t('Checking linenumbers="normal" argument'));
    $this->assertGeshiFilterHighlighting('<code type="cpp" start="27">' . $source_code . '</code>',
      array(array($source_code, 'cpp', 1, 27, FALSE)),
      t('Checking start="27" argument'));
    $this->assertGeshiFilterHighlighting('<code type="cpp" linenumbers="fancy">' . $source_code . '</code>',
      array(array($source_code, 'cpp', 5, 1, FALSE)),
      t('Checking linenumbers="fancy" argument'));
    $this->assertGeshiFilterHighlighting('<code type="cpp" fancy="3">' . $source_code . '</code>',
      array(array($source_code, 'cpp', 3, 1, FALSE)),
      t('Checking fancy="3" argument'));
  }

  /**
   * Test with brackets only angle.
   */
  public function testBracketsOnlyAngle() {
    $this->config->set('tags', 'code');
    $this->config->set('language_enabled_cpp', TRUE);
    // Enable only angle brackets.
    $this->config->set('geshifilter_tag_styles', array(
      GESHIFILTER_BRACKETS_ANGLE => GESHIFILTER_BRACKETS_ANGLE,
    ));
    $this->config->save();

    $source_code = "//C++ source code\nfor (int i=0; i<10; ++i) {\n  fun(i);\n  bar.foo(x, y);\n server->start(&pool); \n}";
    // This should be filtered.
    $this->assertGeshiFilterHighlighting('<code language="cpp">' . $source_code . '</code>',
      array(array($source_code, 'cpp', 0, 1, FALSE)),
      t('Checking angle brackets style in GESHIFILTER_BRACKETS_ANGLE mode'));
    // This should not be filtered.
    $this->assertGeshiFilterHighlighting('[code language="cpp"]' . $source_code . '[/code]',
      array(array($source_code, NULL, 0, 1, FALSE)),
      t('Checking [foo] brackets style in GESHIFILTER_BRACKETS_ANGLE mode'));
    $this->assertGeshiFilterHighlighting('[[code language="cpp"]]' . $source_code . '[[/code]]',
      array(array($source_code, NULL, 0, 1, FALSE)),
      t('Checking [[foo]] brackets style in GESHIFILTER_BRACKETS_ANGLE mode'));
    $this->assertGeshiFilterHighlighting('<?php' . $source_code . '?>',
      array(array($source_code, NULL, 0, 1, FALSE)),
      t('Checking php code blocks in GESHIFILTER_BRACKETS_ANGLE mode'));
  }

  /**
   * Test with brackets only square.
   */
  public function testBracketsOnlySquare() {
    $this->config->set('tags', 'code');
    $this->config->set('language_enabled_cpp', TRUE);
    $source_code = "//C++ source code\nfor (int i=0; i<10; ++i) {\n  fun(i);\n  bar.foo(x, y);\n server->start(&pool); \n}";
    // Enable only square brackets.
    $this->config->set('geshifilter_tag_styles', array(
      GESHIFILTER_BRACKETS_SQUARE => GESHIFILTER_BRACKETS_SQUARE,
    ));
    $this->config->save();
    // This should be filtered.
    $this->assertGeshiFilterHighlighting('[code language="cpp"]' . $source_code . '[/code]',
      array(array($source_code, 'cpp', 0, 1, FALSE)),
      t('Checking [foo] brackets style in GESHIFILTER_BRACKETS_SQUARE mode'));
    // This should not be filtered.
    $this->assertGeshiFilterHighlighting('<code language="cpp">' . $source_code . '</code>',
      array(array($source_code, NULL, 0, 1, FALSE)),
      t('Checking angle brackets style in GESHIFILTER_BRACKETS_SQUARE mode'));
    $this->assertGeshiFilterHighlighting('[[code language="cpp"]]' . $source_code . '[[/code]]',
      array(array($source_code, NULL, 0, 1, FALSE)),
      t('Checking [[foo]] brackets style in GESHIFILTER_BRACKETS_SQUARE mode'));
    $this->assertGeshiFilterHighlighting('<?php' . $source_code . '?>',
      array(array($source_code, NULL, 0, 1, FALSE)),
      t('Checking php code blocks in GESHIFILTER_BRACKETS_SQUARE mode'));
  }

  /**
   * Test with brackets only double square.
   */
  public function testBracketsOnlyDoubleSquare() {
    $this->config->set('tags', 'code');
    $this->config->set('language_enabled_cpp', TRUE);
    $source_code = "//C++ source code\nfor (int i=0; i<10; ++i) {\n  fun(i);\n  bar.foo(x, y);\n server->start(&pool); \n}";
    // Enable only double square brackets.
    $this->config->set('geshifilter_tag_styles', array(
      GESHIFILTER_BRACKETS_DOUBLESQUARE => GESHIFILTER_BRACKETS_DOUBLESQUARE,
    ));
    $this->config->save();

    // This should be filtered.
    $this->assertGeshiFilterHighlighting('[[code language="cpp"]]' . $source_code . '[[/code]]',
      array(array($source_code, 'cpp', 0, 1, FALSE)),
      t('Checking [[foo]] brackets style in GESHIFILTER_BRACKETS_DOUBLESQUARE mode'));
    // This should not be filtered.
    $this->assertGeshiFilterHighlighting('<code language="cpp">' . $source_code . '</code>',
      array(array($source_code, NULL, 0, 1, FALSE)),
      t('Checking angle brackets style in GESHIFILTER_BRACKETS_DOUBLESQUARE mode'));
    $this->assertGeshiFilterHighlighting('[code language="cpp"]' . $source_code . '[/code]',
      array(array($source_code, NULL, 0, 1, FALSE)),
      t('Checking [foo] brackets style in GESHIFILTER_BRACKETS_DOUBLESQUARE mode'));
    $this->assertGeshiFilterHighlighting('<?php' . $source_code . '?>',
      array(array($source_code, NULL, 0, 1, FALSE)),
      t('Checking php code blocks in GESHIFILTER_BRACKETS_DOUBLESQUARE mode'));
  }

  /**
   * Test with brackets only php code block.
   */
  public function testBracketsOnlyPhpCodeBlock() {
    $this->config->set('tags', 'code');
    $this->config->set('language_enabled_cpp', TRUE);
    $source_code = "//C++ source code\nfor (int i=0; i<10; ++i) {\n  fun(i);\n  bar.foo(x, y);\n server->start(&pool); \n}";
    // Enable only double square brackets.
    $this->config->set('tag_styles', array(
      GESHIFILTER_BRACKETS_PHPBLOCK => GESHIFILTER_BRACKETS_PHPBLOCK,
    ));
    $this->config->save();

    // This should be filtered.
    $this->assertGeshiFilterHighlighting('<?php' . $source_code . '?>',
      array(array($source_code, 'php', 0, 1, FALSE)),
      t('Checking php code blocks in GESHIFILTER_BRACKETS_PHPBLOCK mode'));
    // This should not be filtered.
    $this->assertGeshiFilterHighlighting('<code language="cpp">' . $source_code . '</code>',
      array(array($source_code, NULL, 0, 1, FALSE)),
      t('Checking angle brackets style in GESHIFILTER_BRACKETS_PHPBLOCK mode'));
    $this->assertGeshiFilterHighlighting('[code language="cpp"]' . $source_code . '[/code]',
      array(array($source_code, NULL, 0, 1, FALSE)),
      t('Checking [foo] brackets style in GESHIFILTER_BRACKETS_PHPBLOCK mode'));
    $this->assertGeshiFilterHighlighting('[[code language="cpp"]]' . $source_code . '[[/code]]',
      array(array($source_code, NULL, 0, 1, FALSE)),
      t('Checking [[foo]] brackets style in GESHIFILTER_BRACKETS_PHPBLOCK mode'));
  }


  /**
   * Check if tags like [c++] and [c#] work.
   *
   * Problem described in http://drupal.org/node/208720.
   */
  public function testSpecialTags() {
    // Enabled the tags.
    $this->config->set('language_enabled_cpp', TRUE);
    $this->config->set('language_tags_cpp', 'c++');
    $this->config->set('language_enabled_csharp', TRUE);
    $this->config->set('language_tags_csharp', 'c#');
    $this->config->save();
    // Body material.
    $source_code = "//C++-ish source code\nfor (int i=0; i<10; ++i) {\n  fun(i);\n  bar.foo(x, y);\n server->start(&pool); \n}";
    // Test the tags.
    $this->assertGeshiFilterHighlighting('<c++>' . $source_code . '</c++>',
      array(array($source_code, 'cpp', 0, 1, FALSE)),
      t('Checking <c++>..</c++>'));
    $this->assertGeshiFilterHighlighting('<c#>' . $source_code . '</c#>',
      array(array($source_code, 'csharp', 0, 1, FALSE)),
      t('Checking <c#>..</c#>'));
  }

  /**
   * Test if tags like [cpp], [css], [csharp] aren't highjacked by [c].
   */
  public function testPrefixTags() {
    // Enabled the tags.
    $this->config->set('language_enabled_c', TRUE);
    $this->config->set('language_tags_c', 'c');
    $this->config->set('language_enabled_cpp', TRUE);
    $this->config->set('language_tags_cpp', 'cpp');
    $this->config->set('language_enabled_csharp', TRUE);
    $this->config->set('language_tags_csharp', 'csharp');
    $this->config->save();
    // Body material.
    $source_code = "//C++-ish source code\nfor (int i=0; i<10; ++i) {\n  fun(i);\n  bar.foo(x, y);\n server->start(&pool); \n}";
    // Test the tags.
    $this->assertGeshiFilterHighlighting('<cpp>' . $source_code . '</cpp>',
      array(array($source_code, 'cpp', 0, 1, FALSE)),
      t('Source code in <cpp>...</cpp> should work when <c>...</c> is also enabled'));
    $this->assertGeshiFilterHighlighting('<csharp>' . $source_code . '</csharp>',
      array(array($source_code, 'csharp', 0, 1, FALSE)),
      t('Source code in <csharp>...</csharp> should work when <c>...</c> is also enabled'));
  }

  /**
   * Test for do nothing mode.
   */
  public function testDoNothingMode() {
    // Enable C++.
    $this->config->set('language_enabled_cpp', TRUE);
    $this->config->set('language_tags_cpp', 'cpp');
    // Set default highlighting mode to "do nothing".
    $this->config->set('default_highlighting', GESHIFILTER_DEFAULT_DONOTHING);
    $this->config->save();
    // Body material with some characters ('<' and '&') that would be escaped
    // Except in "do nothing" mode.
    $source_code = "//C++-ish source code\nfor (int i=0; i!=10; ++i) {\n  fun(i);\n  bar.foo(x, y);\n}";
    // Tests.
    $this->assertGeshiFilterHighlighting('<code>' . $source_code . '</code>',
      array(array('<code>' . $source_code . '</code>', FALSE, 0, 1, FALSE)),
      t('Do nothing mode should not touch given source code')
    );
    $this->assertGeshiFilterHighlighting('<code language="cpp">' . $source_code . '</code>',
      array(array($source_code, 'cpp', 0, 1, FALSE)),
      t('Highlighting with language="cpp" should work when default is "do nothing"')
    );
    $this->assertGeshiFilterHighlighting('<cpp>' . $source_code . '</cpp>',
      array(array($source_code, 'cpp', 0, 1, FALSE)),
      t('Highlighting with <cpp>...</cpp> should work when default is "do nothing"')
    );
  }

  /**
   * Test title attribute on code block.
   */
  public function testTitleAttributeOnCodeBlock() {
    $source_code = "for (int i=0; i!=10; ++i) {\n  fun(i);\n  bar.foo(x, y);\n}";
    // No title set.
    $this->assertGeshiFilterHighlighting('<code language="cpp">' . $source_code . '</code>',
      array(array('geshifilter-title', FALSE, 0, 0, 0)),
      t('Setting the title attritbute on code block.'),
     TRUE
    );
    // Title set.
    $this->assertGeshiFilterHighlighting('<code language="cpp" title="Foo the bar!">' . $source_code . '</code>',
      array(
        array('<div class="geshifilter-title">Foo the bar!</div>',
          FALSE,
          0,
          0,
          0,
        ),
      ),
      t('Setting the title attritbute on code block.')
    );
  }

  /**
   * Test title attribute on inline code.
   */
  public function testTitleAttributeOnInlineCode() {
    $source_code = "for (int i=0; i!=10; ++i) { fun(i); }";
    // No title set.
    $this->assertGeshiFilterHighlighting('<code language="cpp">' . $source_code . '</code>',
      array(array('<span class="geshifilter">', FALSE, 0, 0, 0)),
      t('Setting the title attritbute on inline code.')
    );
    // Title set.
    $this->assertGeshiFilterHighlighting('<code language="cpp" title="Foo the bar!">' . $source_code . '</code>',
      array(
        array(
          '<span class="geshifilter" title="Foo the bar!">',
          FALSE,
          0,
          0,
          0,
        ),
      ),
      t('Setting the title attritbute on inline code.')
    );
  }


  /**
   * Issue http://drupal.org/node/1010216.
   */
  public function testSquareBracketConfusion() {
    $this->config->set('tags', 'code');
    $this->config->set('nguage_enabled_ini', TRUE);
    $source_code = "[section]\nserver=192.0.2.62  ; IP address\nport=12345";
    // Enable square brackets.
    $this->config->set('tag_styles', array(
      GESHIFILTER_BRACKETS_SQUARE => GESHIFILTER_BRACKETS_SQUARE,
    ));
    $this->config->save();
    // This should be filtered.
    $this->assertGeshiFilterHighlighting('[code]' . $source_code . '[/code]',
      array(array($source_code, 'text', 0, 1, FALSE)),
      t('Checking if [code][section]...[/code] works'));
    $this->assertGeshiFilterHighlighting('[code language="ini"]' . $source_code . '[/code]',
      array(array($source_code, 'ini', 0, 1, FALSE)),
      t('Checking if [code language="ini"][section]...[/code] works'));
  }

}
