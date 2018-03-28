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

    public function updateInfo(Request $request) {
        $info = $request->only('first_name', 'last_name', 'gender', 'income', 'age');
        $validator = Validator::make($info, [
            'first_name' => 'max:255',
            'last_name' => 'max:255',
            'gender' => 'in:M,F',
            'income' => 'exists:income_groups,id',
            'age' => 'exists:age_groups,id',
        ]);
        if ($validator->fails()) {
            throw new StoreResourceFailedException($validator->errors()->first());
        }
        try {
            $user = Auth::User();
            foreach ($info as $key => $value) {
                if (empty($member->{$key})) $fill[$key] = $value;
            }
            $member->fill($fill)->save();
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->item($user, new UserTransformer);
    }

    public function generatePreference(Request $request)
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

    public function setPreference(Request $request)
    {
        throw new ServiceUnavailableHttpException('', trans('custom.implementation'));
    }
}