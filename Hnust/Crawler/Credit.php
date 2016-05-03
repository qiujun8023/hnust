<?php

namespace Hnust\Crawler;

use Hnust\Config;
use Hnust\Utils\Http;

class Credit extends Jwc
{
    //获取学分绩点
    public function getCredit()
    {
        //教务网登录
        $this->login();

        //构造正则表达式
        $pattern = '/';
        for ($i = 0; $i < 6; $i++) {
            $pattern .= '<td height="23"  style="text-overflow:ellipsis; white-space:nowrap; overflow:hidden;" width="\d{1,4}" title="null" >(.{1,80})<\/td>';
        }
        $pattern .= '<\/tr>/';

        $credit = array();
        for ($i = 1; $i < 30; $i++) {
            //获取第i页的内容
            try {
                $http = new Http(array(
                    CURLOPT_URL        => $this->baseUrl . 'xszqcjglAction.do?method=toXfjdList',
                    CURLOPT_POSTFIELDS => 'PageNum=' . $i,
                    CURLOPT_COOKIE     => $this->cookies,
                    CURLOPT_TIMEOUT    => 5,
                ));
            } catch (\Exception $e) {
                throw new \Exception('网络异常，学分绩点获取失败', Config::RETURN_ERROR);
            }

            //正则所有学分绩点
            if (preg_match_all($pattern, $http->content, $temp)) {
                for ($j = 0; $j < count($temp[0]); $j++) {
                    $resit = (stripos($temp[4][$j], '*') !== false) ? true : false;
                    $temp[4][$j] = str_replace('*', '', $temp[4][$j]);
                    $credit[] = array(
                        'id'     => count($credit),
                        'course' => $temp[2][$j],
                        'credit' => $temp[3][$j],
                        'score'  => $temp[4][$j],
                        'resit'  => $resit,
                        'point'  => $temp[5][$j],
                        'gpa'    => $temp[6][$j]
                    );
                }
            }
            if (false !== stripos($content, 'nextno.gif')) {
                break;
            }
        }
        return $credit;
    }
}