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
    $api->get('/system/property', 'SystemController@getProperty');
    $api->group(['prefix' => 'member'], function($api) {
        $api->post('/register', 'UserController@register');
        $api->post('/authentication', 'UserController@authenticate');
        $api->post('/password/forget', 'UserController@forgetPassword');
        $api->post('/password/reset', 'UserController@resetPassword');
        $api->post('/resend/email', 'VerifyController@resendConfrimation');
        $api->get('/confirm/{token}', 'VerifyController@confirmation');
    });
    $api->group(['prefix' => 'city'], function($api) {
        $api->get('/{id}/attractions', 'AttractionController@getRows');
        $api->get('/{id}/attractions/all', 'AttractionController@getAllAttractions');
        $api->get('/{id}/attractions/hotels', 'AttractionController@getHotels');
        $api->get('/{id}/attractions/restaurants', 'AttractionController@getRestaurants');
        $api->get('/{id}/attractions/attractions', 'AttractionController@getAttractions');
    });
    $api->group(['prefix' => 'attraction'], function($api) {
        $api->get('/{id}', 'AttractionController@getInfo');
    });
    $api->group(['prefix' => 'survey'], function($api) {
        $api->post('/', 'SurveyController@input');
    });

    // Auth Required
    $api->group(['middleware' => 'auth:api'], function($api) {
        $api->group(['prefix' => 'city'], function($api) {
            $api->get('/{id}/attraction/bookmarks', 'Auth\AttractionController@getBookmarks');
        });
        $api->group(['prefix' => 'member'], function($api) {
            $api->get('/preference', 'Auth\UserController@generatePreference');
            $api->put('/preference', 'Auth\UserController@setPreference');
            $api->get('/info', 'Auth\UserController@getInfo');
            $api->put('/info', 'Auth\UserController@updateInfo');
        });
        $api->group(['prefix' => 'attraction'], function($api) {
            $api->post('/{id}/review', 'Auth\AttractionController@placeReview');
            $api->post('/{id}/bookmark', 'Auth\AttractionController@setBookmark');
        });
        $api->group(['prefix' => 'trip'], function($api) {
            $api->get('/', 'Auth\TripController@listTrip');
            $api->get('/ended', 'Auth\TripController@listEndedTrip');
            $api->get('/{id}', 'Auth\TripController@listTripByUser');
            $api->get('/search/{keyword}', 'Auth\TripController@listTripByKeyword');
            $api->post('/', 'Auth\TripController@createTrip');
            $api->put('/{id}', 'Auth\TripController@editTrip');
            $api->delete('/{id}', 'Auth\TripController@deleteTrip');
            $api->get('/{id}/article', 'Auth\TripController@generateArticle');
            $api->get('/bookmarks', 'Auth\TripController@getBookmarks');
            $api->post('/{id}/bookmark', 'Auth\TripController@setBookmark');
        });
        $api->group(['prefix' => 'itinerary'], function($api) {
            $api->post('/{id}', 'Auth\TripController@assignItineraryItem');
            $api->put('/{id}', 'Auth\TripController@editItineraryItem');
            $api->delete('/{id}', 'Auth\TripController@deleteItineraryItem');
        });
        $api->group(['prefix' => 'survey'], function($api) {
            $api->get('/', 'SurveyController@export');
        });
    });
});
