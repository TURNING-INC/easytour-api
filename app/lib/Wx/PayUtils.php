<?php

namespace app\lib\Wx;

use app\lib\Tools;
use app\model\Merchants;
use think\Request;

class PayUtils
{
    private $mchId = '';
    private $key = '';
    private $appId = '';
    private $openid = '';

    private $payNotifyUrl = 'http://etapi.carben.me /o/payCallback';
    private $refundNotifyUrl = 'http://etapi.carben.me /o/refundCallback';

    public function __construct()
    {
        $merchantId = app(Request::class)->merchant->id ?? app(Request::class)->admin->merchant_id;
        $merchant = app(Merchants::class)->find($merchantId);

        if (!$merchant->wx_app_id || !$merchant->wx_mch_id || !$merchant->wx_mch_key) {
            HttpEx('未配置mch信息');
        }

        $this->appId = $merchant->wx_app_id;
        $this->mchId = $merchant->merchant->wx_mch_id;
        $this->key = $merchant->wx_mch_key;

        $this->openid = $merchant->user->wx_openid ?? '';
    }

    public function pay($totalFee, $body, $ourTradeNo, $tradeType = 'JSAPI') {

        $totalFee = $totalFee * 100; //分
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $params = [
            'appid' => $this->appId,
            'mch_id' => $this->mchId,
            'openid' => $this->openid,
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

    /**
     * 申请退款
     * @param  string $transactionId     微信支付订单号
     * @param  string $outTradeNo 商户订单号
     * @param  string $totalFee 订单金额
     * @param  string $refundFee 退款金额
     * @param  string $refundDesc 退款原因 若订单退款金额≤1元，且属于部分退款，则不会在退款消息中体现退款原因
     * @param  string $notifyUrl   回调地址
     * @param  string $attach   附加数据(附加数据，在查询API和支付通知中原样返回，可作为自定义参数使用。)
     * @return array  $res      下单结果
     */
    public function refund($transactionId, $outTradeNo, $outRefundNo, $totalFee, $refundFee, $refundDesc) {
        $url = 'https://api.mch.weixin.qq.com/secapi/pay/refund';

        $data['appid'] = $this->appId;
        $data['mch_id'] = $this->mchId;
        $data['nonce_str'] = md5(mt_rand());
        $data['transaction_id'] = $transactionId;
        $data['out_trade_no'] = $outTradeNo;
        $data['out_refund_no'] = $outRefundNo;
        $data['total_fee'] = $totalFee;
        $data['refund_fee'] = $refundFee;
        $data['refund_desc'] = $refundDesc;
        $data['notify_url'] = $this->refundNotifyUrl;
        $data['sign'] = $this->makeSign($data);

        // xml格式化、发送
        $data2 = $this->toXml($data);
        $res = Tools::curlRequest($url, [], [], $data2, [], false);

        // 反xml
        $res = $this->fromXml($res);
        $res = array_merge($res, $data);

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
    {
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
        $res['result_code'] = $data['result_code'];

        if (isset($data['err_code_des'])) {
            $res['err_code_des'] = $data['err_code_des'];
        }

        return $res;
    }

    /**
     * 对退款结果通知中的req_info进行解密
     * 1）对加密串A做base64解码，得到加密串B
     * 2）对商户key做md5，得到32位小写key* ( key设置路径：微信商户平台(pay.weixin.qq.com)-->账户设置-->API安全-->密钥设置 )
     * 3）用key*对加密串B做AES-256-ECB解密（PKCS7Padding）
     */
    public function decodeReqInfo($reqInfo) {
        $decodeReqInfo = base64_decode($reqInfo);
        $key = md5($this->key);
        $reqInfo = openssl_decrypt($decodeReqInfo , 'aes-256-ecb', $key, OPENSSL_RAW_DATA);

        return $this->fromXml($reqInfo);
    }
}