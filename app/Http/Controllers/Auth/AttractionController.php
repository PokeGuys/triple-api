<?php

namespace App\Http\Controllers\Auth;

use Auth;
use Cache;
use Log;
use Validator;
use App\Models\City;
use App\Models\Attraction;
use App\Models\AttractionComment;
use App\Transformers\AttractionTransformer;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Dingo\Api\Exception\StoreResourceFailedException;

class AttractionController extends Controller
{
    use Helpers;

    public function getBookmarks($id) {
        try {
            if (
                !Cache::remember("city_{$id}", 60, function () use ($id) {
                    return City::find($id);
                })
            ) {
                throw new NotFoundHttpException(trans('notfound.city'));
            }
            $user = Auth::getUser();
            $attractions = Cache::remember("bookmark_attraction_user_{$user->id}", 20, function() use ($user) {
                return $user->bookmarkedAttraction;
            })->where('city_id', $id);
            if (!$attractions) {
                throw new NotFoundHttpException(trans('notfound.attractions'));
            }
        } catch (Exception $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->collection($attractions, new AttractionTransformer);
    }

    public function setBookmark($id) {
        try {
            if (
                !Cache::remember("attraction_$id", 60, function() use ($id) {
                    return Attraction::find($id);
                })
            ) {
                throw new NotFoundHttpException(trans('notfound.attracion'));
            }
            $user = Auth::getUser();
            $user->bookmarkedAttraction()->syncWithoutDetaching(['attraction_id' => $id]);
            Cache::put("bookmark_attraction_user_{$user->id}", $user->bookmarkedAttraction, 20);
        } catch (Exception $id) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->noContent();
    }

    public function placeReview(Request $request, $id) {
        $validator = Validator::make($request->all(), [
            'title'   => 'required|min:1|max:255',
            'message' => 'required|min:1',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            throw new StoreResourceFailedException($validator->errors()->first());
        }
        try {
            DB::beginTransaction();

            $attraction = Cache::remember("attraction_$id", 60, function() use ($id) {
                return Attraction::find($id);
            });
            if (!$attraction) {
                throw new NotFoundHttpException(trans('notfound.attracion'));
            }
            $user = Auth::getUser();
            // $attraction->reviews()->create([
            //     'user_id' => $user->id,
            //     'title' => $request->title,
            //     'message' => $request->message,
            //     'rating' => $request->rating,
            // ]);
            AttractionComment::create([
              'attraction_id' => $id,
              'user_id' => $user->id,
              'title' => $request->title,
              'content' => $request->message,
              'rating' => $request->rating,
              'photos' => []
            ]);
            $newRating = $attraction->rating + (($request->rating - $attraction->rating) / ($attraction->rating_count + 1));
            $attraction->increment('comment_count');
            $attraction->increment('rating_count');
            $attraction->forceFill([
                'rating' => $newRating,
            ])->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->created();
    }
}
