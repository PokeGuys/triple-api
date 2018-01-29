<?php

namespace App\Http\Controllers;

use Log;
use App\Models\City;
use App\Models\AgeGroup;
use App\Models\IncomeGroup;
use App\Transformers\CityTransformer;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Serializer\DataArraySerializer;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class SystemController extends Controller
{
    use Helpers;

    public function getProperty()
    {
        try {
            $manager = new Manager();
            $manager->setSerializer(new DataArraySerializer());
            $income = IncomeGroup::all();
            $age = AgeGroup::all();
            $city = City::all();
            $resource = new Collection($city, new CityTransformer, 'city');
            $formatted_city = $manager->createData($resource)->toArray();
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->array([
            'income' => $income,
            'age' => $age,
            'city' => $formatted_city['data']
        ]);
    }
}