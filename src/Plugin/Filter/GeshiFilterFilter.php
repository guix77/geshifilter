<?php

/**
 * @file
 * Contains \Drupal\geshifilter\Plugin\Filter\GeshiFilterFilter.
 */

// Namespace for filter.
namespace Drupal\geshifilter\Plugin\Filter;

// Base class for filters.
use Drupal\filter\Plugin\FilterBase;

// Necessary for String::checkPlain().
use Drupal\Component\Utility\String;

// Necessary for forms.
use Drupal\Core\Form\FormStateInterface;

// Necessary for result of process().
use Drupal\filter\FilterProcessResult;

// Necessary for URL.
use Drupal\Core\Url;
use Drupal\geshifilter\GeshiFilterCss;

// Need this for geshifilter_use_format_specifc_options().
require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.inc';

// Need this for geshifilter_use_format_specifc_options().
require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.admin.inc';

// Need this for _geshifilter_prepare_callback(0 and others like this.
require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.pages.inc';

/**
 * Provides a base filter for Geshi Filter.
 *
 * @Filter(
 *   id = "filter_geshifilter",
 *   module = "geshifilter",
 *   title = @Translation("GeSHi filter"),
 *   description = @Translation("Enables syntax highlighting of inline/block
 *     source code using the GeSHi engine"),
 *   type = \Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   cache = FALSE,
 *   weight = 0
 * )
 */
class GeshiFilterFilter extends FilterBase {

