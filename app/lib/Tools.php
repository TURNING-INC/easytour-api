<?php
namespace app\lib;

use Firebase\JWT\JWT;
use PHPMailer\PHPMailer\PHPMailer;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class Tools
{
    /**
     * curl
     * @param $url 访问的URL
     * @param null $post post数据(不填则为GET)
     * @param $header 请求头
     * @param $config 设置
     * @return array|bool|string
     */
    public static function curlRequest(
        $url,
        $get = null,
        $header = [],
        $post = null,
        $config = [],
        $returnArray = true
    )
    {
        $cookie = $config['cookie'] ?? '';
        $useHttpBuildQuery = $config['use_http_build_query'] ?? 0;
        $referer = $config['referer'] ?? '';
        $returnCookie = $config['return_cookie'] ?? 0;

        if ($get) {
            $query = http_build_query($get);
            $url .= "?{$query}";
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_REFERER, $referer);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 1000);
        curl_setopt($curl, CURLOPT_TIMEOUT, 1000);

        if ($post) {
            curl_setopt($curl, CURLOPT_POST, 1);

            if ($useHttpBuildQuery) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
            } else {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
            }
        }

        if ($cookie) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }

        curl_setopt($curl, CURLOPT_HEADER, $returnCookie);
        //curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);  //ssl 这两行代码是为了能走https的请求,http请求放着也没有影响
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);  //ssl 这两行代码是为了能走https的请求,http请求放着也没有影响


//        curl_setopt($curl, CURLOPT_BUFFERSIZE, 128);
//        curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate'); // 启用压缩
//        curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);

        if (isset($config['CURLOPT_USERPWD'])) {
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, $config['CURLOPT_USERPWD']);
        }

        $data = curl_exec($curl);

        if (curl_errno($curl)) {
            return curl_error($curl);
        }

        curl_close($curl);

        if ($returnCookie) {
            list($header, $body) = explode("\r\n\r\n", $data, 2);
            preg_match_all("/Set\-Cookie:([^;]*);/", $header, $matches);
            $info['cookie'] = substr($matches[1][0], 1);
            $info['content'] = $body;
            return $info;
        }
        $return = $data;

        if ($returnArray) {
            $return = json_decode($return, true) ?? [];
        }

        if (!$return) {
            var_dump($data);
        }

        return $return;
    }

    /**
     * 获取客户端ip
     * @return false|mixed|string
     */
    public static function getIp()
    {
        $ip = false;

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']);

            if ($ip) {
                array_unshift($ips, $ip);
                $ip = FALSE;
            }

            for ($i = 0; $i < count($ips); $i++) {
                if (!preg_match('/^(10│172.16│192.168)./', $ips[$i])) {
                    $ip = $ips[$i];
                    break;
                }
            }
        }

        return ($ip ?: $_SERVER['REMOTE_ADDR']);
    }

    public static function decodeFrontToken($token)
    {
        $jwtData = (array)JWT::decode($token, env('front.jwt_key'), ['HS512']);

        return $jwtData;
    }

    public static function decodeDataId($encrypted) {
        $string="";
        for($i=0;$i<strlen($encrypted)-1;$i+=2)
            $string.=chr(hexdec($encrypted[$i].$encrypted[$i+1]));
        return explode(':', $string)[1] ?? 0;
    }

    public static function generateQrCode($url) {
        $qrCode = QrCode::create($url);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        return $result->getDataUri();
    }

}