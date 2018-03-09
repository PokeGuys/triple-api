<?php

namespace App\Http\Controllers\Auth;

use Auth;
use Log;
use Validator;
use App\Transformers\AttractionTransformer;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Dingo\Api\Exception\StoreResourceFailedException;

class AttractionController extends Controller
{
    use Helpers;

    public function getBookmarks(Request $request, $id) {
        try {
            $user = Auth::getUser();
            $attractions = Cache::remember("bookmark_attraction_user_{$user->id}", 20, function() use ($user) {
                return $user->bookmarkedAttraction;
            });
            if (!$attractions) {
                throw new NotFoundHttpException(trans('notfound.attractions'));
            }
        } catch (Exception $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->collection($attractions, new AttractionTransformer);
    }
}