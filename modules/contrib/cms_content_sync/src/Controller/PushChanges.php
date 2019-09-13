<?php

namespace Drupal\cms_content_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\SyncIntent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Push changes controller.
 */
class PushChanges extends ControllerBase {

  /**
   * Published entity to Sync Core.
   *
   * @param string $flow_id
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $entity_type
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *
   * @throws \Exception
   */
  public static function pushChanges($flow_id, $entity, $entity_type = '') {

    if (!$entity instanceof EntityInterface) {
      if ($entity_type == '') {
        throw new \Exception(t('If no entity object is given, the entity_type is required.'));
      }
      $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity);
      if (!$entity instanceof EntityInterface) {
        throw new \Exception(t('Entity could not be loaded.'));
      }
    }

    $flow = Flow::load($flow_id);
    if (!ExportIntent::exportEntityFromUi(
      $entity,
      ExportIntent::EXPORT_FORCED,
      SyncIntent::ACTION_UPDATE,
      $flow
    )) {
      $messenger = \Drupal::messenger();
      $messenger->addWarning(t('%label has not been exported with CMS Content Sync: @reason', ['%label' => $entity->label(), '@reason' => ExportIntent::getNoExportReason($entity, TRUE)]));
    }

    return new RedirectResponse('/');
  }

  /**
   * Returns an read_list entities for Sync Core.
   *
   * TODO Should be removed when read_list will be allowed to omit.
   */
  public function pushChangesEntitiesList() {
    return new Response('[]');
  }

}
