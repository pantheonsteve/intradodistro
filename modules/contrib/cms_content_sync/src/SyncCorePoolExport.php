<?php

namespace Drupal\cms_content_sync;

use Drupal\cms_content_sync\Entity\Pool;
use Drupal\cms_content_sync\Plugin\rest\resource\EntityResource;
use Drupal\cms_content_sync\SyncCore\Storage\ApiStorage;
use Drupal\cms_content_sync\SyncCore\Storage\PreviewEntityStorage;

/**
 *
 */
class SyncCorePoolExport extends SyncCoreExport {

  /**
   * @var \Drupal\cms_content_sync\Entity\Pool
   */
  protected $pool;

  /**
   * SyncCorePoolExport constructor.
   *
   * @param \Drupal\cms_content_sync\Entity\Pool $pool
   *   The pool this exporter is used for.
   */
  public function __construct(Pool $pool) {
    parent::__construct();

    $this->pool = $pool;
  }

  /**
   * Check if another site has already connected to this pool with the same
   * site ID.
   */
  public function siteIdExists() {
    $url = $this->pool->getBackendUrl();

    $condition = [
      "operator" => "and",
      "conditions" => [
        [
          "operator" => "==",
          "values" => [
            [
              "source" => "data",
              "property" => "id",
            ],
            [
              "source" => "value",
              "value" => $this->pool->getSiteId(),
            ],
          ],
        ],
        [
          "operator" => "!=",
          "values" => [
            [
              "source" => "data",
              "property" => "base_url",
            ],
            [
              "source" => "value",
              "value" => EntityResource::getBaseUrl(),
            ],
          ],
        ],
        [
          "operator" => "!=",
          "values" => [
            [
              "source" => "data",
              "property" => "base_url",
            ],
            [
              "source" => "value",
              "value" => NULL,
            ],
          ],
        ],
      ],
    ];

    $condition = json_encode($condition);

    $url = sprintf('%s/api_unify-api_unify-instance-0_1?condition=%s',
      $url,
      $condition
    );

    $response = $this->client->get($url);
    $body = json_decode($response->getBody(), TRUE);

    return $body['total_number_of_items'] > 0;
  }

  /**
   * @inheritdoc
   */
  public function prepareBatch($subsequent = FALSE) {
    $url = $this->pool->getBackendUrl();

    $base_url = EntityResource::getBaseUrl();
    if (empty($base_url)) {
      throw new \Exception('Please provide a base_url via settings or drush command.');
    }

    $this->remove(TRUE);

    $operations = [];

    // Skip creation of base entities if they are already created.
    if (!$subsequent) {
      // Create "drupal" API entity.
      $operations[] = [$url . '/api_unify-api_unify-api-0_1', [
        'json' => [
          'id' => 'drupal-' . ApiStorage::CUSTOM_API_VERSION,
          'name' => 'drupal',
          'version' => ApiStorage::CUSTOM_API_VERSION,
        ],
      ],
      ];

      // Create the preview connection entity.
      $operations[] = [$url . '/api_unify-api_unify-connection-0_1', [
        'json' => [
          'id' => PreviewEntityStorage::ID,
          'name' => 'Drupal preview connection',
          'hash' => PreviewEntityStorage::EXTERNAL_PREVIEW_PATH,
          'usage' => 'EXTERNAL',
          'status' => 'READY',
          'entity_type_id' => PreviewEntityStorage::PREVIEW_ENTITY_ID,
          'options' => [
            'crud' => [
              'read_list' => [],
            ],
            'static_values' => [],
          ],
        ],
      ],
      ];
    }

    // Create the child entity.
    $operations[] = [$url . '/api_unify-api_unify-api-0_1', [
      'json' => [
        'id' => $this->pool->id . '-' . ApiStorage::CUSTOM_API_VERSION,
        'name' => $this->pool->label(),
        'version' => ApiStorage::CUSTOM_API_VERSION,
        'parent_id' => 'drupal-' . ApiStorage::CUSTOM_API_VERSION,
      ],
    ],
    ];

    // Create the instance entity.
    $operations[] = [$url . '/api_unify-api_unify-instance-0_1', [
      'json' => [
        'id' => $this->pool->getSiteId(),
        'base_url' => $base_url,
        'version' => static::getVersion(),
        'api_id' => $this->pool->id . '-' . ApiStorage::CUSTOM_API_VERSION,
      ],
    ],
    ];

    return $operations;
  }

  /**
   *
   */
  public function remove($removedOnly = TRUE) {
    return TRUE;
  }

}
