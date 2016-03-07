<?php

namespace Hnust\Crawler;

use Hnust\Config;

class VCode
{
    protected $imgInfo;
    protected $keyData;

    public function __construct($imgInfo, $keyData)
    {
        $this->imgInfo = $imgInfo;
        $this->keyData = $keyData;
    }

    public function getKeys($imgPath)
    {
        if (!($res = @imagecreatefromjpeg($imgPath))) {
            return '';
        }

        //提取文字部分图片
        $wordData = array();
        for ($i = 0; $i < $this->imgInfo['wordNum']; $i++) {
            $wordData[$i] = '';
            $x = ($i * ($this->imgInfo['wordWidth'] + $this->imgInfo['wordSpace'])) + $this->imgInfo['offsetWidth'];
            $y = $this->imgInfo['offsetHeight'];
            for ($h = $y; $h < ($this->imgInfo['offsetHeight'] + $this->imgInfo['wordHeight']); $h++) {
                for ($w = $x; $w < ($x + $this->imgInfo['wordWidth']); $w++) {
                    $rgb = imagecolorsforindex($res, imagecolorat($res, $w, $h));
                    $wordData[$i] .= ($rgb['red'] < 127 || $rgb['green'] < 127 || $rgb['blue'] < 127)? '1':'0';
                }
            }
        }

        $keys = '';
        //相似度比较
        foreach ($wordData as $wordStr) {
            $maxPercent = 0.0;
            foreach ($this->keyData as $k => $v) {
                $percent = 0.0;
                similar_text($v, $wordStr, $percent);
                if ($percent > $maxPercent) {
                    $maxPercent = $percent;
                    $key = $k;
                }
            }
            $keys .= $key;
        }

        return $keys;
    }
}