<?php

namespace carono\rest\client;

class Client
{
    public $login;
    public $password;
    public $proxy;
    public $method = 'GET';

    const TYPE_JSON = 'json';
    const TYPE_XML = 'xml';


    protected $protocol = 'https';
    protected $domain = '';
    protected $type = 'json';
    protected $_guzzleOptions = [];
    protected $_guzzle;

    /**
     * Client constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->_guzzle = new \GuzzleHttp\Client();
        foreach ($config as $prop => $value) {
            $this->$prop = $value;
        }
        $this->guzzleOptions();
    }

    /**
     * @param $url
     * @return string
     */
    protected function buildUrl($url)
    {
        return $this->protocol . '://' . $this->domain . '/' . $url;
    }

    /**
     * @param $urlRequest
     * @param array $data
     * @return string
     */
    public function getContent($urlRequest, $data = [])
    {
        $url = $this->buildUrl($urlRequest);
        $client = new \GuzzleHttp\Client();
        $options = [
            'body' => $this->prepareData($data)
        ];
        $request = $client->request($this->method, $url, array_merge($options, $this->_guzzleOptions));
        return $request->getBody()->getContents();
    }

    /**
     * @param $data
     * @return string
     * @throws \Exception
     */
    protected function prepareData($data)
    {
        switch ($this->type) {
            case self::TYPE_JSON:
                $data = \GuzzleHttp\json_encode($data);
                break;
            case self::TYPE_XML:
                throw new \Exception('Xml type is not implemented yet');
                break;
            default:
                throw new \Exception('Type is not supported');
        }
        return $data;
    }

    /**
     * @throws \Exception
     */
    public function guzzleOptions()
    {
        $options = [
            'headers' => []
        ];
        if ($this->proxy) {
            $options['proxy'] = $this->proxy;
        }
        switch ($this->type) {
            case self::TYPE_JSON:
                $options['headers']['content-type'] = 'application/json';
                break;
            case self::TYPE_XML:
                $options['headers']['content-type'] = 'application/xml';
                break;
            default:
                throw new \Exception('Type is not supported');
        }
        if ($this->login || $this->password) {
            $options['auth'] = [$this->login, $this->password];
        }
        $this->_guzzleOptions = $options;
    }
}