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
    protected $_errors;

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

    public function getError($asString = true)
    {
        if (!$asString) {
            return $this->_errors;
        }
        $error = ['Ошибка валидации параметров'];
        foreach ($this->_errors as $param => $errors) {
            $error[] = $param . ' - ' . join('; ', $errors);
        }
        return join("\n", $error);
    }

    public function validate(array $data)
    {
        foreach ($data as $param => $value) {
            $this->validateParam($param, $value);
        }
        return !count($this->_errors);
    }

    /**
     * @param array $data
     * @return array
     */
    public function filter(array $data)
    {
        $result = [];
        foreach ($data as $param => $value) {
            if (!is_null($filtered = $this->filterParam($param, $value))) {
                $result[$param] = $filtered;
            }
        }
        return $result;
    }

    /**
     * @param $param
     * @param $value
     * @return mixed
     */
    public function filterParam($param, $value)
    {
        return $value;
    }

    /**
     * @param $param
     * @param $value
     * @return bool
     */
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
        $options = [];
        $this->guzzleOptions();
        $url = $this->buildUrl($urlRequest);
        $client = new \GuzzleHttp\Client();
        $data = $this->prepareData($data);
        if ($this->method == 'GET') {
            $url = (strpos($url, '?') ? '&' : '?') . $data;
        } else {
            $options = [
                'body' => $data
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

    public function addError($param, $message)
    {
        $this->_errors[$param][] = $message;
    }

    /**
     * @param $data
     * @return string
     * @throws \Exception
     */
    protected function prepareData(array $data)
    {
        $data = $this->filter($data);
        if (!$this->validate($data)) {
            throw new \Exception($this->getError());
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