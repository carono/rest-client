<?php

namespace carono\rest;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\ResponseInterface;
use function GuzzleHttp\Psr7\build_query;

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

    const TYPE_RAW = 'raw';
    const TYPE_JSON = 'json';
    const TYPE_XML = 'xml';
    const TYPE_FORM = 'form';
    const TYPE_MULTIPART = 'multipart';

    protected $protocol = 'https';
    protected $url = '';
    protected $type = 'json';
    protected $output_type;
    protected $_guzzleOptions = [];
    protected $_custom_guzzle_options = [];
    protected $_guzzle;
    protected $_errors = [];

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

    /**
     * @param $array
     */
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

    /**
     * @param $a
     * @param $b
     * @return array|mixed
     */
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
                } elseif (\is_array($v) && isset($res[$k]) && \is_array($res[$k])) {
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
        }

        return $this->protocol . '://' . $this->url . ($url ? '/' . $url : '');
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
     * @param array $options
     * @return string|\stdClass|\SimpleXMLElement
     */
    public function getContent($urlRequest, $data = [], $options = [])
    {
        $method = $options['method'] ?? $this->method;
        $postDataInBody = $options['postDataInBody'] ?? $this->postDataInBody;
        $type = $options['type'] ?? $this->type;

        $requestOptions = [];
        $this->guzzleOptions();
        $url = $this->buildUrl($urlRequest);
        $client = $this->getGuzzle();
        $data = $this->prepareData($data);
        if ($method == 'GET') {
            $url = $url . (strpos($url, '?') ? '&' : '?') . build_query($data);
        } elseif ($postDataInBody) {
            $requestOptions = ['body' => $data];
        } elseif ($type === self::TYPE_MULTIPART) {
            $requestOptions = ['multipart' => $data];
        } else {
            $requestOptions = ['form_params' => $data];
        }
        $request = $client->request($method, $url, self::merge($requestOptions, $this->_guzzleOptions));
        $this->request = $request;
        return $this->unSerialize($request->getBody()->getContents(), $options);
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
     * @param array $options
     * @return mixed|null|\SimpleXMLElement
     */
    public function unSerialize($data, $options = [])
    {
        $outputType = $options['output_type'] ?? $this->output_type;
        $type = $options['type'] ?? $this->type;
        $unSerializeType = $outputType ?: $type;

        switch ($unSerializeType) {
            case self::TYPE_JSON:
                return $this->unSerializeJson($data);
            case self::TYPE_XML:
                return $this->unSerializeXml($data);
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
     * @param array $data
     * @param array $options
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
        if ($this->method === 'GET') {
            return $data;
        }
        switch ($this->type) {
            case self::TYPE_JSON:
                $data = \GuzzleHttp\json_encode($data);
                break;
            case self::TYPE_XML:
                throw new \Exception('Xml type is not implemented yet');
            case self::TYPE_FORM:
                break;
            case self::TYPE_MULTIPART:
                $prepared = [];
                foreach ($data as $param => $value) {
                    if (\is_array($value)) {
                        foreach ($value as $item) {
                            $prepared[] = ['name' => $param . '[]', 'contents' => $item];
                        }
                    } else {
                        $prepared[] = ['name' => $param, 'contents' => $value];
                    }
                }
                $data = $prepared;
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
            case self::TYPE_FORM:
                $options['headers']['content-type'] = 'application/x-www-form-urlencoded';
                break;
            case self::TYPE_MULTIPART:
                break;
            default:
                throw new \Exception('Type is not supported');
        }
        if (($this->login || $this->password) && $this->useAuth) {
            $options['auth'] = [$this->login, $this->password];
        }
        $this->_guzzleOptions = self::merge($options, $this->_custom_guzzle_options, $this->customGuzzleOptions());
    }

    /**
     * @param $value
     */
    public function setUrl($value)
    {
        $this->url = $value;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param $value
     */
    public function setType($value)
    {
        $this->type = $value;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}