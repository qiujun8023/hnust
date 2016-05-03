<?php

namespace Hnust\Crawler;

use Hnust\Config;
use Hnust\Utils\Http;

class Schedule extends Jwc
{
    //获取正则结果
    public function getSchdule($sid, $term)
    {
        $this->login();

        try {
            //获取课表内容
            $http = new Http(array(
                CURLOPT_URL        => $this->baseUrl . 'jiaowu/pkgl/llsykb/llsykb_kb.jsp',
                CURLOPT_POSTFIELDS => 'isview=1&type=xs0101&xnxq01id=' . $term . '&xs0101id=' . $sid,
                CURLOPT_COOKIE     => $this->cookies,
                CURLOPT_TIMEOUT    => 5,
            ));
        } catch (\Exception $e) {
            throw new \Exception('课表获取失败', Config::RETURN_ERROR);
        }

        $content = str_replace(
            array('&nbsp;', '<font color="red">★</font>', '------------------------------<br>'),
            array('', '★', ''),
            $http->content
        );

        //正则课表备注
        preg_match('/<td colspan="7" style="color: red;">(.*?)<\/td>/s', $content, $temp);
        $schedule = array(
            'remarks' => empty($temp)? '':trim($temp[1])
        );

        //分割课表内容
        $pattern = '/<div id="(?:.{34})-2"\s*style="display: none;" class="kbcontent"\s*>(.*?)<\/div>/';
        preg_match_all($pattern, $content, $content);
        if (empty($content)) {
            continue;
        }

        //正则课表内容
        $flag = false;
        $pattern = "/(.*?)<br\/><font title='老师'>(.*?)<\/font><br\/><font title='周次\(节次\)'>(.*?)<br\/>(.*?)(?:\(全部\))?(?:\[.*?\])?<\/font>(?:<br\/><font title='教室'>(.*?)<\/font><br\/>)?/";
        for ($i = 1; $i <= 7; $i++) {
            for ($j = 1; $j <= 5; $j++) {
                $schedule[$i][$j] = array();
                preg_match_all($pattern, $content[1][$i+$j*7-8], $temp);
                for ($k = 0; $k < count($temp[0]); $k++) {
                    $flag = empty($temp[0][$k])? $flag:true;
                    $schedule[$i][$j][] = array(
                        'course'    => $temp[1][$k],
                        'teacher'   => $temp[2][$k],
                        'class'     => $temp[3][$k],
                        'time'      => $temp[4][$k] . '周',
                        'classroom' => empty($temp[5][$k])? '[暂无教室]':$temp[5][$k]
                    );
                }
            }
        }

        if (false === $false) {
            throw new \Exception('课表获取失败', Config::RETURN_ERROR);
        } else {
            return $schedule;
        }
    }
}