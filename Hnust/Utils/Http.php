<?php

namespace Hnust\Utils;

class Http
{

    public $content = '';
    public $headers = '';
    public $cookies = '';

    public function __construct($options)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt_array($ch, $options);
        //获取返回值
        $response = curl_exec($ch);
        //CURL错误
        if (curl_errno($ch)) {
            throw new \Exception('Curl error: ' . curl_error($ch));
        }

        //headers&content
        $headerSize    = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->headers = substr($response, 0, $headerSize);
        $this->content = substr($response, $headerSize);

        //cookies
        preg_match_all("/set\-cookie:([^;]*)/i", $this->headers, $temp);
        for ($i = 0; $i < count($temp[1]); $i++) {
            $this->cookies .= $temp[1][$i] . ";";
        }
        //关闭curl
        curl_close($ch);
    }

    //使用多线程+多进程更新数据
    public static function multi($url, $params, $times = 3)
    {
        $failures = array();
        //更新完成返回空,未完成返回未完成的个数
        if (count($params) == 0) {
            return 0;
        } else if ($times == 0) {
            return count($params);
        }

        //curl_multi初始化
        $mh = curl_multi_init();
        foreach ($params as $i => $item) {
            //初始化单个curl
            $conn[$i] = curl_init();
            curl_setopt($conn[$i], CURLOPT_URL, $url);
            curl_setopt($conn[$i], CURLOPT_POSTFIELDS, $item);
            curl_setopt($conn[$i], CURLOPT_RETURNTRANSFER, true);
            //将单个curl加入curl_multi
            curl_multi_add_handle ($mh, $conn[$i]);
        }
        //初始化
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        //循环，直到所有页面加载完成
        while ($active && $mrc == CURLM_OK) {
            while (curl_multi_exec($mh, $tactive) === CURLM_CALL_MULTI_PERFORM);
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        //关闭curl
        foreach ($params as $i => $item) {
            //判断结果,更新失败,加入再次更新
            $result = curl_multi_getcontent($conn[$i]);
            if ($result != 'success') {
                $failures[] = $item;
            }
            curl_close($conn[$i]);
        }
        curl_multi_close($mh);

        //递归更新
        if (!empty($failures)) {
            return self::multi($url, $failures, ($times - 1));
        } else {
            return 0;
        }
    }
}