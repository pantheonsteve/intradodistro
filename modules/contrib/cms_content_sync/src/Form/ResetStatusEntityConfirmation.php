<?php

namespace Drupal\cms_content_sync\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Url;
use Drupal\cms_content_sync\Entity\Pool;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the confirmation form for the pool reset entity status action.
 *
 * @internal
 */
class ResetStatusEntityConfirmation extends ConfirmFormBase {

  /**
   * The pool the status entities should be reset for.
   *
   * @var string
   */
  protected $cms_content_sync_pool;

  /**
   * The pool storage.
   */
  protected $pool_storage;

  /**
   * The current pool.
   */
  protected $pool;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * Constructs a DeleteMultiple form object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $manager
   * @param \Drupal\Core\Messenger\Messenger $messenger
   */
  public function __construct(EntityTypeManager $manager, Messenger $messenger) {
    $this->pool_storage = $manager->getStorage('cms_content_sync_pool');
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $cms_content_sync_pool = NULL) {
    $this->cms_content_sync_pool = $cms_content_sync_pool;
    $this->pool = $this->pool_storage->load($this->cms_content_sync_pool);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cms_content_sync_pool_status_entity_reset_confirmation';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return 'Are you sure you want to reset the status entities for the Pool: "' . $this->pool->label() . '"?';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('By resetting the status of all entities, the date of the last import 
    and the date of the last export will be reset. The dates will no longer be displayed until 
    the content is imported or exported again and all entities will be exported / imported again at 
    the next synchronization regardless of whether they have changed or not.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.cms_content_sync_pool.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Confirm');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    Pool::resetStatusEntities($this->pool->id());

    $this->messenger->addMessage($this->t('The status entities for the Pool @pool have been reset successfully.', [
      '@pool' => $this->pool->label(),
    ]));

    $form_state->setRedirect('entity.cms_content_sync_pool.collection');
  }

}
