<?php

namespace carono\rest;

use function GuzzleHttp\Psr7\build_query;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Client
{
    public $login;
    public $password;
    public $proxy;
    public $method = 'GET';
    public $postDataInBody = false;
    public $useAuth = true;

    /**
     * @var ResponseInterface
     */
    public $request;

    const TYPE_JSON = 'json';
    const TYPE_XML = 'xml';
    const TYPE_FORM = 'form';

    protected $protocol = 'https';
    protected $url = '';
    protected $type = 'json';
    protected $output_type;
    protected $_guzzleOptions = [];
    protected $_custom_guzzle_options = [];
    protected $_guzzle;
    protected $_errors;

    /**
     * Client constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->_guzzle = new GuzzleClient();
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

    /**
     * @param bool $asString
     * @return string
     */
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

    public function setGuzzleOptions($array)
    {
        $this->_custom_guzzle_options = $array;
    }

    /**
     * @return array
     */
    protected function customGuzzleOptions()
    {
        return [];
    }

    protected static function merge($a, $b)
    {
        $args = func_get_args();
        $res = array_shift($args);
        while (!empty($args)) {
            $next = array_shift($args);
            foreach ($next as $k => $v) {
                if (is_int($k)) {
                    if (array_key_exists($k, $res)) {
                        $res[] = $v;
                    } else {
                        $res[$k] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = self::merge($res[$k], $v);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
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
     * @return GuzzleClient
     */
    public function getGuzzle()
    {
        return $this->_guzzle;
    }

    /**
     * @param $urlRequest
     * @param array $data
     * @return string|\stdClass|\SimpleXMLElement
     */
    public function getContent($urlRequest, $data = [])
    {
        $options = [];
        $this->guzzleOptions();
        $url = $this->buildUrl($urlRequest);
        $client = $this->getGuzzle();
        $data = $this->prepareData($data);
        if ($this->method == 'GET') {
            $url = $url . (strpos($url, '?') ? '&' : '?') . build_query($data);
        } elseif ($this->postDataInBody) {
            $options = ['body' => $data];
        } else {
            $options = ['form_params' => $data];
        }
        $request = $client->request($this->method, $url, self::merge($options, $this->_guzzleOptions));
        $this->request = $request;
        return $this->unSerialize($request->getBody()->getContents());
    }

    /**
     * @param $data
     * @return mixed
     */
    protected function unSerializeJson($data)
    {
        return \GuzzleHttp\json_decode($data);
    }

    /**
     * @param $data
     * @return \SimpleXMLElement
     */
    protected function unSerializeXml($data)
    {
        return simplexml_load_string($data);
    }

    /**
     * @param $data
     * @return mixed|null|\SimpleXMLElement
     */
    public function unSerialize($data)
    {
        $type = $this->output_type ? $this->output_type : $this->type;

        switch ($type) {
            case self::TYPE_JSON:
                return $this->unSerializeJson($data);
                break;
            case self::TYPE_XML:
                return $this->unSerializeXml($data);
                break;
        }
        return $data;
    }

    /**
     * @param $param
     * @param $message
     */
    public function addError($param, $message)
    {
        $this->_errors[$param][] = $message;
    }

    /**
     * @param array $data
     * @return array
     */
    protected function beforePrepareData(array $data)
    {
        return $data;
    }

    /**
     * @param $data
     * @return string|array
     * @throws \Exception
     */
    protected function prepareData(array $data)
    {
        $data = $this->beforePrepareData($data);
        $data = $this->filter($data);

        if (!$this->validate($data)) {
            throw new \Exception($this->getError());
        }
        if ($this->method == 'GET') {
            return $data;
        }
        switch ($this->type) {
            case self::TYPE_JSON:
                $data = \GuzzleHttp\json_encode($data);
                break;
            case self::TYPE_XML:
                throw new \Exception('Xml type is not implemented yet');
                break;
            case self::TYPE_FORM:
                break;
            default:
                throw new \Exception('Type is not supported');
                break;
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
            case self::TYPE_FORM:
                $options['headers']['content-type'] = 'application/x-www-form-urlencoded';
                break;

            default:
                throw new \Exception('Type is not supported');
        }
        if (($this->login || $this->password) && $this->useAuth) {
            $options['auth'] = [$this->login, $this->password];
        }
        $this->_guzzleOptions = self::merge($options, $this->_custom_guzzle_options, $this->customGuzzleOptions());
    }
}