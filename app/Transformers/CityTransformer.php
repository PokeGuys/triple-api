<?php

namespace App\Transformers;

use App\Models\City;
use Cache;

class CityTransformer extends TransformerAbstract
{
    public function __construct($fields = null)
    {
        $this->fields = $fields;
    }

    public function transform(City $city)
    {
        $this->model = $city;
        $country = Cache::remember("country_{$city->country_id}", 60, function () use ($city) {
            return $city->country;
        });
        return $this->transformWithField([
            'id' => $city->id,
            'country' => $country->name,
            'name' => $city->name,
            'description' => $city->description,
            'photo' => $city->photo_url
        ]);
    }
}