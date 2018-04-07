<?php

namespace App\Http\Controllers;

use Log;
use Validator;
use App\Models\Attraction;
use App\Models\AttractionComment;
use App\Models\User;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Dingo\Api\Routing\Helpers;
use Dingo\Api\Exception\StoreResourceFailedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * @Resource("Attraction", uri="/attraction")
 */
class AttractionCommentController extends Controller
{
    use Helpers;


    //success status = 201 Created
    public function addComment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'attraction_id' => 'required',
            'user_id' => 'required',
            'title' => 'required',
            'content' => 'required',
            'photos' => 'required'
        ]);
        if ($validator->fails())
        {
            throw new StoreResourceFailedException($validator->errors()->first());
        }
        try {
            DB::beginTransaction();

            $attraction_comment = AttractionComment::create([
                'attraction_id' => $request->attraction_id,
                'user_id' => $request->user_id,
                'title' => $request->title,
                'content' => $request->content,
                'photos' => $request->photos
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans($e));
        }
        return $this->response->created();
    }
}
