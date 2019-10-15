<?php

namespace Drupal\stockinfo;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Render\FormattableMarkup;
use GuzzleHttp\Exception\GuzzleException;
//use Drupal\Core\Cache\CacheBackendInterface;

class StockInfoClient {

  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * @var string $baseUri
   *   Hostname/IP of the API.
   *   Example: https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=MSFT&apikey=demo
   */
  protected $baseUri = 'https://www.alphavantage.co/';

  /**
   * @var string $path
   *   Endpoint path that follows $clientBaseUri.
   *   Example: https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=MSFT&apikey=demo
   */
  protected $path = 'query';

  /**
   * @var string $query
   *   Endpoint param.
   *   Example: https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=MSFT&apikey=demo
   */
  protected $function = 'TIME_SERIES_DAILY';

  /**
   * @var string $symbol
   *   Stock symbol.
   *   Example: https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=MSFT&apikey=demo
   */
  protected $symbol = 'MSFT';

  /**
   * @var string $apikey
   *   Endpoint parameter.
   *   Example: https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=MSFT&apikey=demo
   */
  protected $apikey = '7NA2G4MADSX6RRWU'; // @TODO Extract this temporary API key so it can be set in `settings.php`

  /**
   * @var int $refreshTtl
   *   Refresh TTL (in seconds).
   */
  protected $refreshTtl = 3600;

  /**
   * StockInfoClient constructor.
   *
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory
   */
  public function __construct($http_client_factory) {
    try {// Load data from 'demo' endpoint:
      $this->client = $http_client_factory->fromOptions([
        'base_uri' => $this->baseUri,
        'timeout' => 30,
        //      'headers' => [
        //        'User-Agent' => 'Drupal/' . \Drupal::VERSION . ' (+https://www.drupal.org/) ' . \GuzzleHttp\default_user_agent(),
        //      ],
      ]);
    } catch (\Exception $error) {
      watchdog_exception('stockinfo', $error);
    }
  }

  /**
   * Get stock data for a specific symbol.
   *
   * @param string $symbol
   *
   * @return array|false|object
   */
  public function data($symbol) {
    if (($cache = \Drupal::cache()->get('stockinfo:data:' . \Drupal::languageManager()->getCurrentLanguage()->getId())) && !empty($cache)) {
      return $cache->data;
    }

    try {
      $response = $this->client->get($this->path, [
        'query' => [
          'function' => $this->function,
          'symbol' => $symbol,
          'apikey' => $this->apikey,
        ],
      ]);
    } catch (GuzzleException $error) {
      // Get the original response
      $response = $error->getResponse();
      // Get the info returned from the remote server.
      $response_info = $response->getBody()->getContents();
      // Using FormattableMarkup allows for the use of <pre/> tags, giving a more readable log item.
      $message = new FormattableMarkup('API connection error. Error details are as follows:<pre>@response</pre>', ['@response' => print_r(json_decode($response_info), TRUE)]);
      // Log the error
      watchdog_exception('stockinfo', $error, $message);
    } catch (\Exception $error) {
      // Log the error.
      watchdog_exception('stockinfo', $error, t('An unknown error occurred while trying to connect to the remote API. This is not a Guzzle error, nor an error in the remote API, rather a generic local error occurred. The reported error was @error', ['@error' => $error->getMessage()]));
    }

    // The DATA!
    $json_data = Json::decode($response->getBody());

    if (!empty($json_data)) {
      // Find first record of a certain child
      $target_data_child = 'Time Series (Daily)';

      if (isset($json_data[$target_data_child])) {
        $data = new \stdClass();
        $stock_data = array_shift($json_data[$target_data_child]);
        // Prep return data
        $data->name = $symbol;
        $data->price = number_format($stock_data['4. close'], 2);
        $data->change = '+0.445';
        $data->volume = number_format($stock_data['5. volume']);
        // Cache data
        if ($data !== NULL) {
          // Cache the data for speedier lookup
          \Drupal::cache()->set('stockinfo:data:' . \Drupal::languageManager()->getCurrentLanguage()->getId(), $data, REQUEST_TIME + ($this->refreshTtl));
        }
        return $data;
      }

    }
    \Drupal::logger('stockinfo')->warning('Could not find the following key in JSON data: ' . $target_data_child);
    return FALSE;
  }

}
