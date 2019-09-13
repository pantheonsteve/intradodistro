<?php

namespace Drupal\cms_content_sync\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\cms_content_sync\Form\PoolForm;

/**
 * Provides a listing of Pool.
 */
class PoolListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['id'] = $this->t('Machine name');
    $header['site_id'] = $this->t('Site identifier');
    $header['backend_url'] = $this->t('Sync Core URL');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /**
     * @var \Drupal\cms_content_sync\Entity\Pool $entity
     */
    $row['name'] = $entity->label();
    $row['id'] = $entity->id();
    $row['site_id'] = $entity->getSiteId();
    $row['backend_url'] = $entity->getBackendUrl();

    if (strlen($entity->getSiteId()) > PoolForm::siteIdMaxLength) {
      $messenger = \Drupal::messenger();
      $warning = 'The site id of pool ' . $entity->id() . ' is having more then ' . PoolForm::siteIdMaxLength . ' characters. This is not allowed due to backend limitations and will result in an exception when it is trying to be exported.';
      $messenger->addWarning(t($warning));
    }

    // You probably want a few more properties here...
    return $row + parent::buildRow($entity);
  }

}
