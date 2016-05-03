<?php

namespace Hnust\Crawler;

use Hnust\Config;
use Hnust\Utils\Http;

require_once __DIR__ . '/../../library/bmp2jpg.php';

class Tuition
{
    protected $baseUrl;
    protected $sid;
    protected $passwd;
    protected $cookies;
    protected $vCode;
    protected $vCodePath;

    public function __construct($sid, $passwd = '')
    {
        $this->sid     = $sid;
        $this->passwd  = $passwd;
        $this->baseUrl = Config::getConfig('cwc_base_url');
    }

    //验证码识别初始化
    protected function vCodeInit()
    {
        //配置验证码识别参数
        if (empty($this->vCode)) {
            $this->vCode = new VCode(array(
                'offsetWidth'  =>  0,
                'offsetHeight' =>  0,
                'wordNum'      =>  4,
                'wordWidth'    => 10,
                'wordHeight'   => 10,
                'wordSpace'    =>  0,
            ), array(
                '0' => '0001111000001000010000100001000010110100001011010000101101000010110100001000010000100001000001111000',
                '1' => '0000100000001110000000001000000000100000000010000000001000000000100000000010000000001000000011111000',
                '2' => '0001111000001000010000100001000000000100000000100000000100000000100000000100000000100001000011111100',
                '3' => '0001111000001000010000100001000000001000000010000000000000000000000100001000010000100001000001111000',
                '4' => '0000010000000001000000001100000001010000001001000000100100000011111100000001000000000100000000111100',
                '5' => '0011111100001000000000100000000010111000001100010000000001000000000100001000010000100001000001111000',
                '6' => '0000011000000000010000100000000010000000001011100000110001000010000100001000010000100001000001111000',
                '7' => '0011111100001000100000100010000000010000000001000000001000000000100000000010000000001000000000100000',
                '8' => '0001111000001000010000100001000010000100000111100000010010000010000100001000010000100001000001111000',
                '9' => '0001110000001000100000100001000010000100001000110000011101000000000100000000010000100010000001110000'
            ));
            $this->vCodePath = tempnam(Config::TMP_PATH, 'vcode');
        }
        return $this->vCode;
    }

    //教务网验证码识别
    protected function vCode()
    {
        try {
            //获取验证码图片内容
            $http = new Http(array(
                CURLOPT_URL     => $this->baseUrl . 'code.asp',
                CURLOPT_COOKIE  => $this->cookies,
                CURLOPT_TIMEOUT => 3,
            ));
        } catch (\Exception $e) {
            return false;
        }
        $this->cookies = empty($this->cookies)? $http->cookies:$this->cookies;

        //验证码识别
        $this->vCodeInit();
        file_put_contents($this->vCodePath, $http->content);
        if (@bmp2jpg($this->vCodePath)) {
            return $this->vCode->getKeys($this->vCodePath);
        } else {
            return false;
        }
    }

    //财务网登录
    public function login()
    {
        if (empty($this->passwd)) {
            throw new \Exception('请输入财务网密码：', Config::RETURN_NEED_PASSWORD);
        }

        //三次识别验证码
        for ($i = 0; $i < 3; $i++) {
            //获取验证码值
            if (!($vCode = $this->vCode())) {
                continue;
            }

            try {
                //提交验证码
                $http = new Http(array(
                    CURLOPT_URL        => $this->baseUrl . 'pay/login.asp',
                    CURLOPT_POSTFIELDS => 'uid=' . $this->sid . '&pwd=' . $this->passwd . '&yzm=' . $vCode,
                    CURLOPT_COOKIE     => $this->cookies,
                    CURLOPT_TIMEOUT    => 3,
                ));
            } catch (\Exception $e) {
                throw new \Exception('验证码提交失败，请刷新或稍后再试', Config::RETURN_ERROR);
            }

            $content = mb_convert_encoding($http->content, 'UTF-8', 'GBK');
            if (false !== stripos($content, 'cx_index.asp')) {
                try {
                    $http = new Http(array(
                        CURLOPT_URL     => $this->baseUrl . 'pay/cx_index.asp',
                        CURLOPT_COOKIE  => $this->cookies,
                        CURLOPT_TIMEOUT => 3,
                    ));
                } catch (\Exception $e) {
                    throw new \Exception('财务网网初始化失败，请刷新或稍后再试', Config::RETURN_ERROR);
                }
                return true;
            } elseif (false !== stripos($content, '密码有误，请重试')) {
                throw new \Exception('登录失败，财务网密码错误', Config::RETURN_NEED_PASSWORD);
            } else {
                continue;
            }
        }
        throw new \Exception('登录失败，财务网网络环境异常', Config::RETURN_ERROR);
    }

    //获取学费内容
    public function getTuition()
    {
        $this->login();

        try {
            $http = new Http(array(
                CURLOPT_URL     => $this->baseUrl . 'pay/stu_search.asp',
                CURLOPT_COOKIE  => $this->cookies,
                CURLOPT_TIMEOUT => 5,
            ));
        } catch (\Exception $e) {
            throw new \Exception('学费获取失败，请刷新或稍后再试', Config::RETURN_ERROR);
        }

        $content = str_replace('￥.00', '￥0.00',
            mb_convert_encoding($http->content, 'UTF-8', 'GBK')
        );

        //正则收费合计
        $pattern = '/<td class="style11" align="right">(.*?)<\/td>/';
        preg_match_all($pattern, $content, $temp);
        $tuition = array('total' => array(
            'payable'   => $temp[1][0],
            'returns'   => $temp[1][1],
            'reduction' => $temp[1][2],
            'paid'      => $temp[1][3],
            'owing'     => $temp[1][4],
        ));

        //构造正则表达式
        $pattern = '/<tr>\s*';
        for ($i = 0; $i < 7; $i++) {
            $pattern .= '<td height="22"(?:.*?)>(.*?)<\/td>\s*';
        }
        $pattern .= '<\/tr>/s';
        //正则详细收费内容
        preg_match_all($pattern, $content, $temp);
        for ($i = 0; $i < count($temp[0]); $i++) {
            $term = str_replace('学年度', '', $temp[1][$i]);
            $tuition[$term][] = array(
                'item'      => $temp[2][$i],
                'payable'   => $temp[3][$i],
                'returns'   => $temp[4][$i],
                'reduction' => $temp[5][$i],
                'paid'      => $temp[6][$i],
                'owing'     => $temp[7][$i],
            );
        }

        return $tuition;
    }
}