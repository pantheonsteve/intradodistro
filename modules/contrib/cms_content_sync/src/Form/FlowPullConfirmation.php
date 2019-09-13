<?php

namespace Drupal\cms_content_sync\Form;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a pool pull all confirmation page.
 *
 * @internal
 */
class FlowPullConfirmation extends ConfirmFormBase {

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The nodes to push.
   *
   * @var array
   */
  protected $nodes;

  /**
   * The flow configuration.
   */
  protected $flow;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   *
   * The flow storage
   */
  protected $flow_storage;

  /**
   * The content sync flow machine name.
   *
   * @var string
   */
  protected $cms_content_sync_flow;

  /**
   * Constructs a DeleteMultiple form object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Entity\EntityManagerInterface $manager
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, EntityManagerInterface $manager) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->flow_storage = $manager->getStorage('cms_content_sync_flow');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $cms_content_sync_flow = NULL) {
    $this->cms_content_sync_flow = $cms_content_sync_flow;
    $this->flow = $this->flow_storage->load($this->cms_content_sync_flow);

    $form['pull_mode'] = [
      '#type' => 'radios',
      '#options' => [
        'new_entities' => $this->t('Only add new entities'),
        'all_entities' => $this->t('Force update of all entities'),
      ],
      '#default_value' => 'new_entities',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pool_pull_confirmation';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return 'Do you really want to pull all entities from this flow?';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Depending on the amount of entities this could take a while.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('system.admin_content');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Pull');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.cms_content_sync_flow.pull', [
      'cms_content_sync_flow' => $this->flow->id(),
      'pull_mode' => $form_state->getValue('pull_mode'),
    ]);
  }

}
