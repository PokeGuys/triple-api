<?php

namespace App\Http\Controllers;

use Cache;
use App\Models\City;
use App\Models\Attraction;
use App\Http\Controllers\Controller;
use App\Services\Wikipedia\SearchAPI;
use App\Services\Wikipedia\SummaryAPI;
use App\Services\Foursquare\DetailAPI;
use App\Transformers\AttractionTransformer;
use Carbon\Carbon;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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
        $limit = $request->limit ?? 30;
        $limit = $limit > 30 ? 30 : $limit;
        try {
            $city = Cache::remember("city_$id", 60, function () use ($id) {
                return City::find($id);
            });
            if (!$city) throw new NotFoundHttpException(trnas('notfound.city'));
            $attractions = Cache::remember("attractions_city_{$id}", 60, function() use ($city){
                return $city->attractions;
            });
        } catch (\PDOException $e) {
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->paginator($attractions->paginate($limit), new AttractionTransformer, ['key' => 'attractions']);
    }



    public function getAllAttractions(Request $request, $id)
    {
        $currentPage = $request->page ?? 0;
        $perPage = $request->limit ?? 10;

        try {
            $city = Cache::remember("city_$id", 60, function () use ($id) {
                return City::find($id);
            });
            if (!$city) throw new NotFoundHttpException(trnas('notfound.city'));
            $attractions = Cache::remember("all_attractions_city_{$id}", 60, function() use ($city){
                 return $city->attractions;
             });

         $collection = new Collection($attractions);
         $currentPageBlogResults = $collection->slice($currentPage * $perPage, $perPage)->all();
         $paginatedBlogResults= new LengthAwarePaginator($currentPageBlogResults, count($collection), $perPage);

        } catch (\PDOException $e) {
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        //return $this->response->item($attractions, new AttractionTransformer , ['key' => 'data']);
        return $this->response->paginator($paginatedBlogResults, new AttractionTransformer, ['key' => 'attractions']);
    }



    public function getHotels(Request $request, $id)
    {
        $currentPage = $request->page ?? 0;
        $perPage = $request->limit ?? 10;

        try {
            $city = Cache::remember("city_$id", 10, function () use ($id) {
                return City::find($id);
            });
            if (!$city) throw new NotFoundHttpException(trnas('notfound.city'));
            $attractions = Cache::remember("attractions_hotels_city_{$id}", 10, function() use ($city, $id){
                $tags = array("4bf58dd8d48988d1fa931735", "4bf58dd8d48988d1ee931735", "4bf58dd8d48988d1fb931735", "4bf58dd8d48988d12f951735");
                $condition = implode(" OR ", array_map(function($tag) {
                    return "JSON_CONTAINS(tags, '\"$tag\"')";
                }, $tags));
                return $city->attractions()->whereRaw("(".$condition.") AND city_id = ".$id)->get();
             });
             $collection = new Collection($attractions);
             $currentPageBlogResults = $collection->slice($currentPage * $perPage, $perPage)->all();
             $paginatedBlogResults= new LengthAwarePaginator($currentPageBlogResults, count($collection), $perPage);
        } catch (\PDOException $e) {
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->paginator($paginatedBlogResults, new AttractionTransformer, ['key' => 'attractions']);
    }



    public function getRestaurants(Request $request, $id)
    {
        $currentPage = $request->page ?? 0;
        $perPage = $request->limit ?? 10;

        try {
            $city = Cache::remember("city_$id", 10, function () use ($id) {
                return City::find($id);
            });
            if (!$city) throw new NotFoundHttpException(trnas('notfound.city'));
            $attractions = Cache::remember("attractions_restaurants_city_{$id}", 10, function() use ($city, $id){
                $tags = array("4bf58dd8d48988d1c4941735", "52e81612bcbc57f1066b79f8", "4bf58dd8d48988d1c7941735", "58daa1558bbb0b01f18ec1d3", "4bf58dd8d48988d16d941735", "4bf58dd8d48988d1e0931735", "52af3b773cf9994f4e043c03", "55a59bace4b013909087cb30", "52af3add3cf9994f4e043bf5", "52af3b6e3cf9994f4e043c02", "4bf58dd8d48988d1cb941735", "4bf58dd8d48988d1ce941735", "4bf58dd8d48988d10f941735", "52af0bd33cf9994f4e043bdd", "4bf58dd8d48988d1d2941735", "4bf58dd8d48988d1d3941735", "4bf58dd8d48988d1d1941735", "4bf58dd8d48988d111941735");
                $condition = implode(" OR ", array_map(function($tag) {
                    return "JSON_CONTAINS(tags, '\"$tag\"')";
                }, $tags));
                return $city->attractions()->whereRaw("(".$condition.") AND city_id = ".$id)->get();
             });

             $collection = new Collection($attractions);
             $currentPageBlogResults = $collection->slice($currentPage * $perPage, $perPage)->all();
             $paginatedBlogResults= new LengthAwarePaginator($currentPageBlogResults, count($collection), $perPage);
        } catch (\PDOException $e) {
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        //return $this->response->item($attractions, new AttractionTransformer, ['key' => 'data']);
        return $this->response->paginator($paginatedBlogResults, new AttractionTransformer, ['key' => 'attractions']);
    }



    public function getAttractions(Request $request, $id)
    {
        $currentPage = $request->page ?? 0;
        $perPage = $request->limit ?? 10;

        try {
            $city = Cache::remember("city_$id", 10, function () use ($id) {
                return City::find($id);
            });
            if (!$city) throw new NotFoundHttpException(trnas('notfound.city'));
            $attractions = Cache::remember("attractions_attractions_city_{$id}", 10, function() use ($city, $id){
                $tags = array("4bf58dd8d48988d1fa931735", "4bf58dd8d48988d1ee931735", "4bf58dd8d48988d1fb931735", "4bf58dd8d48988d12f951735", "4bf58dd8d48988d1c4941735", "52e81612bcbc57f1066b79f8", "4bf58dd8d48988d1c7941735", "58daa1558bbb0b01f18ec1d3", "4bf58dd8d48988d16d941735", "5109983191d435c0d71c2bb1", "4bf58dd8d48988d1e0931735", "4bf58dd8d48988d132951735", "52af3b773cf9994f4e043c03", "55a59bace4b013909087cb30", "52af3add3cf9994f4e043bf5", "52af3b6e3cf9994f4e043c02", "4bf58dd8d48988d1cb941735", "4bf58dd8d48988d1ce941735", "4bf58dd8d48988d10f941735", "52af0bd33cf9994f4e043bdd", "4bf58dd8d48988d1d2941735", "4bf58dd8d48988d1d3941735", "4bf58dd8d48988d1d1941735", "4bf58dd8d48988d111941735");
                $condition = implode(" AND ", array_map(function($tag) {
                    return "NOT JSON_CONTAINS(tags, '\"$tag\"')";
                }, $tags));
                 return $city->attractions()->whereRaw("(".$condition.") AND city_id = ".$id)->get();
             });

             $collection = new Collection($attractions);
             $currentPageBlogResults = $collection->slice($currentPage * $perPage, $perPage)->all();
             $paginatedBlogResults= new LengthAwarePaginator($currentPageBlogResults, count($collection), $perPage);

        } catch (\PDOException $e) {
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->paginator($paginatedBlogResults, new AttractionTransformer, ['key' => 'attractions']);
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
                throw new NotFoundHttpException(trans('notfound.attraction'));
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
                    $keyword = trim(preg_replace("/\p{Han}+/u", '', !empty($bestName) ? $bestName : $info->name));
                    if (!empty($keyword)) {
                        $result = $searchAPI->fetch($keyword);
                        if (!isset($result->error)) {
                            $summaryAPI = new SummaryAPI();
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
                    'rating_count' => $info->ratingSignals ?? 0,
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
