<?php
namespace Flc\Alidayu;

/**
 * 阿里大于 - 辅助类
 *
 * @author Flc <2016年9月19日 21:01:49>
 * @link   http://flc.ren
 */
class Support
{
    /**
     * 格式化数组为json字符串（避免数字等不符合规格）
     * @param  array $params 数组
     * @return string
     */
    public static function jsonStr($params = [])
    {
        $arr = [];

        array_walk($params, function($value, $key) use (&$arr) {
            $arr[] = "\"{$key}\":\"{$value}\"";
        });

        if (is_array($arr) || count($arr) > 0) {
            return '{' . implode(',', $arr) . '}';
        }

        return '';
    }

    /**
     * 获取随机位数数字
     * @param  integer $len 长度
     * @return string       
     */
    public static function randStr($len = 6)
    {
        $chars = str_repeat('0123456789', $len);
        $chars = str_shuffle($chars);
        $str   = substr($chars, 0, $len);
        return $str;
    }

    /**
     * XML编码
     * @param mixed $data 数据
     * @param string $root 根节点名
     * @param string $item 数字索引的子节点名
     * @param string $id 数字索引子节点key转换的属性名
     * @return string
     */
    static public function arr2xml($data, $root = 'xml', $item = 'item', $id = 'id')
    {
        return "<{$root}>" . self::_data_to_xml($data, $item, $id) . "</{$root}>";
    }

    /**
     * XML内容生成
     * @param array $data 数据
     * @param string $item 子节点
     * @param string $id 节点ID
     * @param string $content 节点内容
     * @return string
     */
    static private function _data_to_xml($data, $item = 'item', $id = 'id', $content = '')
    {
        foreach ($data as $key => $val) {
            is_numeric($key) && $key = "{$item} {$id}=\"{$key}\"";
            $content .= "<{$key}>";
            if (is_array($val) || is_object($val)) {
                $content .= self::_data_to_xml($val);
            } elseif (is_numeric($val)) {
                $content .= $val;
            } else {
                $content .= '<![CDATA[' . preg_replace("/[\\x00-\\x08\\x0b-\\x0c\\x0e-\\x1f]/", '', $val) . ']]>';
            }
            list($_key,) = explode(' ', $key . ' ');
            $content .= "</$_key>";
        }
        return $content;
    }


    /**
     * 将xml转为array
     * @param string $xml
     * @return array
     */
    static public function xml2arr($xml)
    {
        return json_decode(Support::json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }

    /**
     * 生成安全JSON数据
     * @param array $array
     * @return string
     */
    static public function json_encode($array)
    {
        return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function ($matches) {
            return mb_convert_encoding(pack("H*", $matches[1]), "UTF-8", "UCS-2BE");
        }, json_encode($array));
    }
}
