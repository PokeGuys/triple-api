<?php

namespace App\Providers;

use Illuminate\Auth\EloquentUserProvider;

class CachedUserProvider extends EloquentUserProvider {

    public function retrieveById($identifier)
    {
        return \Cache::remember('user:'.$identifier, 5, function() use ($identifier) {
            return parent::retrieveById($identifier);
        });
    }

}