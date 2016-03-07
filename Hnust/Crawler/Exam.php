<?php

namespace Hnust\Crawler;

use Hnust\Config;
use Hnust\Utils\Http;

class Exam extends Jwc
{
    //获取考试学期批次
    protected function getBatch()
    {
        $postData = 'xnxqh=&xqlb=';

        try {
            $http = new Http(array(
                CURLOPT_URL     => $this->baseUrl . 'jiaowu/kwgl/kwgl_xsJgfb_soso.jsp',
                CURLOPT_COOKIE  => $this->cookies,
                CURLOPT_TIMEOUT => 3,
            ));
        } catch (\Exception $e) {
            throw new \Exception('网络异常，考试安排批次获取失败！', Config::RETURN_ERROR);
        }

        $content = substr($http->content, strpos($http->content, '考试名称：'));
        $pattern = '<option value="(.*?)">';
        if (preg_match_all($pattern, $content, $temp) <= 1) {
            return false;
        }

        for($i = 0; $i < count($temp[1]); $i++) {
            $postData .= '&kwmc=' . $temp[1][$i];
        }

        return $postData;
    }

    //获取考试页面内容
    public function getExam()
    {
        //教务网登录
        $this->login();

        $exam = array();

        if (!($postData = $this->getBatch())) {
            return $exam;
        }

        $pattern = '/title="null" >.*?<\/td>.*?title="null" >(.*?)<\/td>.*?title="null" >(.*?)<\/td>.*?title="null" >(.*?)<\/td>.*?title="null" >(.*?)<\/td>.*?>(.*?)<\/td>.*?>(.*?)<\/td>/';
        for ($i = 1; $i < 12; $i++) {
            //获取第i页的内容
            try {
                $http = new Http(array(
                    CURLOPT_URL        => $this->baseUrl . 'kwsjglAction.do?method=sosoXsFb',
                    CURLOPT_POSTFIELDS => $postData . '&PageNum=' . $i,
                    CURLOPT_COOKIE     => $this->cookies,
                    CURLOPT_TIMEOUT    => 5,
                ));
            } catch (\Exception $e) {
                throw new \Exception('网络异常，考试安排获取失败！', Config::RETURN_ERROR);
            }

            $content = str_replace('&nbsp;', '', $http->content);
            //正则所有考试
            if (preg_match_all($pattern, $content, $temp)) {
                for ($j = 0; $j < count($temp[0]); $j++) {
                    $exam[] = array(
                        'course' => $temp[1][$j],
                        'begin'  => $temp[2][$j],
                        'end'    => $temp[3][$j],
                        'room'   => $temp[4][$j],
                        'seat'   => $temp[5][$j],
                        'ticket' => $temp[6][$j]
                    );
                }
            }
            if (false !== stripos($content, 'nextno.gif')) {
                break;
            }
        }
        return $exam;
    }
}