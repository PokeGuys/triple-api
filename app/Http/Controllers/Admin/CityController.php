<?php

namespace App\Http\Controllers\Admin;

use Auth;
use Log;
use Validator;
use App\Models\Country;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Dingo\Api\Exception\StoreResourceFailedException;

class CityController extends Controller
{
    use Helpers;

    public function create(Request $request) {
        $validator = Validator::make($request->all(), [
            'city' => 'required|unique:cities,name',
            'country' => 'required',
            'timezone' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
            'photo' => 'required',
            'description' => 'required',
        ]);
        if ($validator->fails()) {
            throw new StoreResourceFailedException($validator->errors()->first());
        }
        try {
            if (!$country = Country::where('name', $request->country)->first()) {
                $country = Country::create([
                    'name' => $request->name,
                ]);
            }
            $country->cities()->create([
                'name' => $request->city,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'timezone' => $request->timezone,
                'photo_url' => $request->photo,
                'description' => $request->description
            ]);
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->noContent();
    }
}