  /**
   * Object with configuration for geshifilter.
   *
   * @var object
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->config = \Drupal::config('geshifilter.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    // Load GeSHi library (if not already).
    $geshi_library = libraries_load('geshi');
    if (!$geshi_library['loaded']) {
      drupal_set_message($geshi_library['error message'], 'error');
      return $text;
    }

    // Get the available tags.
    list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();
    if (in_array(GESHIFILTER_BRACKETS_PHPBLOCK, array_filter($this->tagStyles()))) {
      $language_tags[] = 'questionmarkphp';
      $tag_to_lang['questionmarkphp'] = 'php';
    }
    $tags = array_merge($generic_code_tags, $language_tags);
    // Escape special (regular expression) characters in tags (for tags like
    // 'c++' and 'c#').
    $tags = preg_replace('#(\\+|\\#)#', '\\\\$1', $tags);

    $tags_string = implode('|', $tags);
    // Pattern for matching the prepared "<code>...</code>" stuff.
    $pattern = '#\\[geshifilter-(' . $tags_string . ')([^\\]]*)\\](.*?)(\\[/geshifilter-\1\\])#s';
    $text = preg_replace_callback($pattern, array(
        $this,
        'replaceCallback'
      ), $text);

    // Create the object with result.
    $result = new FilterProcessResult($text);

    // Add the css file when necessary.
    if ($this->config->get('css_mode') == GESHIFILTER_CSS_CLASSES_AUTOMATIC) {
      $assets = array();
      $assets['css'][GeshiFilterCss::languageCssPath()] = array();
      $assets['css'][drupal_get_path('module', 'geshifilter') . '/assets/css/geshifilter.css'] = array();
      $result->addAssets($assets);
    }

    // Add cache tags, so we can re-create the node when some geshifilter
    // settings change.
    $cache_tags = array('geshifilter');
    $result->addCacheTags($cache_tags);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare($text, $langcode) {
    // Get the available tags.
    list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();
    $tags = array_merge($generic_code_tags, $language_tags);

    // Escape special (regular expression) characters in tags (for tags like
    // 'c++' and 'c#').
    $tags = preg_replace('#(\\+|\\#)#', '\\\\$1', $tags);
    $tags_string = implode('|', $tags);
    // Pattern for matching "<code>...</code>" like stuff
    // Also matches "<code>...$"  where "$" refers to end of string, not end of
    // line (because PCRE_MULTILINE (modifier 'm') is not enabled), so matching
    // still works when teaser view trims inside the source code.
    // Replace the code container tag brackets
    // and prepare the container content (newline and angle bracket protection).
    // @todo: make sure that these replacements can be done in series.
    $tag_styles = array_filter($this->tagStyles());
    if (in_array(GESHIFILTER_BRACKETS_ANGLE, $tag_styles)) {
      // Prepare <foo>..</foo> blocks.
      $pattern = '#(<)(' . $tags_string . ')((\s+[^>]*)*)(>)(.*?)(</\2\s*>|$)#s';
      // $text = preg_replace_callback($pattern, create_function('$match',
      // "return \_geshifilter_prepare_callback(\$match, '$id');"), $text);
      $text = preg_replace_callback($pattern, array($this, 'prepareCallback'), $text);
    }
    if (in_array(GESHIFILTER_BRACKETS_SQUARE, $tag_styles)) {
      // Prepare [foo]..[/foo] blocks.
      $pattern = '#((?<!\[)\[)(' . $tags_string . ')((\s+[^\]]*)*)(\])(.*?)
      ((?<!\[)\[/\2\s*\]|$)#s';
      // $pattern = '((?<!\[)\[)(php|code)((\s+[^\]]*)*)(\])(.*?)((?<!\[)\[\/\2\s*\]|$)/s';
      // $text = preg_replace_callback($pattern, create_function('$match',
      // "return \_geshifilter_prepare_callback(\$match, '$id');"), $text);
      $text = preg_replace_callback($pattern, array($this, 'prepareCallback'), $text);
    }
    if (in_array(GESHIFILTER_BRACKETS_DOUBLESQUARE, $tag_styles)) {
      // Prepare [[foo]]..[[/foo]] blocks.
      $pattern = '#(\[\[)(' . $tags_string . ')((\s+[^\]]*)*)(\]\])(.*?)
      (\[\[/\2\s*\]\]|$)#s';
      // $text = preg_replace_callback($pattern, create_function('$match',
      // "return \_geshifilter_prepare_callback(\$match, '$id');"), $text);
      $text = preg_replace_callback($pattern, array($this, 'prepareCallback'), $text);
    }
    if (in_array(GESHIFILTER_BRACKETS_PHPBLOCK, $tag_styles)) {
      // Prepare < ?php ... ? > blocks.
      $pattern = '#[\[<](\?php|\?PHP|%)(.+?)((\?|%)[\]>]|$)#s';
      // $text = preg_replace_callback($pattern,
      // '\_geshifilter_prepare_php_callback', $text);
      $text = preg_replace_callback($pattern, array($this, 'prepareCallback'), $text);
    }
    return $text;
  }

  /**
   * Get the tips for the filter.
   *
   * @param bool $long
   *   If get the long or short tip.
   *
   * @return string
   *   The tip to show for the user.
   */
  public function tips($long = FALSE) {
    // Get the supported tag styles.
    $tag_styles = array_filter($this->tagStyles());
    $tag_style_examples = array();
    $bracket_open = NULL;
    if (in_array(GESHIFILTER_BRACKETS_ANGLE, $tag_styles)) {
      if (!$bracket_open) {
        $bracket_open = String::checkPlain('<');
        $bracket_close = String::checkPlain('>');
      }
      $tag_style_examples[] = '<code>' . String::checkPlain('<foo>') . '</code>';
    }
    if (in_array(GESHIFILTER_BRACKETS_SQUARE, $tag_styles)) {
      if (!$bracket_open) {
        $bracket_open = String::checkPlain('[');
        $bracket_close = String::checkPlain(']');
      }
      $tag_style_examples[] = '<code>' . String::checkPlain('[foo]') . '</code>';
    }
    if (in_array(GESHIFILTER_BRACKETS_DOUBLESQUARE, $tag_styles)) {
      if (!$bracket_open) {
        $bracket_open = String::checkPlain('[[');
        $bracket_close = String::checkPlain(']]');
      }
      $tag_style_examples[] = '<code>' . String::checkPlain('[[foo]]') . '</code>';
    }
    if (!$bracket_open) {
      drupal_set_message(t('Could not determine a valid tag style for GeSHi filtering.'), 'error');
      $bracket_open = String::checkPlain('<');
      $bracket_close = String::checkPlain('>');
    }

    if ($long) {
      // Get the available tags.
      list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();
      // Get the available languages.
      $languages = _geshifilter_get_enabled_languages();
      $lang_attributes = _geshifilter_whitespace_explode(GESHIFILTER_ATTRIBUTES_LANGUAGE);

      // Syntax highlighting tags.
      $output = '<p>' . t('Syntax highlighting of source code can be enabled with the following tags:') . '</p>';
      $items = array();
      // Seneric tags.
      $tags = array();
      foreach ($generic_code_tags as $tag) {
        $tags[] = '"<code>' . $bracket_open . $tag . $bracket_close . '</code>"';
      }
      $items[] = t('Generic syntax highlighting tags: !tags.', array('!tags' => implode(', ', $tags)));
      // Language tags.
      $tags = array();
      foreach ($language_tags as $tag) {
        $tags[] = t('"<code>!tag</code>" for @lang source code', array(
          '!tag' => $bracket_open . $tag . $bracket_close,
          '@lang' => $languages[$tag_to_lang[$tag]])
        );
      }
      $items[] = t('Language specific syntax highlighting tags: !tags.', array('!tags' => implode(', ', $tags)));
      // PHP specific delimiters.
      if (in_array(GESHIFILTER_BRACKETS_PHPBLOCK, $tag_styles)) {
        $items[] = t('PHP source code can also be enclosed in &lt;?php ... ?&gt; or &lt;% ... %&gt;, but additional options like line numbering are not possible here.');
      }

      $output .= theme('item_list', array('items' => $items));

      // Options and tips.
      $output .= '<p>' . t('Options and tips:') . '</p>';
      $items = array();

      // Info about language attribute to language mapping.
      $att_to_full = array();
      foreach ($languages as $langcode => $fullname) {
        $att_to_full[$langcode] = $fullname;
      }
      foreach ($tag_to_lang as $tag => $lang) {
        $att_to_full[$tag] = $languages[$lang];
      }
      ksort($att_to_full);
      $att_for_full = array();
      foreach ($att_to_full as $att => $fullname) {
        $att_for_full[] = t('"<code>@langcode</code>" (for @fullname)', array('@langcode' => $att, '@fullname' => $fullname));
      }
      $items[] = t('The language for the generic syntax highlighting tags can be
        specified with one of the attribute(s): %attributes. The possible values
        are: !languages.', array(
          '%attributes' => implode(', ', $lang_attributes),
          '!languages' => implode(', ', $att_for_full),
        )
      );

      // Tag style options.
      if (count($tag_style_examples) > 1) {
        $items[] = t('The supported tag styles are: !tag_styles.', array('!tag_styles' => implode(', ', $tag_style_examples)));
      }

      // Line numbering options.
      $items[] = t('<em>Line numbering</em> can be enabled/disabled with the
        attribute "%linenumbers". Possible values are: "%off" for no line
        numbers, "%normal" for normal line numbers and "%fancy" for fancy line
        numbers (every n<sup>th</sup> line number highlighted). The start line
        number can be specified with the attribute "%start", which implicitly
        enables normal line numbering. For fancy line numbering the interval
        for the highlighted line numbers can be specified with the attribute
        "%fancy", which implicitly enables fancy line numbering.', array(
          '%linenumbers' => GESHIFILTER_ATTRIBUTE_LINE_NUMBERING,
          '%off' => 'off',
          '%normal' => 'normal',
          '%fancy' => 'fancy',
          '%start' => GESHIFILTER_ATTRIBUTE_LINE_NUMBERING_START,
          '%fancy' => GESHIFILTER_ATTRIBUTE_FANCY_N,
        )
      );

