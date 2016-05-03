<?php

namespace Hnust\Crawler;

use Hnust\Config;
use Hnust\Utils\Http;

class Card
{
    protected $sid;
    protected $passwd;
    protected $baseUrl;
    protected $cookies;

    public function __construct($sid, $passwd)
    {
        if (empty($sid) || empty($passwd)) {
            throw new \Exception('请输入一卡通查询密码：', Config::RETURN_NEED_PASSWORD);
        }
        $this->sid    = $sid;
        $this->passwd = $passwd;
        //一卡通基地址
        $this->baseUrl = Config::getConfig('card_base_url');

        //登录一卡通
        $this->login();
    }

    //一卡通登录
    public function login()
    {
        try {
            $http = new Http(array(
                CURLOPT_URL        => $this->baseUrl . 'pages/gmxyLoginStudent.action',
                CURLOPT_POSTFIELDS => 'userType=1&loginType=2&name=' . $this->sid . '&passwd=' . $this->passwd,
                CURLOPT_COOKIE     => $this->cookies,
                CURLOPT_TIMEOUT    => 5,
            ));
        } catch (\Exception $e) {
            throw new \Exception('登录异常，一卡通网络异常', Config::RETURN_ERROR);
        }

        $this->cookies = empty($this->cookies)? $http->cookies:$this->cookies;

        //判断登录成功与否
        if (false === stripos($http->headers, 'HTTP/1.1 302 Moved Temporarily')) {
            $content = mb_convert_encoding($http->content, 'UTF-8', 'GBK');
            $pattern = '/<p class="biaotou" >(.*)<\/p>/';
            preg_match($pattern, $content, $temp);
            $msg = empty($temp)? '登录异常，一卡通数据异常':$temp[1];
            $code = (false !== stripos($msg, '密码错误'))? Config::RETURN_NEED_PASSWORD:Config::RETURN_ERROR;
            throw new \Exception($msg, $code);
        } else {
            return true;
        }
    }

    //一卡通图片转映射对应值
    protected function cardKeys($content) {
        $keys = array(
            '0011110011001101100' => '0',
            '0011000111100000110' => '1',
            '0111110110011011001' => '2',
            '0011110011011101000' => '3',
            '0000110000111000011' => '4',
            '0011111001000001100' => '5',
            '0001110001100001110' => '6',
            '1111111110001110000' => '7',
            '0111110110001111000' => '8',
            '0111110110011011000' => '9',
        );

        //打开图片
        $imgPath = tempnam(Config::TMP_PATH, 'img');
        file_put_contents($imgPath, $content);
        if (!($res = imagecreatefrompng($imgPath))) {
            throw new \Exception('获取一卡通映射值失败', Config::RETURN_ERROR);
        }

        $result = array();
        for ($i = 0; $i < 10; $i++) {
            $data = '';
            $x = 19 + $i % 3 * 36;
            $y = 40+ ((int)($i / 3)) * 36;
            for ($h = $y; $h < ($y + 13); $h++) {
                for ($w = $x; $w < ($x + 7); $w++) {
                    $rgb = imagecolorsforindex($res, imagecolorat($res,$w,$h));
                    $data .= ($rgb['red'] < 127 && $rgb['green'] < 127 && $rgb['blue'] < 127)? '1':'0';
                }
            }
            $result[$keys[substr($data, 0, 19)]] = $i;
        }
        return $result;
    }

    //获取一卡通信息
    public function getInfo()
    {
        try {
            $http = new Http(array(
                CURLOPT_URL     => $this->baseUrl . 'accountcardUser.action',
                CURLOPT_COOKIE  => $this->cookies,
                CURLOPT_TIMEOUT => 5,
            ));
        } catch (\Exception $e) {
            throw new \Exception('获取一卡通信息失败', Config::RETURN_ERROR);
        }
        $content  = str_replace(array('&nbsp;', ' ', "\r\n"), '', strip_tags($http->content));
        $pattern  = '/姓名：(.*?)帐号：(.*?)性别：(?:.*?)卡状态：(.*?)冻结状态：(.*?)余额：(.*?)（卡余额）(?:.*?)检查状态：(.*?)挂失状态：(.*)$/';
        preg_match($pattern, $content, $temp);
        if (empty($temp)) {
            throw new \Exception('获取一卡通信息失败', Config::RETURN_ERROR);
        }

        //检查一卡通状态
        $status = '正常';
        foreach (array(3, 4, 6, 7) as $v) {
            if ($temp[$v] != '正常') {
                $status = $temp[$v];
            }
        }

        return array(
            'name'    => trim($temp[1]),
            'cardId'  => trim($temp[2]),
            'balance' => rtrim(trim($temp[5]), '元'),
            'status'  => $status,
        );
    }

