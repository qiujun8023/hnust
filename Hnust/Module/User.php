<?php

namespace Hnust\Module;

class User extends Auth
{
    public function update()
    {
        return parent::updateUser();
    }
}