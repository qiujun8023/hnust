<?php

namespace Hnust\Module;

use Hnust\Utils\Log;
use Hnust\Utils\Mysql;

class App extends Base
{
    protected function url($key)
    {
        // return 'http://7nj30o.com1.z0.glb.clouddn.com/' . $key;
        return 'https://o5rtvsgw3.qnssl.com/' . $key;
    }

    public function app()
    {
        $this->download();
    }

    public function latest()
    {
        return $this->stable();
    }

    public function stable()
    {
        $sql = "SELECT * FROM `app` WHERE `develop` = '0' ORDER BY `version` DESC, `time` DESC LIMIT 1";
        $result = Mysql::execute($sql);
        $this->data = $result[0];
        $this->data['url'] = $this->url($this->data['key']);
        return $this->data;
    }

    public function develop()
    {
        $sql = "SELECT * FROM `app` ORDER BY `version` DESC, `time` DESC LIMIT 1";
        $result = Mysql::execute($sql);
        $this->data = $result[0];
        $this->data['url'] = $this->url($this->data['key']);
        return $this->data;
    }

    public function download()
    {
        $data = $this->stable();
        header('location:' . $data['url']);
        exit;
    }

    //APP异常收集
    public function error()
    {
        $content = \Hnust\input('content');
        if (!empty($content)) {
            Log::file('android', $content);
        }
    }
}