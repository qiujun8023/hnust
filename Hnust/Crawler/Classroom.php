<?php

namespace Hnust\Crawler;

use Hnust\Config;
use Hnust\Utils\Http;

class Classroom extends Jwc
{
    //获取空闲教室
    public function getClassroom($term, $build, $week, $day, $beginSession, $endSession)
    {
        //教务网登录
        $this->login();

        try {
            $http = new Http(array(
                CURLOPT_URL        => $this->baseUrl . 'jiaowu/kxjsgl/kxjsgl.do?method=queryKxxxByJs&typewhere=xszq',
                CURLOPT_POSTFIELDS => "typewhere=xszq&xnxqh={$term}&jxlbh={$build}&zc={$week}&zc2={$week}",
                CURLOPT_COOKIE     => $this->cookies,
                CURLOPT_TIMEOUT    => 5,
            ));
        } catch (\Exception $e) {
            throw new \Exception('网络异常，空闲教室查询失败', Config::RETURN_ERROR);
        }

        $classroom = array();

        //正则空闲教室列表
        $pattern = '/id="jsids">\s*(.*?)(\(\d+\/\d+\))\s*<\/td>/s';
        preg_match_all($pattern, $http->content, $temp);
        $lists = $temp[1];

        //正则空闲教室信息
        $pattern = '/showJc\(this\)">\s*&nbsp;(.*?)\s*<\/td>/s';
        preg_match_all($pattern, $http->content, $temp);
        for ($i = 0; $i < count($lists); $i++) {
            for ($begin = $beginSession, $end = $endSession; $begin <= $end; $begin++) {
                if(!empty($temp[1][$i * 35 + ($day - 1) * 5 + $begin - 1])) {
                    break;
                }
            }
            if ($begin > $end) {
                $classroom[] = trim($lists[$i]);
            }
        }

        return $classroom;
    }
}