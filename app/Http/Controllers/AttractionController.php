<?php

namespace App\Http\Controllers;

use App\Models\Attraction;
use App\Transformers\AttractionTransformer;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Resource("Attraction", uri="/attraction")
 */
class AttractionController extends Controller
{
    use Helpers;

    /**
     * Get attraction list
     *
     * @Get("/")
     * @Versions({"v1"})
     * @Response(200, body={ "data": { { "id": 1, "name": "Taipei 101 Observatory", "phone": "+886 2 8101 8898", "email": null, "website": "http://www.taipei-101.com.tw/tw/observatory-info.aspx", "address": "110, Taiwan, Taipei City, Xinyi District, Section 5, Xinyi Road, 7號89樓", "tags": { "point_of_interest", "establishment" }, "latitude": "25.0336076", "longitude": "121.5647587", "rating": "4.30", "comment_count": 0, "photo_count": 0, "created_at": 1518970328, "updated_at": 1518970329 }, } })
     */
    public function getRows()
    {
        try {
            $attractions = Attraction::all();
            if (!$attractions) {
                throw new NotFoundHttpException(trans('notfound.attracions'));
            }
        } catch (\PDOException $e) {
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->collection($attractions, new AttractionTransformer);
    }


    /**
     * Get attraction details
     *
     * @Get("/{id}")
     * @Versions({"v1"})
     * @Parameters({
     *     @Parameter("id", type="integer", required=true, description="The id of attraction to view."),
     * })
     * @Response(200, body={ "data": { "id": 1, "name": "Taipei 101 Observatory", "phone": "+886 2 8101 8898", "email": null, "website": "http://www.taipei-101.com.tw/tw/observatory-info.aspx", "address": "110, Taiwan, Taipei City, Xinyi District, Section 5, Xinyi Road, 7號89樓", "tags": { "point_of_interest", "establishment" }, "latitude": "25.0336076", "longitude": "121.5647587", "rating": "4.30", "comment_count": 0, "photo_count": 0, "created_at": 1518970328, "updated_at": 1518970329, "comments": { "data": {} } } })
     */
    public function getInfo($id)
    {
        try {
            $attraction = Attraction::find($id);
            if (!$attraction) {
                throw new NotFoundHttpException(trans('notfound.attracion'));
            }
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