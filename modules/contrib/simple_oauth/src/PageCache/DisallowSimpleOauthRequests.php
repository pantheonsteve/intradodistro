<?php

namespace Drupal\simple_oauth\PageCache;

use Symfony\Component\HttpFoundation\Request;

/**
 * Do not serve a page from cache if OAuth2 authentication is applicable.
 *
 * @internal
 */
class DisallowSimpleOauthRequests implements SimpleOauthRequestPolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function isOauth2Request(Request $request) {
    // Check the header. See: http://tools.ietf.org/html/rfc6750#section-2.1
    return strpos(trim($request->headers->get('Authorization', '', TRUE)), 'Bearer ') !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    return $this->isOauth2Request($request) ? static::DENY : NULL;
  }

}
