<?php

namespace Hnust\Module;

use Hnust\Utils\Mysql;

class App extends Base
{
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
        return $this->data = $result[0];
    }

    public function develop()
    {
        $sql = "SELECT * FROM `app` ORDER BY `version` DESC, `time` DESC LIMIT 1";
        $result = Mysql::execute($sql);
        return $this->data = $result[0];
    }

    public function download()
    {
        $data = $this->stable();
        header('location:' . $data['url']);
    }
}