<?php

namespace App\Http\Controllers;

use Cache;
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

/**
 * @Resource("System")
 */
class SystemController extends Controller
{
    use Helpers;

    /**
     * Show system property
     *
     * @Get("/system/property")
     * @Versions({"v1"})
     * @Response(200, body={"income":{{"id":0,"description":"Below 10000"},{"id":1,"description":"10000-14999"}},"age":{{"id":0,"description":"Under 12"},{"id":1,"description":"13-17"}},"city":{{"id":1,"country":"Taiwan","name":"Taipei","photo":""}}})
     */
    public function getProperty()
    {
        return Cache::remember('property', 60, function() {
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
        });
    }
}