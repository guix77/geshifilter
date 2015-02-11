<?php
/**
 * @file
 * Definition of Drupal\geshifilter\Tests\GeshiFilterAdministrationTest.
 */

// Namespace of tests.
namespace Drupal\geshifilter\Tests;

// Use of base class for the tests.
use Drupal\simpletest\WebTestBase;

use \Drupal\geshifilter\GeshiFilter;

/**
 * Test for administrative interface of GeshiFilter.
 *
 * @group geshifilter
 */
class GeshiFilterAdministrationTest extends WebTestBase {

  /**
   * A global filter adminstrator.
   */
  protected $filterAdminUser;

  /**
   * The id of the text format with only GeSHi filter in it.
   */
  protected $inputFormatIid;

  /**
   * List of modules to enable.
   */
  public static $modules = array('libraries', 'geshifilter','filter');

  /**
   * Configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Set up the tests and create the users.
   */
  public function setUp() {
    parent::setUp();

    // Create object with configuration.
    $this->config = \Drupal::configFactory()->getEditable('geshifilter.settings');

    // Create a filter admin user.
    $permissions = array(
      'administer filters',
      'access administration pages',
      'administer site configuration',
    );
    $this->filterAdminUser = $this->drupalCreateUser($permissions);

    // Log in with filter admin user.
    $this->drupalLogin($this->filterAdminUser);

    // Set some default GeSHi filter admin settings.
    // Set default highlighting mode to "do nothing".
    $this->config->set('default_highlighting', GeshiFilter::DEFAULT_PLAINTEXT);
    $this->config->set('use_format_specific_options', FALSE);
    $this->config->set('tag_styles', array(
      GeshiFilter::BRACKETS_ANGLE => GeshiFilter::BRACKETS_ANGLE,
      GeshiFilter::BRACKETS_SQUARE => GeshiFilter::BRACKETS_SQUARE,
    ));
    $this->config->set('default_line_numbering', GeshiFilter::LINE_NUMBERS_DEFAULT_NONE);
    $this->config->save();
  }

  /**
   * Tags should differ between languages and from generic tags.
   */
  public function testTagUnicity() {
    // Enable some languages first.
    $this->config->set('language.php.enabled', TRUE);
    $this->config->set('language.python.enabled', TRUE);

    // First round: without format specific tag options.
    $this->config->set('use_format_specific_options', FALSE);
    $this->config->set('tags', 'code blockcode generictag');
    $this->config->save();

    // A language tag should differ from the generic tags.
    $form_values = array(
      'language[php][tags]' => 'php generictag',
    );
    $this->drupalPostForm('admin/config/content/formats/geshifilter/languages/all', $form_values, t('Save configuration'));
    $this->assertText(t('The language tags should differ between languages and from the generic tags.'), t('Language tags should differ from generic tags (with generic tag options)'));

    // Language tags should differ between languages.
    $form_values = array(
      'language[php][tags]' => 'php languagetag',
      'language[python][tags]' => 'languagetag python',
    );
    $this->drupalPostForm('admin/config/content/formats/geshifilter/languages/all', $form_values, t('Save configuration'));
    $this->assertText(t('The language tags should differ between languages and from the generic tags.'), t('Language tags should differ between languages (with generic tag options)'));

    /* @todo Must uncoment this make make this test work. */
    // Second round: with format specific tag options.
    // Current format specific options are not working, so will uncoment and
    // fix this part of test latter.
    // $this->config->set('use_format_specific_options', TRUE);
    // $this->config->set('tags_' . $this->input_format_id,
    // 'code blockcode generictag');
    // A language tag should differ from the generic tags.
    // $form_values = array(
    // 'geshifilter_language_tags_php_' . $this->input_format_id =>
    // 'php generictag');
    // $this->drupalPostForm('admin/config/content/formats/' .
    // $this->input_format_id
    // . '/configure', $form_values, t('Save configuration'));
    // $this->assertText(t('The language tags should differ between languages
    // and from the generic tags.'), t('Language tags should differ from
    // (with format specific tag options)'));
    // Language tags should differ between languages.
    // $form_values = array(
    // 'geshifilter_language_tags_php_' . $this->input_format_id =>
    // 'php languagetag',
    // 'geshifilter_language_tags_python_' . $this->input_format_id =>
    // 'languagetag python',
    // );
    // $this->drupalPostForm('admin/config/content/formats/' .
    // $this->input_format_id .
    // '/configure', $form_values, t('Save configuration'));
    // $this->assertText(t('The language tags should differ between languages
    // and from the
    // generic tags.'), t('Language tags should differ between languages (with
    // format specific tag options)'));
  }

  /**
   * Tests for GeshiFilterLanguageForm.
   */
  public function testLanguagesForm() {
    $edit = array();
    $edit['language[xml][enabled]'] = TRUE;
    $edit['language[xml][tags]'] = "<xml>";
    $this->drupalPostForm('admin/config/content/formats/geshifilter/languages/all', $edit, t('Save configuration'));
    $this->drupalGet('admin/config/content/formats/geshifilter/languages/all');
    $this->assertFieldChecked('edit-language-xml-enabled', 'The language is enabled.');
    $this->assertRaw('&lt;xml&gt;', 'The tag is defined.');
  }
}
