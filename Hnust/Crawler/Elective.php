<?php

namespace Hnust\Crawler;

use Hnust\Config;
use Hnust\Utils\Http;

class Elective extends Jwc
{

    public function deduplication($string)
    {
        return implode(',', array_unique(explode(',', $string)));
    }

    //获取选修课列表
    public function getList($term)
    {
        //教务网登录
        $this->login();

        //构造正则表达式
        $pattern = '/<tr heigth = 23(?:.*?)>';
        for ($i = 0; $i < 10; $i++) {
            $pattern .= '<td height="23"(?:.*?)title="(.*?)">(?:.*?)<\/td>';
        }
        $pattern .= '(?:.*?)onclick="javascript:vJsMod\(\'\/(?:xxjw|kdjw)\/(.*?)\',400,250\)/s';

        //正则选修课列表
        $list = array();
        for ($i = 1; $i < 50 ;$i++) {
            try {
                //获取第$i页的选修列表
                $http = new Http(array(
                    CURLOPT_URL        => $this->baseUrl . 'xkglAction.do?method=toFindxskxkclb&zzdxklbname=1&xnxq01id=' . $term,
                    CURLOPT_POSTFIELDS => 'PageNum=' . $i,
                    CURLOPT_COOKIE     => $this->cookies,
                    CURLOPT_TIMEOUT    => 5,
                ));
            //由于选课的网络因素，不抛出异常
            } catch (\Exception $e) {
                break;
            }

            //正则提取数据
            $content = str_replace(array('&nbsp;', ' >'), array('', '>'), $http->content);
            if (preg_match_all($pattern, $content, $temp)) {
                for ($j = 0; $j < count($temp[0]); $j++) {
                    $list[] = array(
                        'school'  => $this->school,
                        'course'  => $temp[1][$j],
                        'college' => $temp[2][$j],
                        'credit'  => $temp[3][$j],
                        'total'   => $temp[4][$j],
                        'remain'  => $temp[5][$j],
                        'teacher' => $temp[6][$j],
                        'week'    => $temp[8][$j],
                        'time'    => $this->deduplication($temp[9][$j]),
                        'place'   => $this->deduplication($temp[10][$j]),
                        'url'     => $temp[11][$j],
                        'term'    => $term,
                    );
                }
            }

            //判断如果不不存在下一页则退出
            if (false !== stripos($content, 'nextno.gif')) {
                break;
            }
        }

        //返回结果集
        return $list;
    }

    //获取个人选课列表
    public function getSelected($term)
    {
        //教务网登录
        $this->login();

        //构造正则表达式
        $pattern = '/';
        for ($i = 0; $i < 9; $i++) {
            $pattern .= '<td height="23"(?:.*?)>(.*?)<\/td>';
        }
        $pattern .= '(?:.*?)onclick="javascript:vJsMod\(\'\/(?:xxjw|kdjw)\/(.*?)\',400,250\)/s';

        //正则已选列表
        $selected = array();
        for ($i = 1; $i <= 3 ;$i++) {
            try {
                //获取第$i页的课程列表
                $http = new Http(array(
                    CURLOPT_URL        => $this->baseUrl . 'xkglAction.do?method=toFindxsyxkc&zzdxklbname=1&jx02kczid=&xnxq01id=' . $term,
                    CURLOPT_POSTFIELDS => 'PageNum=' . $i,
                    CURLOPT_COOKIE     => $this->cookies,
                    CURLOPT_TIMEOUT    => 5,
                ));
            } catch (\Exception $e) {
                throw new \Exception('网络异常，已选列表获取失败！', Config::RETURN_ERROR);
            }

            //正则提取数据
            $content = str_replace('&nbsp;', '', $http->content);
            if (preg_match_all($pattern, $content, $temp)) {
                //提取数据
                for ($j = 0; $j < count($temp[0]); $j++) {
                    if ($temp[9][$j] == '预置') {
                        continue;
                    }
                    $selected[] = array(
                        'course'  => $temp[1][$j],
                        'teacher' => strip_tags($temp[5][$j]),
                        'time'    => $this->deduplication($temp[7][$j]),
                        'place'   => $this->deduplication($temp[8][$j]),
                        'url'     => $temp[10][$j]
                    );
                }
            }

            //判断如果不不存在下一页则退出
            if (false !== stripos($http->content, 'nextno.gif')) {
                break;
            }
        }

        //返回结果集
        return $selected;
    }

    //选/退操作
    public function doAction($url)
    {
        //教务网登录
        $this->login();

        try {
            //获取第$i页的课程列表
            $http = new Http(array(
                CURLOPT_URL     => $this->baseUrl . $url,
                CURLOPT_COOKIE  => $this->cookies,
                CURLOPT_TIMEOUT => 10,
            ));
        } catch (\Exception $e) {
            throw new \Exception('网络异常，操作失败！', Config::RETURN_ERROR);
        }

        //正则结果
        $pattern = "/alert\('(.*?)'\);/";
        if (preg_match($pattern, $http->content, $temp)) {
            return $temp[1];
        } else {
            throw new \Exception('返回了未知结果', Config::RETURN_ERROR);
        }
    }
}