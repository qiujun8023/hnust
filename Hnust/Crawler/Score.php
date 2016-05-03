<?php

namespace Hnust\Crawler;

use Hnust\Config;
use Hnust\Utils\Http;

class Score extends Jwc
{
    public function __construct($sid, $name, $idcard)
    {
        //判断数据完整性
        if (empty($sid)) {
            throw new \Exception('学号不能为空', Config::RETURN_ERROR);
        }

        $this->sid    = $sid;
        $this->name   = $name;
        $this->idcard = $idcard;

        //父类构造函数
        parent::__construct($sid, '', true);
    }

    //登陆家长查成绩
    public function login()
    {
        for ($i = 0; $i < 3; $i++) {
            //获取验证码值
            $vCode = $this->vCode();
            if (false === $vCode) {
                continue;
            }

            try {
                //提交验证码
                $http = new Http(array(
                    CURLOPT_URL        => $this->baseUrl . 'xscjcx_check.jsp',
                    CURLOPT_POSTFIELDS => 'xsxm=' . $this->name . '&xssfzh=' . $this->idcard . '&yzm=' .  $vCode,
                    CURLOPT_COOKIE     => $this->cookies,
                    CURLOPT_TIMEOUT    => 3,
                ));
            } catch (\Exception $e) {
                throw new \Exception('验证码提交失败', Config::RETURN_ERROR);
            }

            $content = trim($http->content);

            //获取成功或多次失败返回
            if (32 === strlen($content)) {
                return $content;
            } else {
                continue;
            }
        }
        throw new \Exception('登录失败，教务网网络环境异常', Config::RETURN_ERROR);
    }

    //获取成绩信息
    public function getScore()
    {
        $score = array();
        $loginResult = $this->login();

        try {
            //获取成绩
            $http = new Http(array(
                CURLOPT_URL     => $this->baseUrl . 'xscjcx.jsp?yzbh=' . $loginResult,
                CURLOPT_COOKIE  => $this->cookies,
                CURLOPT_TIMEOUT => 3,
            ));
        } catch (\Exception $e) {
            throw new \Exception('成绩获取失败，教务网网络过慢', Config::RETURN_ERROR);
        }

        //判断成绩数据是否正常
        if (false === stripos($http->content, $this->name)) {
            throw new \Exception('查询失败，教务网数据有误', Config::RETURN_ERROR);
        }

        //正则个人信息
        $pattern = '/style="background-color: #eaeaea;">(?:.*?)<\/td>\s*<(?:.*?)>(.*)<\/td>/';
        preg_match_all($pattern, $http->content, $temp);
        $score['info'] = array(
            'name'    => $temp[1][0],
            'sid'     => $temp[1][1],
            'idcard'  => $temp[1][2],
            'college' => $temp[1][3],
            'major'   => $temp[1][4],
            'class'   => $temp[1][5],
            'time'    => date('Y-m-d H:i:s', time())
        );
        $score['data'] = array();

        //正则学期信息
        $pattern = '/学年学期：(.*)<\/td>/';
        preg_match_all($pattern, $http->content, $terms);
        foreach ($terms[1] as $term) {
            $score['data'][$term] = array();
            //分割课程信息
            $http->content = substr($http->content, strpos($http->content, '学年学期') + strlen('学年学期'));
            $tempContent = substr($http->content, 0, (($term == end($terms[1])) ? -1 : strpos($http->content, '学年学期')));
            //正则课程信息
            $pattern = '/<td>(▲)?&nbsp;(.*)<\/td>\s*<td align="center">(.*)<\/td>\s*<td>(.*)<\/td>\s*<td>(.*制)<\/td>/';
            preg_match_all($pattern, $tempContent, $temp);
            //存入score数组
            for ($j = 0; $j < count($temp[1]); $j++) {
                //判断补考
                $resit = empty($temp[1][$j])? false : true;
                $score['data'][$term][] = array(
                    'resit'  => $resit,
                    'course' => $temp[2][$j],
                    'credit' => $temp[3][$j],
                    'mark'   => $temp[4][$j],
                    'mode'   => $temp[5][$j]
                );
            }
        }

        return $score;
    }
}