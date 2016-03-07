<?php

namespace Hnust\Crawler;

use Hnust\Config;
use Hnust\Utils\Http;
use Hnust\Utils\Cache;

class Jwc
{
    protected $sid;
    protected $passwd;
    protected $school;
    protected $baseUrl;
    protected $referer;
    protected $logined;
    protected $cookies;
    protected $cache;
    protected $vCode;
    protected $vCodePath;

    public function __construct($sid, $passwd = '', $nocache = false)
    {
        if (empty($sid)) {
            throw new \Exception('服务器错误，学号为空。', Config::RETURN_ERROR);
        }

        $this->sid    = $sid;
        $this->passwd = $passwd;

        //请求需要用到的网址
        if ($this->sid[2] < 3) {
            $this->school  = '科大本部';
            $this->baseUrl = Config::getConfig('kdjw_base_url');
            $this->referer = 'http://kdjw.hnust.cn/kdjw/Logon.do?method=logon';
        } else {
            $this->school  = '潇湘学院';
            $this->baseUrl = Config::getConfig('xxjw_base_url');
            $this->referer = 'http://xxjw.hnust.cn/xxjw/Logon.do?method=logon';
        }

        $this->logined = false;
        $this->cache = new Cache('cookies_jwc');
        $this->cookies = $nocache? '':$this->cache->get($this->sid);
    }

    //验证码识别初始化
    protected function vCodeInit()
    {
        //配置验证码识别参数
        if (empty($this->vCode)) {
            $this->vCode = new VCode(array(
                'offsetWidth'  => 3,
                'offsetHeight' => 4,
                'wordNum'      => 4,
                'wordWidth'    => 12,
                'wordHeight'   => 12,
                'wordSpace'    => -2,
            ), array(
                '1' => '000011000000000111000000001111000000011011000000010011000000000011000000000011000000000011000000000011000000000011000000000011000000000011000000',
                '2' => '001111000000011111100000111000110000110000110000000000110000000001100000000011100000000111000000001110000000011000000000111111110000111111110000',
                '3' => '001111100000011111110000110000110000000000110000000111100000000111100000000001110000000000110000110000110000111001110000011111100000001111000000',
                'b' => '011000000000011000000000011000000000011011100000011111110000011100110000011000010000011000010000011000010000011100110000011111110000011011100000',
                'c' => '000000000000000000000000000000000000000111100000001111110000011100110000011000000000011000000000011000000000011100110000001111100000000111100000',
                'n' => '000000000000000000000000000000000000011011100000011111110000011100010000011000010000011000010000011000010000011000010000011000010000011000010000',
                'm' => '000000000000000000000000000000000000011011100111011111111111011100111001011000110001011000110001011000110001011000110001011000110001011000110001',
                'v' => '000000000000000000000000000000000000011000110000011000110000011000110000001101100000001101100000001101100000000111000000000111000000000111000000',
                'x' => '000000000000000000000000000000000000011000110000011101110000001101100000000111000000000111000000000111000000001101100000011101110000011000110000',
                'z' => '000000000000000000000000000000000000011111110000011111110000000001100000000011100000000111000000001110000000001100000000011111110000011111110000'
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
                CURLOPT_URL     => $this->baseUrl . 'verifycode.servlet',
                CURLOPT_REFERER => $this->referer,
                CURLOPT_TIMEOUT => 3,
            ));
        } catch (\Exception $e) {
            throw new \Exception('验证码获取失败。', Config::RETURN_ERROR);
        }
        $this->cookies = $http->cookies;

        //验证码识别
        $this->vCodeInit();
        file_put_contents($this->vCodePath, $http->content);
        return $this->vCode->getKeys($this->vCodePath);
    }

    //检查登录状态
    public function checkLogin()
    {
        if (empty($this->cookies)) {
            return false;
        }

        try {
            //获取网页内容
            $http = new Http(array(
                CURLOPT_URL     => $this->baseUrl . 'jiaowu/pyfa/xsd_pyfazg_list.jsp',
                CURLOPT_COOKIE  => $this->cookies,
                CURLOPT_TIMEOUT => 3,
            ));
        } catch (\Exception $e) {
            throw new \Exception('教务网网络过慢。', Config::RETURN_ERROR);
        }

        //检查登录状态
        if (false !== stripos($http->content, '<title>出错页面</title>')) {
            $this->logined = false;
            $this->cookies = '';
            $this->cache->delete($this->sid);
            return false;
        } else {
            $this->logined = true;
            return true;
        }
    }

    //教务网登录
    public function login()
    {
        if ($this->logined || $this->checkLogin()) {
            return true;
        } elseif (empty($this->passwd)) {
            throw new \Exception('请输入教务网密码：', Config::RETURN_NEED_PASSWORD);
        }

        //三次识别验证码
        for ($i = 0; $i < 3; $i++) {
            if (!($vCode = $this->vCode())) {
                continue;
            }
            try {
                //提交验证码
                $http = new Http(array(
                    CURLOPT_URL        => $this->baseUrl . 'Logon.do?method=logon',
                    CURLOPT_POSTFIELDS => 'useDogCode=&dlfl=0&USERNAME=' . $this->sid . '&PASSWORD=' . $this->passwd . '&RANDOMCODE=' . $vCode,
                    CURLOPT_COOKIE     => $this->cookies,
                    CURLOPT_TIMEOUT    => 3,
                ));
            } catch (\Exception $e) {
                throw new \Exception('登录失败，无法提交验证码。', Config::RETURN_ERROR);
            }
            //登录成功
            if (false !== stripos($http->content, 'framework/main.jsp')) {
                try {
                    $http = new Http(array(
                        CURLOPT_URL     => $this->baseUrl . 'Logon.do?method=logonBySSO',
                        CURLOPT_COOKIE  => $this->cookies,
                        CURLOPT_TIMEOUT => 3,
                    ));
                } catch (\Exception $e) {
                    throw new \Exception('教务网初始化失败，请刷新或稍后再试', Config::RETURN_ERROR);
                }
                $this->logined = true;
                $this->cache->set($this->sid, $this->cookies, 3600);
                return true;
            //密码错误，抛出异常
            } else if (false !== stripos($http->content, '帐号不存在或密码错误')) {
                throw new \Exception('登录失败，教务网密码错误！', Config::RETURN_NEED_PASSWORD);
            } else {
                continue;
            }
        }
        throw new \Exception('登录失败，验证码识别失败。', Config::RETURN_ERROR);
    }
}