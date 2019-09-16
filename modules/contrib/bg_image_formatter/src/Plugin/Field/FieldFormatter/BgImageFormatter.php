<?php

declare(strict_types = 1);

namespace Drupal\bg_image_formatter\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Zend\Stdlib\ArrayUtils;

/**
 * Class BgImageFormatter.
 *
 * @FieldFormatter(
 *  id = "bg_image_formatter",
 *  label = @Translation("Background Image"),
 *  field_types = {"image"}
 * )
 */
class BgImageFormatter extends ImageFormatter {
  use AjaxHelperTrait;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * BgImageFormatter constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
   *   The image style storage.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function __construct(
        $plugin_id,
        $plugin_definition,
        FieldDefinitionInterface $field_definition,
        array $settings,
        $label,
        $view_mode,
        array $third_party_settings,
        AccountInterface $current_user,
        EntityStorageInterface $image_style_storage,
        RendererInterface $renderer,
        Request $request
    ) {
    parent::__construct(
        $plugin_id,
        $plugin_definition,
        $field_definition,
        $settings,
        $label,
        $view_mode,
        $third_party_settings,
        $current_user,
        $image_style_storage
    );
    $this->renderer = $renderer;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
        $plugin_id,
        $plugin_definition,
        $configuration['field_definition'],
        $configuration['settings'],
        $configuration['label'],
        $configuration['view_mode'],
        $configuration['third_party_settings'],
        $container->get('current_user'),
        $container->get('entity.manager')->getStorage('image_style'),
        $container->get('renderer'),
        $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'image_style' => '',
      'css_settings' => [
        'bg_image_selector' => 'body',
        'bg_image_color' => '#FFFFFF',
        'bg_image_x' => 'left',
        'bg_image_y' => 'top',
        'bg_image_attachment' => 'scroll',
        'bg_image_repeat' => 'no-repeat',
        'bg_image_background_size' => '',
        'bg_image_background_size_ie8' => 0,
        'bg_image_gradient' => '',
        'bg_image_media_query' => 'all',
        'bg_image_important' => 1,
        'bg_image_z_index' => 'auto',
        'bg_image_path_format' => 'absolute',
      ],
    ];
  }

  /**
   * Function taken from the module 'bg_image'.
   *
   * Adds a background image to the page using the
   * css 'background' property.
   *
   * @param string $image_path
   *   The path of the image to use. This can be either
   *      - A relative path e.g. sites/default/files/image.png
   *      - A uri: e.g. public://image.png.
   * @param array $css_settings
   *   An array of css settings to use. Possible values are:
   *      - bg_image_selector: The css selector to use
   *      - bg_image_color: The background color
   *      - bg_image_x: The x offset
   *      - bg_image_y: The y offset
   *      - bg_image_attachment: The attachment property (scroll or fixed)
   *      - bg_image_repeat: The repeat settings
   *      - bg_image_background_size: The background size property if necessary
   *    Default settings will be used for any values not provided.
   * @param string $image_style
   *   Optionally add an image style to the image before applying it to the
   *   background.
   *
   * @return array
   *   The array containing the CSS.
   */
  public function getBackgroundImageCss($image_path, array $css_settings = [], $image_style = NULL) {
    $defaults = self::defaultSettings();
    $css_settings += $defaults['css_settings'];

    // Pull the default css setting if not provided.
    $selector = Xss::filter($css_settings['bg_image_selector']);
    $bg_color = Xss::filter($css_settings['bg_image_color']);
    $bg_x = Xss::filter($css_settings['bg_image_x']);
    $bg_y = Xss::filter($css_settings['bg_image_y']);
    $attachment = $css_settings['bg_image_attachment'];
    $repeat = $css_settings['bg_image_repeat'];
    $important = $css_settings['bg_image_important'];
    $background_size = Xss::filter($css_settings['bg_image_background_size']);
    $background_size_ie8 = $css_settings['bg_image_background_size_ie8'];
    $background_gradient = !empty($css_settings['bg_image_gradient']) ? $css_settings['bg_image_gradient'] . ',' : '';
    $media_query = Xss::filter($css_settings['bg_image_media_query']);
    $z_index = Xss::filter($css_settings['bg_image_z_index']);

    // If important is true, we turn it into a string for css output.
    if ($important) {
      $important = '!important';
    }
    else {
      $important = '';
    }

    // Handle the background size property.
    $bg_size = '';
    $ie_bg_size = '';
    if ($background_size) {
      // CSS3.
      $bg_size = sprintf('background-size: %s %s;', $background_size, $important);
      // Let's cover ourselves for other browsers as well...
      $bg_size .= sprintf('-webkit-background-size: %s %s;', $background_size, $important);
      $bg_size .= sprintf('-moz-background-size: %s %s;', $background_size, $important);
      $bg_size .= sprintf('-o-background-size: %s %s;', $background_size, $important);
      // IE filters to apply the cover effect.
      if ($background_size === 'cover' && $background_size_ie8) {
        $ie_bg_size = sprintf(
        "filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src='%s', sizingMethod='scale');",
        $image_path
        );
        $ie_bg_size .= sprintf(
        "-ms-filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src='%s', sizingMethod='scale');",
        $image_path
        );
      }
    }

    // Add the css if we have everything we need.
    if ($selector && $image_path) {
      $style = sprintf('%s {', $selector);
      if ($bg_color) {
        $style .= sprintf('background-color: %s %s;', $bg_color, $important);
      }
      $style .= sprintf("background-image: %s url('%s') %s;", $background_gradient, $image_path, $important);
      if ($repeat) {
        $style .= sprintf('background-repeat: %s %s;', $repeat, $important);
      }
      if ($attachment) {
        $style .= sprintf('background-attachment: %s %s;', $attachment, $important);
      }
      if ($bg_x && $bg_y) {
        $style .= sprintf('background-position: %s %s %s;', $bg_x, $bg_y, $important);
      }
      if ($z_index) {
        $style .= sprintf('z-index: %s;', $z_index);
      }
      $style .= $bg_size;
      $style .= $background_size_ie8 ? $ie_bg_size : '';
      $style .= '}';

      return [
        'type' => 'inline',
        'style' => $style,
        'media' => !empty($media_query) ? $media_query : 'all',
        'group' => CSS_THEME,
      ];
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $settings = $this->getSettings();

    $element['image_style'] = [
      '#title' => $this->t('Image style'),
      '#type' => 'select',
      '#default_value' => $settings['image_style'],
      '#empty_option' => $this->t('None (original image)'),
      '#options' => image_style_options(),
      '#description' => $this->t(
            'Select <a href="@href_image_style">the image style</a> to apply on images',
            [
              '@href_image_style' => Url::fromRoute('image.style_add')->toString(),
            ]
      ),
    ];

    // Fieldset for css settings.
    $element['css_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default CSS Settings'),
      '#description' => $this->t(
            'Default CSS settings for outputting the background property.
                These settings will be concatenated to form a complete css statementthat uses the "background"
                property. For more information on the css background property see
                http://www.w3schools.com/css/css_background.asp"'
      ),
    ];
    // The selector for the background property.
    $element['css_settings']['bg_image_selector'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Selector(s)'),
      '#description' => $this->t(
            'A valid CSS selector that will be used to apply the background image. One per line.
                      If the field is a multivalue field, the first line will be applied to the first value,
                      the second to the second value... and so on. Tokens are supported.'
      ),
      '#default_value' => $settings['css_settings']['bg_image_selector'],
    ];
    // The token help relevant to this entity type.
    if (isset($form['#entity_type'])) {
      $element['css_settings']['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['user', $form['#entity_type']],
      ];
    }
    else {
      $element['css_settings']['token_help'] = [
        '#theme' => 'token_tree_link',
      ];
    }

    // The selector for the background property.
    $element['css_settings']['bg_image_z_index'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Z Index'),
      '#description' => $this->t(
            'The z-index property specifies the stack order of an element. An element with greater stack order is
                      always in front of an element with a lower stack order. Note: z-index only works on positioned
                      elements (position:absolute, position:relative, or position:fixed)'
      ),
      '#default_value' => $settings['css_settings']['bg_image_z_index'],
    ];

    // The selector for the background property.
    $element['css_settings']['bg_image_color'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Color'),
      '#description' => $this->t(
            'The background color formatted as any valid css color format (e.g. hex, rgb, text, hsl)
                      [<a href="@url">css property: background-color</a>]. One per line. If the field is a multivalue
                      field, the first line will be applied to the first value, the second to the second value...
                      and so on.',
            ['@url' => 'https://developer.mozilla.org/en-US/docs/Web/CSS/linear-gradient']
      ),
      '#default_value' => $settings['css_settings']['bg_image_color'],
    ];

    // The selector for the background property.
    $element['css_settings']['bg_image_x'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Horizontal Alignment'),
      '#description' => $this->t(
            'The horizontal alignment of the background image formatted as any valid css alignment.
                      [<a href="http://www.w3schools.com/css/pr_background-position.asp">
                      css property: background-position
                      </a>]'
      ),
      '#default_value' => $settings['css_settings']['bg_image_x'],
    ];
    // The selector for the background property.
    $element['css_settings']['bg_image_y'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vertical Alignment'),
      '#description' => $this->t(
            'The vertical alignment of the background image formatted as any valid css alignment.
                      [<a href="http://www.w3schools.com/css/pr_background-position.asp">
                      css property: background-position
                      </a>]'
      ),
      '#default_value' => $settings['css_settings']['bg_image_y'],
    ];
    // The selector for the background property.
    $element['css_settings']['bg_image_attachment'] = [
      '#type' => 'radios',
      '#title' => $this->t('Background Attachment'),
      '#description' => $this->t(
            'The attachment setting for the background image.
                      [<a href="http://www.w3schools.com/css/pr_background-attachment.asp">
                      css property: background-attachment
                      </a>]'
      ),
      '#options' => [
        FALSE => $this->t('Ignore'),
        'scroll' => 'Scroll',
        'fixed' => 'Fixed',
      ],
      '#default_value' => $settings['css_settings']['bg_image_attachment'],
    ];
    // The background-repeat property.
    $element['css_settings']['bg_image_repeat'] = [
      '#type' => 'radios',
      '#title' => $this->t('Background Repeat'),
      '#description' => $this->t(
            'Define the repeat settings for the background image.
                      [<a href="http://www.w3schools.com/css/pr_background-repeat.asp">
                      css property: background-repeat
                      </a>]'
      ),
      '#options' => [
        FALSE => $this->t('Ignore'),
        'no-repeat' => $this->t('No Repeat'),
        'repeat' => $this->t('Tiled (repeat)'),
        'repeat-x' => $this->t('Repeat Horizontally (repeat-x)'),
        'repeat-y' => $this->t('Repeat Vertically (repeat-y)'),
      ],
      '#default_value' => $settings['css_settings']['bg_image_repeat'],
    ];
    // The background-size property.
    $element['css_settings']['bg_image_background_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Background Size'),
      '#description' => $this->t(
            'The size of the background (NOTE: CSS3 only. Useful for responsive designs)
                      [<a href="http://www.w3schools.com/cssref/css3_pr_background-size.asp">
                      css property: background-size
                      </a>]'
      ),
      '#default_value' => $settings['css_settings']['bg_image_background_size'],
    ];
    // background-size:cover suppor for IE8.
    $element['css_settings']['bg_image_background_size_ie8'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add background-size:cover support for ie8'),
      '#description' => $this->t(
            'The background-size css property is only supported on browsers that support CSS3.
                      However, there is a workaround for IE using Internet Explorer\'s built-in filters
                      (http://msdn.microsoft.com/en-us/library/ms532969%28v=vs.85%29.aspx).
                      Check this box to add the filters to the css. Sometimes it works well, sometimes it doesn\'t.
                      Use at your own risk'
      ),
      '#default_value' => $settings['css_settings']['bg_image_background_size_ie8'],
    ];
    // Add gradient to background-image.
    $element['css_settings']['bg_image_gradient'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gradient'),
      '#description' => $this->t(
          'Apply this background image gradient css.
                  Example: linear-gradient(red, yellow)
                  [<a href="https://www.w3schools.com/css/css3_gradients.asp">Read about gradients</a>]'
      ),
      '#default_value' => $settings['css_settings']['bg_image_gradient'],
    ];
    // The media query specifics.
    $element['css_settings']['bg_image_media_query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Media Query'),
      '#description' => $this->t(
            'Apply this background image css using a media query. CSS3 Only. Useful for responsive designs.
                      Example: only screen and (min-width:481px) and (max-width:768px)
                      [<a href="http://www.w3.org/TR/css3-mediaqueries/">Read about media queries</a>]'
      ),
      '#default_value' => $settings['css_settings']['bg_image_media_query'],
    ];
    $element['css_settings']['bg_image_important'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add "!important" to the background property.'),
      '#description' => $this->t(
            'This can be helpful to override any existing background image or color properties added by the theme.'
      ),
      '#default_value' => $settings['css_settings']['bg_image_important'],
    ];
    $element['css_settings']['bg_image_path_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Path Format'),
      '#options' => [
        'absolute' => $this->t('Absolute'),
        'relative' => $this->t('Relative'),
      ],
      '#description' => $this->t(
          'Defaults to absolute URLs, however relative URLs maybe solve issues with mixed content errors on websites being served on HTTPS.'
      ),
      '#default_value' => $settings['css_settings']['bg_image_path_format'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $settings = $this->getSettings();

    $image_styles = image_style_options(FALSE);
    unset($image_styles['']);
    if (isset($settings['css_settings']['bg_image_selector'])) {
      $summary[] = $this->t(
        'CSS Selector: @selector',
        [
          '@selector' => $settings['css_settings']['bg_image_selector'],
        ]
      );
    }
    else {
      $summary[] = $this->t('No selector');
    }

    if (isset($image_styles[$settings['image_style']])) {
      $summary[] = $this->t(
        'URL for image style: @style',
        [
          '@style' => $image_styles[$settings['image_style']],
        ]
      );
    }
    else {
      $summary[] = $this->t('Original image style');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $settings = $this->getSettings();
    $css_settings = $settings['css_settings'];
    // Replace views tokens.
    $selector = $css_settings['bg_image_selector'];
    $token_start = strpos($selector, '{{');
    if ($token_start !== FALSE) {
      $token_length = strpos($selector, '}}') - strpos($selector, '{{') + 2;
      $token = substr($selector, strpos($selector, '{{'), $token_length);
      $cleaned_token = trim(str_replace(['{{', '}}'], '', $token));
      $entity = $items->getEntity();
      if ($entity->$cleaned_token && $entity->$cleaned_token->value) {
        $selector = str_replace($token, $entity->$cleaned_token->value, $selector);
        $css_settings['bg_image_selector'] = $selector;
      }
    }
    $image_style = $settings['image_style'] ? $settings['image_style'] : NULL;
    $path_format = $css_settings['bg_image_path_format'];
    $selectors = explode(PHP_EOL, trim($css_settings['bg_image_selector']));
    $colors = explode(PHP_EOL, trim($css_settings['bg_image_color']));

    // Filter out empty selectors.
    $selectors = array_map(function ($value) {
      return trim($value, ',');
    }, $selectors);

    // Filter out empty colors.
    $colors = array_filter(array_map('trim', $colors));

    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $elements;
    }

    // Prepare token data in bg image css selector.
    $token_data = [
      'user' => \Drupal::currentUser(),
      $items->getEntity()->getEntityTypeId() => $items->getEntity(),
    ];
    foreach ($selectors as &$selector) {
      $selector = \Drupal::token()->replace($selector, $token_data);
    }

    // Need an empty element so views renderer will see something to render.
    $elements[0] = [];

    foreach ($files as $delta => $file) {
      $css_settings['bg_image_selector'] = $selectors[$delta % count($selectors)];
      if ($colors) {
        $css_settings['bg_image_color'] = $colors[$delta % count($colors)];
      }

      if ($image_style) {
        $style = $this->imageStyleStorage->load($image_style);
        $file_url = $style->buildUrl($file->getFileUri());
      }
      else {
        $file_url = file_create_url($file->getFileUri());
      }

      $image_url = $path_format === 'absolute' ? $file_url : file_url_transform_relative($file_url);

      $css = $this->getBackgroundImageCss($image_url, $css_settings);

      // Define unique key to prevent collisions when displaying multiple
      // background images on the same page.
      $html_head_key = 'bg_image_formatter_css__s' . sha1(
        implode('__', [
          $items->getEntity()->uuid(),
          $items->getName(),
          $settings['image_style'],
          $delta,
        ])
      );

      $style_element = [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#attributes' => [
          'media' => $css['media'],
        ],
        '#value' => Markup::create($css['style']),
      ];

      if ($this->isAjax() || $this->request->isXmlHttpRequest()) {
        $elements['#attached']['drupalSettings']['bg_image_formatter_css'][$html_head_key] =
                    $this->renderer->renderPlain($style_element);
      }
      else {
        $elements['#attached']['html_head'][] = [$style_element, $html_head_key];
      }
    }

    return $elements;
  }

  /**
   * Merges default settings values into $settings.
   */
  protected function mergeDefaults() {
    $this->settings = ArrayUtils::merge(self::defaultSettings(), $this->settings);
    $this->defaultSettingsMerged = TRUE;
  }

}
