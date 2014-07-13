<?php

/**
 * @file
 * Contains \Drupal\geshifilter\Form\GeshiFilterSettingsForm.
 */

namespace Drupal\geshifilter\Form;

use Drupal\Core\Form\ConfigFormBase;

// Need this for _geshifilter_general_highlight_tags_settings().
require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.admin.inc';

// Need this for constants.
require_once drupal_get_path('module', 'geshifilter') . '/geshifilter.module';

class GeshiFilterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geshifilter_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $config = \Drupal::config('geshifilter.settings');

    // Try to load GeSHi library and get version if successful.
    $geshi_library = libraries_load('geshi');

    // GeSHi library settings (constant GESHI_VERSION is defined in GeSHi
    // library).
    $form['geshifilter_library'] = array(
      '#type' => 'fieldset',
      '#title' => defined('GESHI_VERSION') ? t('GeSHi library version @version detected', array('@version' => GESHI_VERSION)) : t('GeSHi library'),
      '#description' => t('The GeSHi filter requires the GeSHi library (which needs to be <a href="!geshi">downloaded</a> and installed seperately).', array('!geshi' => url('http://qbnz.com/highlighter/'))),
      '#collapsible' => TRUE,
      '#collapsed' => $geshi_library['loaded'],
    );

    // If the GeSHi library is loaded, show all the options and settings.
    if ($geshi_library['loaded']) {

      // Option for flushing the GeSHi language definition cache.
      $form['geshifilter_library']['geshi_language_definition_caching'] = array(
        '#type' => 'item',
        '#title' => t('GeSHi language definition caching'),
        '#description' => t('The GeSHi library uses languages definition files to define the properties and highlight rules of the supported languages. In most scenarios these language definition files do not change and a lot of derivative data, such as the list of available languages or the CSS style sheet, can be cached for efficiency reasons. Sometimes however, this cache needs to be flushed and the languages definition files need to be reparsed, for example after an upgrade of the GeSHi library or after adding/editing some language definition files manually.'),
      );
      // Non-submitting button for flushing the GeSHi language definition file
      // cache.
      $form['geshifilter_library']['geshi_language_definition_caching']['flush_geshi_language_definition_cache'] = array(
        '#type' => 'button',
        '#value' => t("Flush the GeSHi language definition cache"),
        '#executes_submit_callback' => TRUE,
        '#submit' => array('_geshifilter_flush_language_definition_cache'),
      );

      // GeSHi filter tags and delimiters options.
      $form['geshifilter_tag_options'] = array(
        '#type' => 'fieldset',
        '#title' => t('GeSHi filter tags and delimiters'),
        '#collapsible' => TRUE,
      );
      // Usage of format specific options.
      $form['geshifilter_tag_options']['geshifilter_use_format_specific_options'] = array(
        '#type' => 'checkbox',
        '#title' => t('Use text format specific tag settings.'),
        '#default_value' => $config->get('use_format_specific_options', FALSE),
        '#description' => t('Enable seperate tag settings of the GeSHi filter for each <a href="!input_formats">text format</a> instead of global tag settings.', array('!input_formats' => url('admin/config/content/formats'))),
      );
      // Generic tags settings.
      // @todo: do this conditional form part showing/hiding in jQuery instead
      // of in the form builder.
      if (!$config->get('use_format_specific_options', FALSE)) {
        $form['geshifilter_tag_options']['geshifilter_general_tags'] = \_geshifilter_general_highlight_tags_settings();
        // $form['#validate'][] = '_geshifilter_tag_styles_validate';
      }

      // GeSHi filter highlighting options.
      $form['geshifilter_highlighting_options'] = array(
        '#type' => 'fieldset',
        '#title' => t('Syntax highlighting options'),
        '#collapsible' => TRUE,
      );
      // Default language.
      $languages = _geshifilter_get_enabled_languages();
      $form['geshifilter_highlighting_options']['geshifilter_default_highlighting'] = array(
        '#type' => 'select',
        '#title' => t('Default highlighting mode'),
        '#default_value' => $config->get('default_highlighting', GESHIFILTER_DEFAULT_PLAINTEXT),
        '#options' => array(
          t('No highlighting') => array(
            GESHIFILTER_DEFAULT_DONOTHING => t('Do nothing'),
            GESHIFILTER_DEFAULT_PLAINTEXT => t('As plain text'),
          ),
          t('Languages') => $languages,
        ),
        '#description' => t('Select the default highlighting mode to use when no language is defined with a language attribute in the tag.'),
      );
      // Default line numbering scheme.
      $form['geshifilter_highlighting_options']['geshifilter_default_line_numbering'] = array(
        '#type' => 'select',
        '#title' => t('Default line numbering'),
        '#default_value' => $config->get('default_line_numbering', GESHIFILTER_LINE_NUMBERS_DEFAULT_NONE),
        '#options' => array(
          GESHIFILTER_LINE_NUMBERS_DEFAULT_NONE => t('no line numbers'),
          GESHIFILTER_LINE_NUMBERS_DEFAULT_NORMAL => t('normal line numbers'),
          GESHIFILTER_LINE_NUMBERS_DEFAULT_FANCY5 => t('fancy line numbers (every @n lines)', array('@n' => GESHIFILTER_LINE_NUMBERS_DEFAULT_FANCY5)),
          GESHIFILTER_LINE_NUMBERS_DEFAULT_FANCY10 => t('fancy line numbers (every @n lines)', array('@n' => GESHIFILTER_LINE_NUMBERS_DEFAULT_FANCY10)),
          GESHIFILTER_LINE_NUMBERS_DEFAULT_FANCY20 => t('fancy line numbers (every @n lines)', array('@n' => GESHIFILTER_LINE_NUMBERS_DEFAULT_FANCY20)),
        ),
        '#description' => t('Select the default line numbering scheme: no line numbers, normal line numbers or fancy line numbers. With fancy line numbers every n<sup>th</sup> line number is highlighted. (GeSHi documentation: <a href="!link">Line numbers</a>).', array('!link' => 'http://qbnz.com/highlighter/geshi-doc.html#line-numbers')),
      );
      // Highlight_string usage option.
      $form['geshifilter_highlighting_options']['geshifilter_use_highlight_string_for_php'] = array(
        '#type' => 'checkbox',
        '#title' => t('Use built-in PHP function <code>highlight_string()</code> for PHP source code.'),
        '#description' => t('When enabled, PHP source code will be syntax highlighted with the built-in PHP function <code><a href="!highlight_string">highlight_string()</a></code> instead of with the GeSHi library. GeSHi features, like line numbering and usage of an external CSS stylesheet for example, are not available.', array('!highlight_string' => 'http://php.net/manual/en/function.highlight-string.php')),
        '#default_value' => $config->get('use_highlight_string_for_php', FALSE),
      );
      // Option to disable Keyword URL's
      $form['geshifilter_highlighting_options']['geshifilter_enable_keyword_urls'] = array(
        '#type' => 'checkbox',
        '#title' => t('Enable GeSHi keyword URLs'),
        '#description' => t('For some languages GeSHi can link language keywords (e.g. standard library functions) to their online documentation. (GeSHi documentation: <a href="!link">Keyword URLs</a>).', array('!link' => 'http://qbnz.com/highlighter/geshi-doc.html#keyword-urls')),
        '#default_value' => $config->get('enable_keyword_urls', TRUE),
      );

      // Styling, layout and CSS.
      $form['geshifilter_styling'] = array(
        '#type' => 'fieldset',
        '#title' => t('Styling, layout and CSS'),
        '#collapsible' => TRUE,
      );

      // CSS mode.
      $form['geshifilter_styling']['geshifilter_css_mode'] = array(
        '#type' => 'radios',
        '#title' => t('CSS mode for syntax highlighting'),
        '#options' => array(
          GESHIFILTER_CSS_INLINE => t('Inline CSS style attributes.'),
          GESHIFILTER_CSS_CLASSES_AUTOMATIC => t('Use CSS classes and an automatically managed external CSS style sheet.'),
          GESHIFILTER_CSS_CLASSES_ONLY => t('Only add CSS classes to the markup.'),
        ),
        '#default_value' => $config->get('css_mode', GESHIFILTER_CSS_INLINE),
        '#description' => t('Inline CSS is easier to set up, does not depend on
          an external style sheets and is consequently more robust to copy/paste
          operations like content aggregation. However, usage of CSS classes and
          an external stylesheet requires much less markup code and bandwidth.
          The external style sheet can be managed automatically by the GeSHi
          filter module, but this feature requires the public
          <a href="!filesystem">download method</a>. If the GeSHi filter is
          configured to only add the CSS classes to the markup, the
          administrator or themer is responsible for adding the appropriate CSS
          rules to the pages (e.g. based on <a href="!cssdefaults">these
          defaults</a>). (GeSHi documentation: <a href="!geshidoc">Using CSS
          Classes</a>).',
          array(
            '!geshidoc' => 'http://qbnz.com/highlighter/geshi-doc.html#using-css-classes',
            '!filesystem' => url('admin/config/media/file-system'),
            '!cssdefaults' => url('admin/config/content/formats/geshifilter/generate_css'),
          )
        ),
      );

      // Code container.
      $container_options = array(
        GESHI_HEADER_PRE => t('%val: uses a @cnt wrapper, efficient whitespace coding, no automatic line wrapping, generates invalid HTML with line numbering.', array('%val' => 'GESHI_HEADER_PRE', '@cnt' => '<pre>')),
        GESHI_HEADER_DIV => t('%val: uses a @cnt wrapper, enables automatic line wrapping.', array('%val' => 'GESHI_HEADER_DIV', '@cnt' => '<div>')),
      );
      if (version_compare(GESHI_VERSION, '1.0.8', '>=')) {
        $container_options[GESHI_HEADER_PRE_VALID] = t('%val: uses @pre
          wrappers, ensures valid HTML with line numbering, but generates more
          markup.',
          array(
            '%val' => 'GESHI_HEADER_PRE_VALID',
            '@pre' => '<pre>',
            '@li' => '<li>')
          );
        $container_options[GESHI_HEADER_PRE_TABLE] = t('%val: uses a @table construction for adding line numbers which avoids selection/copy/paste problems.', array('%val' => 'GESHI_HEADER_PRE_TABLE', '@table' => '<table>'));
      }
      if (version_compare(GESHI_VERSION, '1.0.7.2', '>=')) {
        $container_options[GESHI_HEADER_NONE] = t('%val: uses no wrapper.', array('%val' => 'GESHI_HEADER_NONE'));
      }

      $form['geshifilter_styling']['geshifilter_code_container'] = array(
        '#type' => 'radios',
        '#title' => t('Code container, wrapping technique'),
        '#description' => t('Define the wrapping technique to use for code blocks. (GeSHi documentation: <a href="!link">The Code Container</a>).', array('!link' => 'http://qbnz.com/highlighter/geshi-doc.html#the-code-container')
        ),
        '#options' => $container_options,
        '#default_value' => $config->get('code_container', GESHI_HEADER_PRE),
      );
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    // Check if automatically managed style sheet is posible.
    if (isset($form_state['values']['geshifilter_css_mode']) && $form_state['values']['geshifilter_css_mode'] == GESHIFILTER_CSS_CLASSES_AUTOMATIC && !_geshifilter_managed_external_stylesheet_possible()) {
      form_set_error('geshifilter_css_mode', t('GeSHi filter can not automatically manage an external CSS style sheet when the download method is private.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $config = \Drupal::config('geshifilter.settings');
    $config->set('use_format_specific_options', $form_state['values']['geshifilter_use_format_specific_options'])
           ->set('default_highlighting', $form_state['values']['geshifilter_default_highlighting'])
           ->set('default_line_numbering', $form_state['values']['geshifilter_default_line_numbering'])
           ->set('use_highlight_string_for_php', $form_state['values']['geshifilter_use_highlight_string_for_php'])
           ->set('enable_keyword_urls', $form_state['values']['geshifilter_enable_keyword_urls'])
           ->set('css_mode', $form_state['values']['geshifilter_css_mode'])
           ->set('code_container', $form_state['values']['geshifilter_code_container']);
    // These values are not always set, so this prevents a warning.
    if (isset($form_state['values']['geshifilter_tags'])) {
      $config->set('tags', $form_state['values']['geshifilter_tags']);
      $config->set('tag_styles', $form_state['values']['geshifilter_tag_styles']);
    }
    $config->save();

    // Regenerate language css.
    if ($config->get('css_mode', GESHIFILTER_CSS_INLINE) == GESHIFILTER_CSS_CLASSES_AUTOMATIC) {
      _geshifilter_generate_languages_css_file();
    }
    // Always clear the filter cache.
    _geshifilter_clear_filter_cache();
    parent::submitForm($form, $form_state);
  }

}
