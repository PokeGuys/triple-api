<?php

namespace App\Http\Controllers\Auth;

use Auth;
use Log;
use Validator;
use App\Transformers\UserTransformer;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Dingo\Api\Exception\StoreResourceFailedException;

class UserController extends Controller
{
    use Helpers;

    public function getInfo(Request $request)
    {
        try {
            $user = Auth::User();
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->item($user, new UserTransformer)->addMeta('token', $request->token);
    }
}