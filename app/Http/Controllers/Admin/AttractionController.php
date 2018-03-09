<?php

namespace App\Http\Controllers\Admin;

use Auth;
use Log;
use Validator;
use App\Transformers\UserTransformer;
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

    public function updateAttraction(Request $request, $id) {
        if ($validattor->fails())
            throw new StoreResourceFailedException($validator->errors()->first());
    }
}