<?php

namespace app\lib;
use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;

class AliyunUtils
{
    /**
     * 阿里云短信发送新版
     */
    function sendVerifyCode($phone, $code)
    {
        $template="SMS_468970383";
        $signName = 'MIMO梦墨科技';
        $option = ['sms_key' => 'LTAI5tLt5tGeTWezRXSwQJwH', 'sms_secret' => 'QW45RvmcISjBSfWgyiByTXbhYZE1dS'];
        $config = new Config([
            "accessKeyId" => $option['sms_key'],
            "accessKeySecret" => $option['sms_secret'],
        ]);
        $config->endpoint = "dysmsapi.aliyuncs.com";
        $client = new Dysmsapi($config);
        $sendSmsRequest = new SendSmsRequest(
            [
                "signName" => $signName,
                "templateCode" => $template,
                "phoneNumbers" => $phone,
                "templateParam" => json_encode(['code' => $code])
            ]
        );

        $runtime = new RuntimeOptions([]);
        try {
            $result = $client->sendSmsWithOptions($sendSmsRequest, $runtime);
            if ($result->body->code != 'OK') {
                return false;
            }
            return true;
        }
        catch (\Exception $e) {
            return false;
        }
    }

}