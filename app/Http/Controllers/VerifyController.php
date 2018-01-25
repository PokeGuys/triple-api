<?php

namespace App\Http\Controllers;

use Auth;
use Log;
use Validator;
use Carbon\Carbon;
use App\Models\Verification;
use App\Mail\VerificationMail;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Dingo\Api\Exception\StoreResourceFailedException;

class VerifyController extends Controller
{
    use Helpers;

    public function confirmation($token)
    {
        try {
            $verify = Verification::where('token', $token)->first();
            if (!$verify) throw new UnprocessableEntityHttpException(trans('custom.notfound.token'));
            if ($this->token_is_expired($verify->updated_at, 24)) throw new UnprocessableEntityHttpException(trans('custom.invalid.token'));
            if ($verify->user->status !== 0) throw new ConflictHttpException(trans('custom.member.activated'));
            $verify->user->forceFill(['status' => 1])->save();
            $verify->delete();
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->noContent();
    }

    public function resendConfrimation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|min:6|max:20',
            'password' => 'required|min:6',
            'email' => 'required|email|max:255|unique:members,email'
        ]);
        try {
            if ($validator->fails()) throw new UnprocessableEntityHttpException($validator->errors()->first());
            if ($member = !valid_user($request->username, $request->password)) throw new NotFoundHttpException(trans('custom.invalid.password'));
            if ($member->status !== 0) throw new ConflictHttpException(trans('custom.member.activated'));
            if ($this->token_last_request($member->verification()->updated_at, 15)) throw new UnprocessableEntityHttpException(trans('custom.limit'));
            $token = Str::random(40);
            $member->verification()->forceFill([
                'token' => $token
            ])->save();
            $member->forceFill([
                'email' => $request->email
            ])->save();
            Mail::to($request->email)->queue(new VerificationMail($token));
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
    }

    private function valid_user($username, $password) {
        $member = Member::where('username', $username)->first();
        if (!$member) return false;
        if (!Hash::check($request->password, $member->password)) return false;

        return $member;
    }

    private function token_is_expired($timestamp, $hours) {
        return Carbon::now()->diffInHours($timestamp) >= $hours;
    }

    private function token_last_request($timestamp, $minute) {
        return Carbon::now()->diffInMinutes($timestamp) <= $minute;
    }
}