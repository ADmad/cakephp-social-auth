<?php
/**
 * ADmad\SocialAuth plugin.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */

namespace ADmad\SocialAuth\Http;

use Cake\Http\Client as HttpClient;
use InvalidArgumentException;
use SocialConnect\Common\Http\Client\Client as SocialConnectClient;
use SocialConnect\Common\Http\Response;

class Client extends SocialConnectClient
{
    /**
     * Http Client instance.
     *
     * @var \Cake\Network\Http\Client
     */
    protected $_client;

    /**
     * Constructor.
     *
     * @param \Cake\Network\Http\Client $client Http Client instance.
     */
    public function __construct(HttpClient $client = null)
    {
        $this->_client = is_null($client) ? new HttpClient() : $client;
    }

    /**
     * Request specify url.
     *
     * @param string $url Request URL.
     * @param array $parameters Request parameters.
     * @param string $method Request method.
     * @param array $headers Request headers.
     * @param array $options Unused.
     *
     * @return \SocialConnect\Common\Http\Response
     */
    public function request(
        $url,
        array $parameters = [],
        $method = SocialConnectClient::GET,
        array $headers = [],
        array $options = []
    ) {
        switch ($method) {
            case SocialConnectClient::GET:
                $response = $this->_client->get(
                    $url,
                    $parameters,
                    ['headers' => $headers]
                );
                break;
            case SocialConnectClient::POST:
                $response = $this->_client->post(
                    $url,
                    $parameters,
                    ['headers' => $headers]
                );
                break;
            case SocialConnectClient::PUT:
                $response = $this->_client->put(
                    $url,
                    $parameters,
                    ['headers' => $headers]
                );
                break;
            case SocialConnectClient::DELETE:
                $response = $this->_client->delete(
                    $url,
                    $parameters,
                    ['headers' => $headers]
                );
                break;
            default:
                throw new InvalidArgumentException("Method {$method} is not supported");
        }

        return new Response(
            $response->getStatusCode(),
            (string)$response->getBody(),
            $response->getHeaders()
        );
    }
}
