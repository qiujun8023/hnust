<?php

namespace Hnust\Analyse;

use Hnust\Config;
use Hnust\Utils\Cache;

Class Download
{
    protected $uid;
    protected $cache;

    public function __construct($uid)
    {
        $this->uid = $uid;
        $this->cache = new Cache('download');
    }

    public function rewrite($rand)
    {
        header('Location:' . Config::WEB_PATH . '/tools/download?file=' . $rand);
        exit;
    }

    public function set($filename)
    {
        $rand = \Hnust\randStr(8);
        $path = Config::BASE_PATH . Config::TEMP_PATH . '/' . $rand;
        $info = array(
            'uid'      => $this->uid,
            'rand'     => $rand,
            'path'     => $path,
            'filename' => $filename,
            'time'     => time()
        );
        $this->cache->set($rand, $info, 604800);
        return $info;
    }

    public function get($rand)
    {
        $info = $this->cache->get($rand);
        if (empty($info) || ($info['uid'] != $this->uid)) {
            return false;
        }
        if (!is_file($info['path'])) {
            return false;
        }
        header("Content-type: application/octet-stream");
        header("Content-Disposition: attachment; filename=" . $info['filename']);
        header("Content-Length: ". filesize($info['path']));
        readfile($info['path']);
        exit;
    }
}