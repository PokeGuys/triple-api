<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

$api = app('Dingo\Api\Routing\Router');
$api->version('v1', [
    'middleware' => ['api.throttle'],
    'throttle' => 'App\Http\Traits\GroupThrottle',
    'namespace' => 'App\Http\Controllers'
], function ($api) {
    // Public
    $api->get('system/property', 'SystemController@getProperty');
    $api->group(['prefix' => 'member'], function($api) {
        $api->post('register', 'UserController@register');
        $api->post('authentication', 'UserController@authenticate');
        $api->post('password/forget', 'UserController@forgetPassword');
        $api->post('password/reset', 'UserController@resetPassword');
        $api->post('resend/email', 'VerifyController@resendConfrimation');
        $api->get('confirm/{token}', 'VerifyController@confirmation');
    });
    $api->group(['prefix' => 'attraction'], function($api) {
        $api->get('/', 'AttractionController@getRows');
        $api->get('/{id}', 'AttractionController@getInfo');
    });

    // Auth Required
    $api->group(['middleware' => 'auth:api'], function($api) {
        $api->group(['prefix' => 'member'], function($api) {
            $api->get('/info', 'Auth\UserController@getInfo');
        });
        $api->group(['prefix' => 'trip'], function($api) {
            $api->get('/', 'TripController@listTrip');
            $api->get('/{id}', 'TripController@listTripByUser');
            $api->get('search/{keyword}', 'TripController@listTripByKeyword');
            $api->post('/', 'TripController@createTrip');
            $api->put('/{id}', 'TripController@editTrip');
            $api->delete('/{id}', 'TripController@deleteTrip');
        });
        $api->group(['prefix' => 'itinerary'], function($api) {
            $api->post('/', 'TripController@createTripItinerary');
            $api->put('/{id}', 'TripController@editTripItinerary');
            $api->delete('/{id}', 'TripController@deleteTripItinerary');
        });
        $api->group(['prefix' => 'item'], function($api) {
            $api->post('/', 'TripController@assignItineraryItem');
            $api->put('/{id}', 'TripController@editItineraryItem');
            $api->delete('/{id}', 'TripController@deleteItineraryItem');
        });
    });
});
