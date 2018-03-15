<?php

namespace App\Http\Controllers;

use Cache;
use Request;
use App\Models\Attraction;
use App\Http\Controllers\Controller;
use App\Services\Wikipedia\SearchAPI;
use App\Services\Wikipedia\SummaryAPI;
use App\Services\Foursquare\DetailAPI;
use App\Transformers\AttractionTransformer;
use Carbon\Carbon;
use Dingo\Api\Routing\Helpers;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

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
    public function getRows(Request $request, $id)
    {
        try {
            $city = Cache::remember("city_$id", 60, function () use ($id) {
                return Ctiy::find($id);
            });
            if (!$city) throw new NotFoundHttpException(trnas('notfound.city'));
            $attractions = Cache::remember("attractions_city_{$id}", 60, function() {
                return $city->attractions;
            });
        } catch (\PDOException $e) {
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->paginator($attractions->paginate(30), new AttractionTransformer, ['key' => 'data']);
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
            $attraction = Cache::remember("attraction_$id", 60, function() use ($id) {
                return Attraction::find($id);
            });
            if (!$attraction) {
                throw new NotFoundHttpException(trans('notfound.attracion'));
            }
            if (!isset($attraction->description) || Carbon::now()->diffInDays($attraction->updated_at) > 14) {
                $api = new DetailAPI();
                $info = $api->fetch($attraction->place_id);
                $bestName = $info->venueChains[0]->bestName->name ?? '';
                $photos = $info->photos->groups[0]->items ?? [];
                $existedPhotos = $attraction->photos;
                $totalNum = 0;
                if (isset($info->description)) {
                    $description = $info->description;
                } else {
                    $searchAPI = new SearchAPI();
                    $summaryAPI = new SummaryAPI();
                    $keyword = trim(preg_replace("/\p{Han}+/u", '', !empty($bestName) ? $bestName : $info->name));
                    if (!empty($keyword)) {
                        $result = $searchAPI->fetch($keyword);
                        if (!isset($result->error)) {
                            $summary = $summaryAPI->fetch($result->title);
                            if (!isset($summary->error)) {
                                $description = $summary;
                            }
                        }
                    }
                }
                foreach ($photos as $photo) {
                    $photo = $photo->prefix.'original'.$photo->suffix;
                    if (!in_array($photo, $existedPhotos)) {
                        $existedPhotos[] = $photo;
                        $totalNum++;
                    }
                }
                $attraction->increment('photo_count', $totalNum);
                $attraction->fill([
                    'name' => $info->name,
                    'local_name' => $bestName,
                    'description' => $description ?? '',
                    'website' => $info->website ?? '',
                    'rating' => $info->rating ?? 0,
                    'website' => $info->url ?? '',
                    'phone' => $info->contact->phone ?? '',
                    'price_level' => $info->price->tier ?? 0,
                    'photos' => $existedPhotos,
                    'address' => join(' ', $info->location->formattedAddress),
                    'price_level' => $info->price->tier ?? 0,
                ])->save();
                Cache::put("attraction_$id", $attraction, 60);
            }
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->item($attraction, new AttractionTransformer([
            'include' => 'comments'
        ]));
    }
}
