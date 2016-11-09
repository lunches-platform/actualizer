<?php


namespace Lunches\ETL\Service;

use GuzzleHttp\Client;

abstract class AbstractService
{
    /** @var Client */
    protected $client;
    /** @var  string */
    protected $accessToken;
    /** @var  string */
    protected $company;
    /** @var string  */
    protected $apiDateFormat = 'Y-m-d';

    public function __construct(Client $client, $accessToken, $company)
    {
        $this->client = $client;
        $this->accessToken = $accessToken;
        $this->company = $company;
    }

    /**
     * @return string
     */
    public function company()
    {
        return $this->company;
    }

    protected function makeRequest($method, $uri, array $params = [])
    {
        $params['query']['accessToken'] = $this->accessToken;
        $response = $this->client->request($method, $uri, $params);
        $body = (string) $response->getBody();
        return (array) json_decode($body, true);
    }
}