      // Block versus inline.
      $items[] = t('If the source code between the tags contains a newline (e.g.
        immediatly after the opening tag), the highlighted source code will be
        displayed as a code block. Otherwise it will be displayed inline.');

      // Code block title.
      $items[] = t('A title can be added to a code block with the attribute "%title".', array(
        '%title' => GESHIFILTER_ATTRIBUTE_TITLE,
      ));

      $output .= theme('item_list', array('items' => $items));

      // Defaults.
      $output .= '<p>' . t('Defaults:') . '</p>';
      $items = array();
      $default_highlighting = $config->get('default_highlighting');
      switch ($default_highlighting) {
        case GESHIFILTER_DEFAULT_DONOTHING:
          $description = t("when no language attribute is specified the code
            block won't be processed by the GeSHi filter");
          break;

        case GESHIFILTER_DEFAULT_PLAINTEXT:
          $description = t('when no language attribute is specified, no syntax
           highlighting will be done');
          break;

        default:
          $description = t('the default language used for syntax highlighting is
            "%default_lang"', array('%default_lang' => $default_highlighting));
          break;
      }
      $items[] = t('Default highlighting mode for generic syntax highlighting
        tags: !description.', array('!description' => $description));
      $default_line_numbering = $config->get('default_line_numbering');
      switch ($default_line_numbering) {
        case GESHIFILTER_LINE_NUMBERS_DEFAULT_NONE:
          $description = t('no line numbers');
          break;

        case GESHIFILTER_LINE_NUMBERS_DEFAULT_NORMAL:
          $description = t('normal line numbers');
          break;

        default:
          $description = t('fancy line numbers (every @n lines)', array('@n' => $default_line_numbering));
          break;
      }
      $items[] = t('Default line numbering: !description.', array('!description' => $description));
      $output .= theme('item_list', array('items' => $items));

      // Examples.
      $output .= '<p>' . t('Examples:') . '</p>';
      $header = array(t('You type'), t('You get'));
      $rows = array();
      if (count($generic_code_tags)) {
        $generic_code_tag = $generic_code_tags[0];
        $lang = array_rand($languages);
        $generic_code_tag_open = $bracket_open . $generic_code_tag;
        $generic_code_tag_close = $bracket_open . '/' . $generic_code_tag . $bracket_close;
        $rows[] = array(
          '<code>' . $generic_code_tag_open . $bracket_close . 'foo = "bar";' . $generic_code_tag_close . '</code>',
          t('Inline code with the default syntax highlighting mode.'),
        );
        $rows[] = array(
          '<code>' . $generic_code_tag_open . $bracket_close . '<br />foo = "bar";<br />baz = "foz";<br />' . $generic_code_tag_close . '</code>',
          t('Code block with the default syntax highlighting mode.'),
        );
        $rows[] = array(
          '<code>' . $generic_code_tag_open . ' ' . $lang_attributes[1 % count($lang_attributes)] . '="' . $lang . '" ' . GESHIFILTER_ATTRIBUTE_LINE_NUMBERING . '="normal"' . $bracket_close . '<br />foo = "bar";<br />baz = "foz";<br />' . $generic_code_tag_close . '</code>',
          t('Code block with syntax highlighting for @lang source code<br /> and normal line numbers.', array('@lang' => $languages[$lang])),
        );
        $rows[] = array(
          '<code>' . $generic_code_tag_open . ' ' . $lang_attributes[2 % count($lang_attributes)] . '="' . $lang . '" ' . GESHIFILTER_ATTRIBUTE_LINE_NUMBERING_START . '="23" ' . GESHIFILTER_ATTRIBUTE_FANCY_N . '="7"' . $bracket_close . '<br />foo = "bar";<br />baz = "foz";<br />' . $generic_code_tag_close . '</code>',
          t('Code block with syntax highlighting for @lang source code,<br />line numbers starting from 23<br /> and highlighted line numbers every 7<sup>th</sup> line.', array('@lang' => $languages[$lang])),
        );
      }
      if (count($language_tags)) {
        $language_tag = $language_tags[0];
        $rows[] = array(
          '<code>' . $bracket_open . $language_tag . $bracket_close . '<br />foo = "bar";<br />baz = "foz";<br />' . $bracket_open . '/' . $language_tag . $bracket_close . '</code>',
          t('Code block with syntax highlighting for @lang source code.', array('@lang' => $languages[$tag_to_lang[$language_tag]])),
        );
        $rows[] = array(
          '<code>' . $bracket_open . $language_tag . ' ' . GESHIFILTER_ATTRIBUTE_LINE_NUMBERING_START . '="23" ' . GESHIFILTER_ATTRIBUTE_FANCY_N . '="7"' . $bracket_close . '<br />foo = "bar";<br />baz = "foz";<br />' . $bracket_open . $language_tag . $bracket_close . '</code>',
          t('Code block with syntax highlighting for @lang source code,<br />line numbers starting from 23<br /> and highlighted line numbers every 7<sup>th</sup> line.', array('@lang' => $languages[$tag_to_lang[$language_tag]])),
        );
      }
      $output .= theme('table', array('header' => $header, 'rows' => $rows));
      return $output;
    }
    else {
      // Get the available tags.
      list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();
      $tags = array();
      foreach ($generic_code_tags as $tag) {
        $tags[] = '<code>' . $bracket_open . $tag . $bracket_close . '</code>';
      }
      foreach ($language_tags as $tag) {
        $tags[] = '<code>' . $bracket_open . $tag . $bracket_close . '</code>';
      }
      $output = t('You can enable syntax highlighting of source code with the following tags: !tags.', array('!tags' => implode(', ', $tags)));
      // Tag style options.
      if (count($tag_style_examples) > 1) {
        $output .= ' ' . t('The supported tag styles are: !tag_styles.', array('!tag_styles' => implode(', ', $tag_style_examples)));
      }
      if (in_array(GESHIFILTER_BRACKETS_PHPBLOCK, $tag_styles)) {
        $output .= ' ' . t('PHP source code can also be enclosed in &lt;?php ... ?&gt; or &lt;% ... %&gt;.');
      }
      return $output;
    }
  }

  /**
   * Create the settings form for the filter.
   *
   * @param array $form
   *   A minimally prepopulated form array.
   * @param FormStateInterface $form_state
   *   The state of the (entire) configuration form.
   *
   * @return array
   *   The $form array with additional form elements for the settings of
   *   this filter. The submitted form values should match $this->settings.
   *
   * @todo Add validation of submited form values, it already exists for
   *       drupal 7, must update it only.
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    if (!($this->config->get('use_format_specific_options'))) {
      // Tags and attributes.
      $form['general_tags'] = $this->generalHighlightTagsSettings();
      // $form['#validate'][] = '_geshifilter_tag_styles_validate';
      // Per language tags.
      $form['per_language_settings'] = array(
        '#type' => 'fieldset',
        '#title' => t('Per language tags'),
        '#collapsible' => TRUE,
        'table' => $this->perLanguageSettings('enabled', FALSE, TRUE),
      );
    }
    else {
      $form['info'] = array(
        '#markup' => '<p>' . t('GeSHi filter is configured to use global tag
          settings. For separate settings per text format, enable this option in
          the <a href="!geshi_admin_url">general GeSHi filter settings</a>.', array(
            '!geshi_admin_url' => URL::fromRoute('geshifilter.settings')->toString(),
          )
        ) . '</p>',
      );
    }
    $form['#validate'][] = 'geshifilter_per_language_settings_validate';
    return $form;
  }

  /**
   * Get the tags for this filter.
   *
   * @return string
   *   A string with the tags for this filter.
   */
  protected function tags() {
    if (!$this->config->get('use_format_specific_options')) {
      // We do not want per filter tags, so get the global tags.
      return $this->config->get('tags');
    }
    else {
      if (isset($this->settings['general_tags']['tags'])) {
        // Tags are set for this format.
        return $this->settings['general_tags']['tags'];
      }
      else {
        // Tags are not set for this format, so use the global ones.
        return $this->config->get('tags');
      }
    }
  }

  /**
   * Helper function for gettings the tags.
   *
   * Old: _geshifilter_get_tags.
   *
   * @todo: recreate a cache for this function.
   */
  protected function getTags() {
    $generic_code_tags = \_geshifilter_tag_split($this->tags());
    $language_tags = array();
    $tag_to_lang = array();
    $enabled_languages = \_geshifilter_get_enabled_languages();
    foreach ($enabled_languages as $language => $fullname) {
      $lang_tags = \_geshifilter_tag_split($this->languageTags($language));
      foreach ($lang_tags as $lang_tag) {
        $language_tags[] = $lang_tag;
        $tag_to_lang[$lang_tag] = $language;
      }
    }

    return array(
      $generic_code_tags,
      $language_tags,
      $tag_to_lang,
    );
  }

  /**
   * Helper function for some settings form fields.
   */
  protected function generalHighlightTagsSettings() {
    $form = array();

    // Generic tags.
    $form["tags"] = array(
      '#type' => 'textfield',
      '#title' => t('Generic syntax highlighting tags'),
      '#default_value' => $this->tags(),
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
      '#default_value' => $this->tagStyles(),
      '#description' => t('Select the container tag styles that should trigger GeSHi syntax highlighting.'),
    );
    return $form;
  }

  /**
   * Function for generating a form table for per language settings.
   */
  protected function perLanguageSettings($view, $add_checkbox, $add_tag_option) {
    $form = array();
    $form['header'] = array(
      '#type' => 'value',
      '#value' => array(),
    );
    $form['header']['#value'][] = t('Language');
    $form['header']['#value'][] = t('GeSHi language code');
    if ($add_tag_option) {
      $form['header']['#value'][] = t('Tag/language attribute value');
    }
    // Table body.
    $form['languages'] = array();
    $languages = _geshifilter_get_available_languages();
    foreach ($languages as $language => $language_data) {
      $enabled = $this->config->get("language_enabled_{$language}");
      // Skip items to hide.
      if (($view == 'enabled' && !$enabled) || ($view == 'disabled' && $enabled)) {
        continue;
      }
      // Build language row.
      $form['languages'][$language] = array();
      // Add enable/disable checkbox.
      if ($add_checkbox) {
        $form['languages'][$language]["language_enabled_{$language}"] = array(
          '#type' => 'checkbox',
          '#default_value' => $enabled,
          '#title' => $language_data['fullname'],
        );
      }
      else {
        $form['languages'][$language]['fullname'] = array(
          '#type' => 'markup',
          '#markup' => $language_data['fullname'],
        );
      }
      // Language code.
      $form['languages'][$language]['name'] = array(
        '#type' => 'markup',
        '#markup' => $language,
      );
      // Add a textfield for tags.
      if ($add_tag_option) {
        $form['languages'][$language]["language_tags_{$language}"] = array(
          '#type' => 'textfield',
          '#default_value' => $this->languageTags($language),
          '#size' => 20,
        );
      }
    }
    return $form;
  }

  /**
   * Get the tags for a language.
   *
   * @param string $language
   *   The language to get the tags(ex: php, html, ...).
   *
   * @return string
   *   The tags for the language(ex: [php],[php5],...).
   */
  private function languageTags($language) {
    if (!$this->config->get('use_format_specific_options')) {
      return $this->config->get("language.{$language}.tags");
    }
    else {
      $settings = $this->settings["per_language_settings"]['table']['languages'];
      if (isset($settings[$language]["language.{$language}.tags"])) {
        // Tags are set for this language.
        return $settings[$language]["language.{$language}.tags"];
      }
      else {
        // Tags are not set for this language, so use the global ones.
        return $this->config->get("language.{$language}.tags");
      }
    }
  }

  /**
   * Get the tag style.
   *
   * @return int
   *   Where to use [], <>, or both for tags.
   */
  protected function tagStyles() {
    $styles = array();
    if ($this->config->get('use_format_specific_options') == FALSE) {
      // Get global tag styles.
      $styles = $this->config->get('tag_styles');
    }
    else {
      if (isset($this->settings['general_tags']["tag_styles"])) {
        // Tags are set for this language.
        $styles = $this->settings['general_tags']["tag_styles"];
      }
      else {
        // Tags are not set for this language, so use the global ones.
        $styles = $this->config->get('tag_styles');
      }
    }
    return $styles;
  }

  /**
   * Callback for preg_replace_callback.
   *
   * Old: _geshifilter_replace_callback($match, $format).
   *
   * @param array $match
   *   Elements from array:
   *   - 0: complete matched string.
   *   - 1: tag name.
   *   - 2: tag attributes.
   *   - 3: tag content.
   *
   * @return string
   *   Return the string processed by geshi library.
   */
  protected function replaceCallback(array $match) {
    $complete_match = $match[0];
    $tag_name = $match[1];
    $tag_attributes = $match[2];
    $source_code = $match[3];

    // Undo linebreak and escaping from preparation phase.
    $source_code = decode_entities($source_code);

    // Initialize to default settings.
    $lang = $this->config->get('default_highlighting');
    $line_numbering = $this->config->get('default_line_numbering');
    $linenumbers_start = 1;
    $title = NULL;

    // Determine language based on tag name if possible.
    // list($generic_code_tags, $language_tags, $tag_to_lang) =
    // _geshifilter_get_tags($format);
    // if (in_array(GESHIFILTER_BRACKETS_PHPBLOCK,
    // array_filter(_geshifilter_tag_styles($format)))) {
    list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();
    if (in_array(GESHIFILTER_BRACKETS_PHPBLOCK, array_filter($this->tagStyles()))) {
      $language_tags[] = 'questionmarkphp';
      $tag_to_lang['questionmarkphp'] = 'php';
    }
    if (isset($tag_to_lang[$tag_name])) {
      $lang = $tag_to_lang[$tag_name];
    }

    // Get additional settings from the tag attributes.
    $settings = $this->parseAttributes($tag_attributes);
    if (isset($settings['language'])) {
      $lang = $settings['language'];
    }
    if (isset($settings['line_numbering'])) {
      $line_numbering = $settings['line_numbering'];
    }
    if (isset($settings['linenumbers_start'])) {
      $linenumbers_start = $settings['linenumbers_start'];
    }
    if (isset($settings['title'])) {
      $title = $settings['title'];
    }

    if ($lang == GESHIFILTER_DEFAULT_DONOTHING) {
      // Do nothing, and return the original.
      return $complete_match;
    }
    if ($lang == GESHIFILTER_DEFAULT_PLAINTEXT) {
      // Use plain text 'highlighting'
      $lang = 'text';
    }
    $inline_mode = (strpos($source_code, "\n") === FALSE);
    // Process and return.
    return \geshifilter_process_sourcecode($source_code, $lang, $line_numbering, $linenumbers_start, $inline_mode, $title);
  }

  /**
   * Helper function for parsing the attributes of GeSHi code tags.
   *
   * Get the settings for language, line numbers, etc.
   *
   * @param string $attributes
   *   String with the attributes.
   *
   * @return array of settings with fields 'language', 'line_numbering',
   *   'linenumbers_start' and 'title'.
   */
  public function parseAttributes($attributes) {
    // Initial values.
    $lang = NULL;
    $line_numbering = NULL;
    $linenumbers_start = NULL;
    $title = NULL;

    // Get the possible tags and languages.
    list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();

    $language_attributes = _geshifilter_whitespace_explode(GESHIFILTER_ATTRIBUTES_LANGUAGE);
    $attributes_preg_string = implode('|', array_merge(
      $language_attributes, array(
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
   * Callback_geshifilter_prepare for preparing input text.
   *
   * Replaces the code tags brackets with geshifilter specific ones to prevent
   * possible messing up by other filters, e.g.
   *   '[python]foo[/python]' to '[geshifilter-python]foo[/geshifilter-python]'.
   * Replaces newlines with "&#10;" to prevent issues with the line break filter
   * Escapes the tricky characters like angle brackets with String::checkPlain()
   * to prevent messing up by other filters like the HTML filter.
   *
   * @param array $match
   *   An array with the pieces from matched string.
   *   - 0: complete matched string.
   *   - 1: opening bracket ('<' or '[').
   *   - 2: tag.
   *   - 3: and.
   *   - 4: attributes.
   *   - 5: closing bracket.
   *   - 6: source code.
   *   - 7: closing tag.
   *
   * @return string
   *   Return escaped code block.
   */
  public function prepareCallback(array $match) {
    $tag_name = $match[2];
    $tag_attributes = $match[3];
    $content = $match[6];

    // Get the default highlighting mode.
    $lang = $this->config->get('default_highlighting');
    if ($lang == GESHIFILTER_DEFAULT_DONOTHING) {
      // If the default highlighting mode is GESHIFILTER_DEFAULT_DONOTHING
      // and there is no language set (with language tag or language attribute),
      // we should not do any escaping in this prepare phase,
      // so that other filters can do their thing.
      $enabled_languages = \_geshifilter_get_enabled_languages();
      // Usage of language tag?
      list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();
      if (isset($tag_to_lang[$tag_name]) && isset($enabled_languages[$tag_to_lang[$tag_name]])) {
        $lang = $tag_to_lang[$tag_name];
      }
      // Usage of language attribute?
      else {
        // Get additional settings from the tag attributes.
        $settings = $this->parseAttributes($tag_attributes);
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
      . str_replace(array("\r", "\n"), array('', '&#10;'), String::checkPlain($content))
      . '[/geshifilter-' . $tag_name . ']';
  }

  /**
   * Return the string with some html entities unencoded.
   *
   * Text editors like ckeditor encodes some strings, like " to &amp&quote;,
   * we must undo this on code passed to geshifilter.
   *
   * @param string $text
   *   The original text.
   *
   * @return string
   *   The text unencoded.
   */
  public function unencode($text) {
    return $text;
  }

}
