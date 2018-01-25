<?php

namespace App\Http\Traits;

use Dingo\Api\Contract\Http\RateLimit\Throttle;
use Illuminate\Container\Container;

class GroupThrottle implements Throttle
{
    public function match(Container $container)
    {
        return true;
    }

    public function getExpires()
    {
        return 1;
    }

    public function getLimit()
    {
        return 1000;
    }
}
