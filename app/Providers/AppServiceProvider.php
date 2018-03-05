<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Auth::provider('cache', function ($app, array $config) {
            return new CachedUserProvider($app['hash'], config('auth.providers.users.model'));
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Collection::macro('paginate', function( $perPage, $total = null, $page = null, $pageName = 'page' ) {
            $page = $page ?: LengthAwarePaginator::resolveCurrentPage( $pageName );
                
            return new LengthAwarePaginator( $this->forPage( $page, $perPage ), $total ?: $this->count(), $perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]);
        });
    }
}
