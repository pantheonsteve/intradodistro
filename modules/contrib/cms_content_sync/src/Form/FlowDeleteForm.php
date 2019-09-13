<?php

namespace Drupal\cms_content_sync\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\cms_content_sync\Entity\EntityStatus;
use Drupal\Core\Config\ConfigFactory;

/**
 * Builds the form to delete an Flow.
 */
class FlowDeleteForm extends EntityConfirmFormBase {

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config factory to load configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * FlowDeleteForm constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   */
  public function __construct(MessengerInterface $messenger, ConfigFactory $config_factory) {
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name? This will also delete all synchronisation status entities!', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.cms_content_sync_flow.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Delete config related status entities.
    $entity_status = \Drupal::entityTypeManager()->getStorage('cms_content_sync_entity_status')
      ->loadByProperties(['flow' => $this->getEntity()->id()]);

    foreach ($entity_status as $status) {
      $entity = EntityStatus::load($status->id());
      $entity->delete();
    }

    $links = \Drupal::entityTypeManager()->getStorage('menu_link_content')
      ->loadByProperties(['link__uri' => 'internal:/admin/content/cms_content_synchronization/' . $this->entity->id()]);

    if ($link = reset($links)) {
      $link->delete();
      menu_cache_clear_all();
    }

    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('cms_content_sync_developer')) {
      $config_factory = $this->configFactory;
      $developer_config = $config_factory->getEditable('cms_content_sync.developer');
      $mismatching_versions = $developer_config->get('version_mismatch');
      if (!empty($mismatching_versions)) {
        unset($mismatching_versions[$this->entity->id()]);
        $developer_config->set('version_mismatch', $mismatching_versions)->save();
      }
    }

    $this->entity->delete();
    $this->messenger->addMessage($this->t('Flow %label has been deleted.', ['%label' => $this->entity->label()]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
