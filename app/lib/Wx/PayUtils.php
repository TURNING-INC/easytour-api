<?php

namespace app\lib;

class PayUtils
{
    //todo 根据merchant表
    private $mchId = '1696032179';
    private $key = 'JdWCxiEQgRu8824hyxUTDvPy9BsGhnzs';
    private $appId = 'wxf1c3ef6d9318c072';
    private $payNotifyUrl = 'http://api.easytour.cn/order/payCallback';

    public function pay($totalFee, $body, $ourTradeNo, $tradeType = 'JSAPI') {
        $totalFee = $totalFee * 100; //分
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $params = [
            'appid' => $this->appId,
            'mch_id' => $this->mchId,
            'nonce_str' => md5(mt_rand()),
            'out_trade_no' => $ourTradeNo,
            'total_fee' => $totalFee,
            'trade_type' => $tradeType,
            'body' => $body,
            'notify_url' => $this->payNotifyUrl,
        ];
        $params['sign'] = $this->makeSign($params);
        $xml = $this->toXml($params);
        $res = Tools::curlRequest($url, [], [], $xml, [], false);
        $res = $this->fromXml($res);

        return $res;
    }

    public function makeSign($params)
    {
        //签名步骤一：按字典序排序参数
        ksort($params);
        $string = $this->toUrlParams($params);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=".$this->key;
        //签名步骤三：MD5加密或者HMAC-SHA256
        //$string = hash_hmac('sha256', $string, $this->key);
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);

        return $result;
    }

    public function checkSign($params)
    {return true;
        if ($params['sign']) {
            $sign = $params['sign'];
            unset($params['sign']);

            $newSign = $this->makeSign($params);

            return ($sign == $newSign);

        } else {
            return false;
        }
    }

    public function validateOrder($data)
    {
        if ($this->checkSign($data)) {

            if ($data['appid'] == $this->appId && $data['mch_id'] == $this->mchId) {
                return true;
            } else {
                return false;
            }

        } else {
            return false;
        }
    }

    public function checkOrder($outTradeNo)
    {
        $url = 'https://api.mch.weixin.qq.com/pay/orderquery';

        $data['appid'] = $this->appId;
        $data['mch_id'] = $this->mchId;
        $data['out_trade_no'] = $outTradeNo;
        $data['nonce_str'] = md5(mt_rand());
        $data['sign'] = $this->makeSign($data);

        // xml格式化、发送
        $data = $this->toXml($data);
        $res = Tools::curlRequest($url, [], [], $data, [], false);

        // 反xml
        $res = $this->fromXml($res);

        return $res;
    }

    public function toUrlParams($params)
    {
        $buff = "";

        foreach ($params as $k => $v)
        {
            if($k != "sign" && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");

        return $buff;
    }
    /**
     * 输出xml字符
     **/
    public function toXml($array)
    {
        if(!is_array($array) || count($array) <= 0)
        {
            $return['code'] = 500;
            $return['data'] = false;
            $return['msg'] = '输出xml失败!';
        }

        $xml = "<xml>";
        foreach ($array as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }

    /**
     * 将xml转为array
     * @param string $xml
     */
    public function fromXml($xml)
    {
        if(!$xml){
            $return['code'] = 500;
            $return['data'] = false;
            $return['msg'] = 'xml转换失败!';
        }

        //将XML转为array
        //禁止引用外部xml实体
        // 设置 libxml 以禁用外部实体加载
        libxml_set_external_entity_loader(function ($public, $system, $context) {
            // 可以抛出异常、return 等操作
            throw new BusinessException(Code::PARAM_ERROR, "禁止加载外部实体");
        });
        $array = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

        foreach ($array as &$val) {
            $val = $val;
        }

        return $array;
    }

    public function formatRes($data)
    {
        $res['appId'] = $data['appid'];
        $res['timeStamp'] = time();
        $res['nonceStr'] = md5(mt_rand());
        $prepayId = $data['prepay_id'] ?? '';
        $res['package'] = "prepay_id={$prepayId}";
        $res['signType'] = 'MD5';
        $res['paySign'] = $this->makeSign($res);
        $res['return_code'] = $data['return_code'];
        $res['return_msg'] = $data['return_msg'];


        return $res;
    }
}