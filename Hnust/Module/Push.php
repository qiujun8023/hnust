<?php

namespace Hnust\Module;

use Hnust\Config;

class Push extends Auth
{
    public function add()
    {
        $uid     = \Hnust\input('uid/d', $this->uid);
        $type    = \Hnust\input('type/d', 0);
        $title   = \Hnust\input('title', '');
        $content = \Hnust\input('content', '');
        $success = \Hnust\input('success', '');
        $push = new \Hnust\Analyse\Push();
        $this->data = $push->add($uid, $type, $title, $content, $success);
        if (!$this->data){
            $this->msg  = 'Something Wrong';
        }
    }

    public function achieve()
    {
        $id = \Hnust\input('id/d', -1);
        $push = new \Hnust\Analyse\Push();
        if ($push->achieve($this->uid, $id)) {
            $this->code = Config::RETURN_NORMAL;
            $this->msg  = 'Change Success';
        } else {
            $this->msg  = 'Something Wrong';
        }
    }

    public function fetch()
    {
        $push = new \Hnust\Analyse\Push();
        $this->data = $push->fetch($this->uid);
    }
}