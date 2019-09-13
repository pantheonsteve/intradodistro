<?php

namespace Drupal\cms_content_sync\SyncCore;

/**
 * Class ListResult
 * Helper class to retrieve individual pages of a ListQuery or all items at once
 * across all pages.
 *
 * @package Drupal\cms_content_sync\SyncCore
 */
class ListResult extends Result {

  /**
   * @var int
   *    Cache how many pages can be returned eventually.
   */
  protected $numberOfPages = NULL;

  /**
   * @var int
   *    Cache how many items will be provided in total.
   */
  protected $totalNumberOfItems = NULL;

  /**
   * @var string
   *    A unique session identifier to make sure the results are consistent.
   *    The session is returned at the first request and then re-used for all
   *    sub-sequent requests. The Sync Core will store which entities should be
   *    returned at the following requests when making the first request and
   *    will ignore any changes (updates, creations, deletions) performed
   *    between requests.
   */
  protected $session = NULL;

  /**
   * Get the items if an individual page.
   *
   * @param int $page
   *   The page to retrieve.
   *
   * @return array The entities as an array.
   *
   * @throws \Exception
   */
  public function getPage($page = 1) {
    /**
     * @var \Drupal\cms_content_sync\SyncCore\ListQuery $query
     */
    $query = $this->query;

    $query->setPage($page);

    $client = $this->query->getStorage()->getPool()->getClient();

    $data = $client->request($this->query);

    if ($this->numberOfPages === NULL) {
      $this->numberOfPages = $data['number_of_pages'];
    }
    if ($this->totalNumberOfItems === NULL) {
      $this->totalNumberOfItems = $data['total_number_of_items'];
    }
    if ($this->session === NULL) {
      $this->session = $data['session'];
    }

    return $data['items'];
  }

  /**
   * Get all remote entities at once.
   *
   * @return array All entities for the given Query as an array.
   *
   * @throws \Exception
   */
  public function getAll() {
    /**
     * @var \Drupal\cms_content_sync\SyncCore\ListQuery $query
     */
    $query = $this->query;

    $page = 1;
    $result = [];

    $items_per_page = $query->getItemsPerPage();
    $query->setItemsPerPage(Client::MAX_ITEMS_PER_PAGE);

    do {
      $items = $this->getPage($page++);
      $result = array_merge($result, $items);
    } while ($page <= $this->numberOfPages);

    $query->setItemsPerPage($items_per_page);

    return $result;
  }

}
