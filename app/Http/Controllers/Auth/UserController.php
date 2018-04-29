<?php

namespace App\Http\Controllers\Auth;

use Auth;
use Cache;
use Log;
use Validator;
use Carbon\Carbon;
use App\Models\Tag;
use App\Transformers\UserTransformer;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        return $this->response->item($user, new UserTransformer(['include' => 'preferences']));
    }

    public function updateInfo(Request $request) {
        $info = $request->only('first_name', 'last_name', 'gender', 'age');
        $validator = Validator::make($info, [
            'first_name' => 'max:255',
            'last_name' => 'max:255',
            'gender' => 'in:M,F',
            //'income' => 'exists:income_groups,id',
            'age' => 'exists:age_groups,id',
        ]);
        if ($validator->fails()) {
            throw new StoreResourceFailedException($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $user = Auth::User();
            $fill = [];
            foreach ($info as $key => $value) {
                $fill[$key] = $value;
            }
            $user->fill($fill)->save();
            $user->forceFill([
                'updated_at' => Carbon::now()
            ])->save();
            DB::commit();
        } catch (\PDOException $e) {
            DB::rollback();
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->noContent();
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
            $preferences = json_decode($output, true);
            $user->tags()->detach();
            for ($i = 0; $i < 4; $i++) {
                $tag = Tag::where('tag', $preferences[$i]['key'])->first();
                $user->tags()->syncWithoutDetaching(['tag_id' => $tag->id]);
            }
            Cache::put("preference_user_{$user->id}", $user->tags, 60);
        } catch (\Exception $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->array($preferences);
    }

    public function updatePassword(Request $request) {
        $validator = Validator::make($info, [
            'oldPassword' => 'required|min:6',
            'password' => 'required|min:6|confirmed',
        ]);
        if ($validator->fails()) {
            throw new StoreResourceFailedException($validator->errors()->first());
        }
        try {
            $user = Auth::User();
            if (!Hash::check($request->oldPassword, $member->password)) throw new UnauthorizedHttpException(trans('auth.failed'));
            $member->forceFill(['password' => bcrypt($request->password)])->save();
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->noContent();
    }

    public function setPreference(Request $request)
    {
        throw new ServiceUnavailableHttpException('', trans('custom.implementation'));
    }
}
