<?php

namespace App\Transformers;

use App\Models\City;

class CityTransformer extends TransformerAbstract
{
    public function __construct($fields = null)
    {
        $this->fields = $fields;
    }

    public function transform(City $city)
    {
        $this->model = $city;
        return $this->transformWithField([
            'id' => $city->id,
            'country' => $city->country->name,
            'name' => $city->name,
            'photo' => $city->photo_url
        ]);
    }
}