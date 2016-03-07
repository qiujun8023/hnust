<?php

namespace Hnust\Crawler;

use Hnust\Config;
use Hnust\Utils\Http;

class Judge extends Jwc
{
    //获取评教批次
    public function getList()
    {
        //教务网登录
        $this->login();

        try {
            $http = new Http(array(
                CURLOPT_URL     => $this->baseUrl . 'jiaowu/jxpj/jxpjgl_queryxs.jsp',
                CURLOPT_COOKIE  => $this->cookies,
                CURLOPT_TIMEOUT => 3,
            ));
        } catch (\Exception $e) {
            throw new \Exception('网络异常，评教批次获取失败！', Config::RETURN_ERROR);
        }
        //获取评教学期
        $pattern  = '/selected>(.*?)<\/option>(?:.*?)';
        $pattern .= '---请选择---<\/option><option value="(.*?)">(?:.*?)';
        $pattern .= '---请选择---<\/option><option value="(.*?)">(?:.*?)<option value="(.*?)">/s';
        if (!preg_match($pattern, $http->content, $temp)) {
            throw new \Exception('未找到相关评教数据！', Config::RETURN_ERROR);
        }
        $term  = $temp[1];
        $batch = $temp[2];
        $types = array($temp[3],$temp[4]);

        //构造正则
        $pattern  = '/<tr heigth = 23(?:.*?)>';
        for ($k = 0; $k < 10; $k ++) {
            $pattern .= '\s*<td(?:.*?)>(.*?)<\/td>';
        }
        $pattern .= '(?:.*?)jxpjgl.do\?(.*?)\',/s';
        //获取评教列表
        $list = array();
        for ($i = 0; $i < count($types); $i++) {
            for ($j = 1; $j < 5; $j++) {
                try {
                    $http = new Http(array(
                        CURLOPT_URL        => $this->baseUrl . 'jxpjgl.do?method=queryJxpj&type=xs',
                        CURLOPT_POSTFIELDS => "xnxq={$term}&pjpc={$batch}&pjkc={$types[$i]}&sfxsyjzb=&zbnrstring=&ok=&PageNum={$j}",
                        CURLOPT_COOKIE     => $this->cookies,
                        CURLOPT_TIMEOUT    => 3,
                    ));
                } catch (\Exception $e) {
                    throw new \Exception('网络异常，评教列表获取失败！', Config::RETURN_ERROR);
                }

                //正则评教列表
                if (preg_match_all($pattern, $http->content, $temp)) {
                    for ($k = 0 ; $k < count($temp[0]); $k++) {
                        $list[] = array(
                            'course'  => $temp[6][$k],
                            'teacher' => $temp[7][$k],
                            'mark'    => $temp[8][$k],
                            'submit'  => $temp[9][$k],
                            'params'  => $temp[11][$k],
                        );
                    }
                }
                if (false !== stripos($http->content, 'nextno.gif')) {
                    break;
                }
            }
        }
        return $list;
    }

    //评教
    public function submit($params, $radio)
    {
        //教务网登录
        $this->login();

        //获取评教参数
        try {
            $http = new Http(array(
                CURLOPT_URL     => $this->baseUrl . 'jxpjgl.do?' . $params,
                CURLOPT_COOKIE  => $this->cookies,
                CURLOPT_TIMEOUT => 3,
            ));
        } catch (\Exception $e) {
            throw new \Exception('网络异常，评教参数获取失败！', Config::RETURN_ERROR);
        }
        $pattern = '/radioXh="0"  value="(.*?)">(?:.*?)radioXh="1"  value="(.*?)">(?:.*?)radioXh="2"  value="(.*?)">(?:.*?)radioXh="3"  value="(.*?)">/s';
        preg_match_all($pattern, $http->content, $temp);
        if (count($temp[0]) != 10) {
            throw new \Exception('评教失败，请教参数有误！', Config::RETURN_ERROR);
        }
        $mark = array($temp[1], $temp[2], $temp[3], $temp[4]);

        //构造get与post参数
        $get = 'method=savePj&tjfs=2&val=';
        for ($i = 0; $i < 10; $i++) {
            $get .= urlencode($mark[$radio[$i]][$i]) . (($i == 9)? '':'*');
        }
        parse_str($params, $temp);
        try {
            $http = new Http(array(
                CURLOPT_URL        => $this->baseUrl . 'jxpjgl.do?' . $get,
                CURLOPT_POSTFIELDS => "type=2&pj09id=&pjdw=3&xsflid=&typejsxs=xs&pjfl=&pj01id={$temp['pj01id']}&pj05id={$temp['pj05id']}&jg0101id={$temp['jg0101id']}&jx0404id={$temp['jx0404id']}&pj0502id={$temp['pj0502id']}&jx02id={$temp['jx02id']}",
                CURLOPT_COOKIE     => $this->cookies,
                CURLOPT_TIMEOUT    => 3,
            ));
        } catch (\Exception $e) {
            throw new \Exception('网络异常，评教提交失败！', Config::RETURN_ERROR);
        }
        if (false === stripos($http->content, '提交成功!')) {
            throw new \Exception('未知错误，评教提交失败！', Config::RETURN_ERROR);
        }
        return true;
    }
}