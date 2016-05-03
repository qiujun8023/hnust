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
        $result  = array();
        $path    = Config::BASE_PATH . Config::LOGS_PATH . '/' . $file . $this->suffix;
        $handle  = fopen($path, 'r');
        $pattern = "/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/";
        while ($handle && !feof($handle)) {
            $temp = fgets($handle);
            if (preg_match($pattern, $temp)) {
                array_unshift($result, $temp);
            } elseif (empty($result)) {
                $result[0] = $temp;
            } elseif (!empty($temp)) {
                $result[0] .= $temp;
            }
        }
        fclose($handle);
        return implode('', $result);
    }

    //获取日志列表
    protected function getList()
    {
        $result = array();
        $path   = Config::BASE_PATH . Config::LOGS_PATH . '/';
        $handle = opendir($path);
        while ($handle && (false !== ($file = readdir($handle)))) {
            $suffix = strrchr(strtolower($file), $this->suffix);
            if ($suffix !== $this->suffix) {
                continue;
            }
            $result[] = substr($file, 0, -strlen($this->suffix));
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