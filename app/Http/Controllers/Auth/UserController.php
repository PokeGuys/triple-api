<?php

namespace App\Http\Controllers\Auth;

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
        return $this->response->item($user, new UserTransformer);
    }

    public function generatePerference(Request $request)
    {
        try {
            $user = Auth::User();
            $path = storage_path('/predict/predict.py');
            $process = new Process("python $path {$user->age} {$user->gender}");
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            $output = $process->getOutput();
        } catch (\Exception $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->array(json_decode($output, true));
    }
}