    //挂失与解挂
    public function doLoss($loss = true)
    {
        //获取卡号
        $cardInfo = $this->getInfo();
        $cardId   = $cardInfo['cardId'];

        //获取密码加密图片
        try {
            $http = new Http(array(
                CURLOPT_URL     => $this->baseUrl . 'getpasswdPhoto.action',
                CURLOPT_COOKIE  => $this->cookies,
                CURLOPT_TIMEOUT => 5,
            ));
        } catch (\Exception $e) {
            throw new \Exception('获取一卡通映射值失败', Config::RETURN_ERROR);
        }

        //加密密码
        $passwd = $this->passwd;
        $cardKeys = $this->cardKeys($http->content);
        for ($i = 0; $i < strlen($passwd); $i++) {
            $passwd[$i] = $cardKeys[$passwd[$i]];
        }

        //挂失/解挂
        try {
            $http = new Http(array(
                CURLOPT_URL        => $this->baseUrl . (($loss)? 'accountDoLoss.action':'accountDoreLoss.action'),
                CURLOPT_POSTFIELDS => 'account=' . $cardId . '&passwd=' . $passwd,
                CURLOPT_COOKIE     => $this->cookies,
                CURLOPT_TIMEOUT    => 5,
            ));
        } catch (\Exception $e) {
            throw new \Exception('网络异常，' . ($loss? '挂失':'解挂') . '失败', Config::RETURN_ERROR);
        }
        $content = mb_convert_encoding($http->content, 'UTF-8', 'GBK');
        $pattern = '/<p class="biaotou" ?>(.*)<\/p>/';
        preg_match($pattern, $content, $temp);
        return $temp[1];
    }

    public function getRecord($cardId, $startDate = null, $endDate = null)
    {
        $startDate = empty($startDate)? date('Ymd', strtotime('-1 month')):$startDate;
        $endDate   = empty($endDate)? date('Ymd', time()):$endDate;
        $result    = array();
        try {
            $records = '';
            //查询当日记录
            if ($endDate >= date('Ymd', time())) {
                $http = new Http(array(
                    CURLOPT_URL        => $this->baseUrl . 'accounttodatTrjnObject.action',
                    CURLOPT_POSTFIELDS => "account={$cardId}&inputObject=all",
                    CURLOPT_COOKIE     => $this->cookies,
                    CURLOPT_TIMEOUT    => 5,
                ));
                //记录当天记录
                $records .= $http->content;
            }

            if ($startDate < date('Ymd', time())) {
                //获取下一步地址
                $http = new Http(array(
                    CURLOPT_URL     => $this->baseUrl . 'accounthisTrjn.action',
                    CURLOPT_COOKIE  => $this->cookies,
                    CURLOPT_TIMEOUT => 5,
                ));
                preg_match('/action="\/(.*?)"/', $http->content, $temp);

                //选择类型为所有
                $http = new Http(array(
                    CURLOPT_URL        => $this->baseUrl . $temp[1],
                    CURLOPT_POSTFIELDS => "account={$cardId}&inputObject=all",
                    CURLOPT_COOKIE     => $this->cookies,
                    CURLOPT_TIMEOUT    => 5,
                ));
                preg_match('/action="\/(.*?)"/', $http->content, $temp);

                //选择时间
                $http = new Http(array(
                    CURLOPT_URL        => $this->baseUrl . $temp[1],
                    CURLOPT_POSTFIELDS => "inputStartDate={$startDate}&inputEndDate={$endDate}",
                    CURLOPT_COOKIE     => $this->cookies,
                    CURLOPT_TIMEOUT    => 5,
                ));
                preg_match('/action="(.*?)"/', $http->content, $temp);

                //取查询结果
                $http = new Http(array(
                    CURLOPT_URL     => $this->baseUrl . 'accounthisTrjn.action' . $temp[1],
                    CURLOPT_COOKIE  => $this->cookies,
                    CURLOPT_TIMEOUT => 15,
                ));
                $records .= $http->content;

                for ($i = 2; $i < 30; $i++) {
                    if (false === stripos($http->content, 'button14_Onclick();')) {
                        break;
                    }
                    $http = new Http(array(
                        CURLOPT_URL        => $this->baseUrl . 'accountconsubBrows.action',
                        CURLOPT_POSTFIELDS => 'pageNum=' . $i,
                        CURLOPT_COOKIE     => $this->cookies,
                        CURLOPT_TIMEOUT    => 5,
                    ));
                    $records .= $http->content;
                }
            }

            $records = mb_convert_encoding($records, 'UTF-8', 'GBK');
        } catch (\Exception $e) {
            throw new \Exception('获取交易记录失败', Config::RETURN_ERROR);
        }

        //正则交易记录
        $pattern  = '/<td  align="center">(.*?)<\/td>\s*';
        $pattern .= '<td   align="center">(.*?)<\/td>\s*';
        $pattern .= '<td  align="center" >(.*?)<\/td>\s*';
        $pattern .= '<td  align="center" >(.*?)<\/td>\s*';
        $pattern .= '<td  align="right">(.*?)<\/td>\s*';
        $pattern .= '<td\s*align="right">(.*?)<\/td>\s*';
        $pattern .= '<td  align="center">(\d*)<\/td>\s*';
        $pattern .= '<td  align="center" >(.*?)<\/td>/s';
        if (preg_match_all($pattern, $records, $temp)) {
            for ($i = 0; $i < count($temp[0]); $i++) {
                $result[] = array(
                    'time'    => substr(trim($temp[1][$i]), 5),
                    'type'    => trim($temp[2][$i]),
                    'system'  => trim($temp[3][$i]),
                    'trade'   => trim($temp[5][$i]),
                    'balance' => trim($temp[6][$i]),
                    'count'   => trim($temp[7][$i]),
                    'status'  => trim($temp[8][$i])
                );
            }
        }
        return $result;
    }
}