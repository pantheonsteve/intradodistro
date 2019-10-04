<?php

namespace Drupal\bootstrap_simple_carousel\Form;

use Drupal\bootstrap_simple_carousel\Service\CarouselService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ItemsForm.
 *
 * Table item list form.
 *
 * @package Drupal\bootstrap_simple_carousel\Form
 */
class ItemsForm extends FormBase {

  /**
   * The database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bootstrap_simple_carousel_items_form';
  }

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Constructs a Connection object.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $query = $this->connection->select('bootstrap_simple_carousel', 'u');
    $query->fields('u');
    $items = $query->execute()->fetchAll();

    $header = [
      'cio' => $this->t('Item id'),
      'image_id' => $this->t('Image'),
      'caption_title' => $this->t('Caption title'),
      'caption_text' => $this->t('Caption text'),
      'status' => $this->t('Status'),
      'edit' => $this->t('Edit'),
      'delete' => $this->t('Delete'),
    ];

    $output = [];
    if (!empty($items)) {
      foreach ($items as $item) {
        $imageParams = [
          'alt' => $item->image_alt,
          'title' => $item->image_title,
        ];

        $output[$item->cid] = [
          'cio' => $item->cid,
          'image_id' => CarouselService::getInstance()
            ->renderImageById($item->image_id, 'thumbnail', $imageParams),
          'caption_title' => $item->caption_title,
          'caption_text' => $item->caption_text,
          'status' => CarouselService::getInstance()->getStatuses()[(bool) $item->status],
          'edit' => CarouselService::getInstance()->renderLink(
            Url::fromRoute('bootstrap_simple_carousel.edit', ['id' => $item->cid]),
            $this->t('edit')
          ),
          'delete' => CarouselService::getInstance()->renderLink(
            Url::fromRoute('bootstrap_simple_carousel.delete', ['id' => $item->cid]),
            $this->t('delete')
          ),
        ];
      }
    }

    $form['toolbar'] = [
      '#type' => 'fieldset',
      '#title' => '',
    ];
    $form['toolbar']['add_link'] = [
      '#title' => $this->t('Add element'),
      '#type' => 'link',
      '#url' => Url::fromRoute('bootstrap_simple_carousel.add'),
      '#attributes' => [
        'class' => ['button button-action button--primary button--small'],
      ],
    ];
    $form['toolbar']['settings_link'] = [
      '#title' => $this->t('Settings'),
      '#type' => 'link',
      '#url' => Url::fromRoute('bootstrap_simple_carousel.admin_settings'),
      '#attributes' => [
        'class' => ['button button--primary button--small'],
      ],
    ];

    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $output,
      '#empty' => $this->t('No images found'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
  }

}
