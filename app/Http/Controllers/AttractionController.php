<?php

namespace App\Http\Controllers;

use App\Models\Attraction;
use App\Transformers\AttractionTransformer;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class AttractionController extends Controller
{
    use Helpers;

    public function getRows()
    {
        try {
            $attractions = Attraction::all();
        } catch (\PDOException $e) {
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->collection($attractions, new AttractionTransformer);
    }

    public function getInfo($id)
    {
        try {
            $attraction = Attraction::find($id);
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->item($attraction, new AttractionTransformer([
            'include' => [
                'comments'
            ]
        ]));
    }
}