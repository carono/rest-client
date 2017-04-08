<?php

namespace carono\rest;

use function GuzzleHttp\Psr7\build_query;

class Client
{
    public $login;
    public $password;
    public $proxy;
    public $method = 'GET';
    public $postDataInBody = false;

    const TYPE_JSON = 'json';
    const TYPE_XML = 'xml';

    protected $protocol = 'https';
    protected $url = '';
    protected $type = 'json';
    protected $_guzzleOptions = [];
    protected $_guzzle;
    protected $error;

    /**
     * Client constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->_guzzle = new \GuzzleHttp\Client();
        foreach ($config as $prop => $value) {
            $this->$prop = $value;
        }
        $this->init();
    }

    public function init()
    {
        $this->method = strtoupper($this->method);
        if (!in_array($this->method, ['GET', 'POST'])) {
            $this->method = 'GET';
        }
    }

    public function validate($data)
    {
        foreach ($data as $param => $value) {
            if (!$this->validateParam($param, $value)) {
                return false;
            }
        }
        return true;
    }

    public function validateParam($param, $value)
    {
        return true;
    }

    /**
     * @param $url
     * @return string
     */
    protected function buildUrl($url)
    {
        if (strpos($this->url, '://')) {
            return $this->url . ($url ? '/' . $url : '');
        } else {
            return $this->protocol . '://' . $this->url . ($url ? '/' . $url : '');
        }
    }

    /**
     * @param $urlRequest
     * @param array $data
     * @return string
     */
    public function getContent($urlRequest, $data = [])
    {
        $this->guzzleOptions();
        $url = $this->buildUrl($urlRequest);
        $client = new \GuzzleHttp\Client();
        if ($this->method == 'GET') {
            $url = (strpos($url, '?') ? '&' : '?') . build_query($data);
        } else {
            $options = [
                'body' => $this->prepareData($data)
            ];
        }
        $request = $client->request($this->method, $url, array_merge($options, $this->_guzzleOptions));
        return $this->unSerialize($request->getBody()->getContents());
    }

    /**
     * @param $data
     * @return mixed|null|\SimpleXMLElement
     */
    public function unSerialize($data)
    {
        if ($this->type == self::TYPE_JSON) {
            return \GuzzleHttp\json_decode($data);
        } elseif ($this->type == self::TYPE_XML) {
            return simplexml_load_string($data);
        }
        return null;
    }

    /**
     * @param $data
     * @return string
     * @throws \Exception
     */
    protected function prepareData($data)
    {
        if (!$this->validate($data)) {
            throw new \Exception($this->error);
        }
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