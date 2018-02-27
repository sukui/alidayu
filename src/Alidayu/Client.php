<?php
namespace Flc\Alidayu;

use Exception;
use Flc\Alidayu\Requests\IRequest;
use ZanPHP\Config\Config;
use ZanPHP\HttpClient\HttpClient;

/**
 * 阿里大于客户端
 *
 * @author Flc <2016-09-18 19:43:18>
 * @link   http://flc.ren
 */
class Client
{
    /**
     * API请求地址
     * @var string
     */
    protected $api_uri = 'http://gw.api.taobao.com/router/rest';

    /**
     * 沙箱请求地址
     * @var string
     */
    protected $api_sandbox_uri = 'http://gw.api.tbsandbox.com/router/rest'; 

    /**
     * 应用
     * @var \Flc\Alidayu\App
     */
    protected $app;

    /**
     * 签名规则
     * @var string
     */
    protected $sign_method = 'md5';

    /**
     * 响应格式。可选值：xml，json。
     * @var string
     */
    protected $format = 'json';

    /**
     * 静态配置
     * @var array
     */
    protected static $app_key;
    protected static $app_secret;
    protected static $sandbox=false;

    /**
     * @var static
     */
    private static $_instance = null;

    /**
     * @param array $config
     * @return static
     */
    final public static function instance($config=[])
    {
        return static::singleton($config);
    }

    final public static function singleton($config=[])
    {
        if (null === static::$_instance) {
            static::$_instance = new static($config);
        }
        return static::$_instance;
    }

    /**
     * @param $config
     * @return static
     */
    final public static function getInstance($config=[])
    {
        return static::singleton($config);
    }

    final public static function swap($instance)
    {
        static::$_instance = $instance;
    }

    /**
     * 初始化
     * @param array $config 阿里大于配置App类
     * @throws Exception
     */
    public function __construct($config=[])
    {

        if(empty($config)){
            $config = Config::get('sms');
        }

        // 判断配置
        if (empty($config['app_key']) || empty($config['app_secret'])) {
            throw new Exception("阿里大于配置信息：app_key或app_secret错误");
        }
        self::$app_key = $config['app_key'];
        self::$app_secret = $config['app_secret'];
        if(isset($config['sandbox'])){
            self::$sandbox = $config['sandbox'];
        }
    }

    /**
     * 发起请求数据
     * @param  \Flc\Alidayu\Requests\IRequest $request 请求类
     * @return false|object
     */
    public function execute(IRequest $request)
    {
        $method        = $request->getMethod();
        $publicParams  = $this->getPublicParams();
        $serviceParams = $request->getParams();

        $params = array_merge(
            $publicParams,
            [
                'method' => $method
            ],
            $serviceParams
        );

        var_dump($params);

        // 签名
        $params['sign'] = $this->generateSign($params);

        // 请求数据
        $resp =yield $this->curl(
            self::$sandbox ? $this->api_sandbox_uri : $this->api_uri,
            $params
        );

        var_dump($resp);

        // 解析返回
        yield $this->parseRep($resp);
    }

    /**
     * 设置签名方式
     * @param string $value 签名方式，支持md5, hmac
     * @return $this
     */
    public function setSignMethod($value = 'md5')
    {
        $this->sign_method = $value;

        return $this;
    }

    /**
     * 设置回传格式
     * @param string $value 响应格式，支持json/xml
     * @return $this
     */
    public function setFormat($value = 'json')
    {
        $this->format = $value;

        return $this;
    }

    /**
     * 解析返回数据
     * @param $response
     * @return array|false
     * @throws Exception
     */
    protected function parseRep($response)
    {
        if ($this->format == 'json') {
            $resp = json_decode($response,true);
        }
        elseif ($this->format == 'xml') {
            $resp = Support::xml2arr($response);
        }

        else {
            throw new Exception("format错误...");
        }
        if(empty($resp['error_response'])){
            return true;
        }else{
            $errorMsg = $resp['error_response']['msg'];

            if(!empty($resp['error_response']['sub_code'])){
                $errorMsg .= '-'.$resp['error_response']['sub_code'];
            }

            if(!empty($resp['error_response']['sub_msg'])){
                $errorMsg .= '-'.$resp['error_response']['sub_msg'];
            }

            throw new Exception($errorMsg,$resp['error_response']['code']);
        }
    }

    /**
     * 返回公共参数
     * @return array 
     */
    protected function getPublicParams()
    {
        return [
            'app_key'     => self::$app_key,
            'timestamp'   => date('Y-m-d H:i:s'),
            'format'      => $this->format,
            'v'           => '2.0',
            'sign_method' => $this->sign_method
        ];
    }

    /**
     * 生成签名
     * @param  array $params 待签参数
     * @return string
     * @throws Exception
     */
    protected function generateSign($params = [])
    {
        if ($this->sign_method == 'md5') {
            return $this->generateMd5Sign($params);
        } elseif ($this->sign_method == 'hmac') {
            return $this->generateHmacSign($params);
        } else {
            throw new Exception("sign_method错误...");
        }
    }

    /**
     * 按Md5方式生成签名
     * @param  array  $params 待签的参数
     * @return string         
     */
    protected function generateMd5Sign($params = [])
    {
        static::sortParams($params);  // 排序

        $arr = [];
        foreach ($params as $k => $v) {
            $arr[] = $k . $v;
        }
        
        $str = self::$app_secret . implode('', $arr) . self::$app_secret;

        return strtoupper(md5($str));
    }

    /**
     * 按hmac方式生成签名
     * @param  array  $params 待签的参数
     * @return string         
     */
    protected function generateHmacSign($params = [])
    {
        static::sortParams($params);  // 排序

        $arr = [];
        foreach ($params as $k => $v) {
            $arr[] = $k . $v;
        }
        
        $str = implode('', $arr);

        return strtoupper(hash_hmac('md5', $str, self::$app_secret));
    }

    /**
     * 待签名参数排序
     * @param  array  &$params 参数
     * @return array         
     */
    protected static function sortParams(&$params = [])
    {
        ksort($params);
    }


    /**
     * 通过接口名称获取对应的类名称
     * @param  string $method 接口名称
     * @return string         
     */
    protected static function getMethodClassName($method)
    {
        $methods = explode('.', $method);
        
        if (!is_array($methods))
            return false;

        $tmp = array();

        foreach ($methods as $value) {
            $tmp[] = ucwords($value);
        }

        $className = implode('', $tmp);

        return $className;
    }

    /**
     * curl请求
     * @param  string $url string
     * @param  array|null $postFields 请求参数
     * @return \Generator [type]             [description]
     */
    protected function curl($url, $postFields = null)
    {
        $httpClient = new HttpClient();
        $response = yield $httpClient->postByURL($url,$postFields);
        yield (intval($response->getStatusCode()) === 200) ? $response->getBody() : false;
    }
}
