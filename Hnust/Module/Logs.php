<?php

namespace Hnust\Module;

use Hnust\Config;

class Logs extends Base
{
    //日志文件后缀
    protected $suffix = '.log';

    //获取日志内容
    protected function getContent($file)
    {
        $lines  = 1000;
        $result = '';
        $path   = Config::BASE_PATH . Config::LOGS_PATH . '/' . $file . $this->suffix;
        $handle = fopen($path, 'r');
        while ($handle && !feof($handle)) {
            $result = fgets($handle) . $result;
        }
        fclose($handle);
        return $result;
    }

    //获取日志列表
    protected function getList()
    {
        $result = array();
        $path   = Config::BASE_PATH . Config::LOGS_PATH . '/';
        $handle = opendir(Config::BASE_PATH . Config::LOGS_PATH);
        while ($handle && (false !== ($file = readdir($handle)))) {
            $suffix = strrchr(strtolower($file), $this->suffix);
            if ($suffix !== $this->suffix) {
                continue;
            }
            $result[] = rtrim($file, $this->suffix);
        }
        closedir($handle);
        return $result;
    }

    //显示日志列表
    public function logs()
    {
        $file  = \Hnust\input('file', null);
        $files = $this->getList();
        if (in_array($file, $files)) {
            echo '<pre>' . $this->getContent($file) . '</pre>';
        } else {
            foreach ($files as $file) {
                echo "<a href='/logs?file={$file}'>{$file}</a><br/>\n";
            }
        }
    }
}