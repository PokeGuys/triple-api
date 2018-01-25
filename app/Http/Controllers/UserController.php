<?php

namespace App\Http\Controllers;

use Auth;
use Log;
use Validator;
use Carbon\Carbon;
use App\Mail\VerificationMail;
use App\Models\User;
use App\Transformers\UserTransformer;
use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Dingo\Api\Exception\StoreResourceFailedException;

class UserController extends Controller
{
    use Helpers;

    public function register(Request $request)
    {
        /*
         * Get User IP from request header.
         * 
         * If the website is using CloudFlare, get IP from "CF-Connecting-IP"
         * Else, "REMOTE_ADDR".
         * 
         **/
        $IP = $request->header('CF-Connecting-IP') ?? $request->ip();

        /* 
         * Validate user input. Using "|" to separate different rules.
         * 
         * ref. doc: https://laravel.com/docs/5.5/validation#manually-creating-validators
         * Avaliable Rule: https://laravel.com/docs/5.5/validation#available-validation-rules
         * 
         **/
        $validator = Validator::make($request->all(), [
            'username' => 'required|min:6|max:20|unique:users',
            'password' => 'required|min:6|confirmed',
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'gender' => 'required',
            'age' => 'required|exists:age_groups,id',
            'email' => 'required|email|max:255|unique:users'
        ]);

        // Still returning 200
        if ($validator->fails()) {
            throw new StoreResourceFailedException($validator->errors()->first());
        }

        try {
            // Generate random string as token
            $token = Str::random(40);
            DB::beginTransaction();
            // Sending Verification mail
            //Mail::to($request->email)->queue(new VerificationMail($token));

            // Create user record
            // Using bcrypt to hash password as security measurement.
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'gender' => $request->gender,
                'age' => $request->age,
                'password' => Hash::make($request->password),
                'regip' => $IP
            ]);

            /* 
            * Add new record with foreign key
            * 
            * Define relation: https://laravel.com/docs/5.5/eloquent-relationships#defining-relationships
            * How to use: https://laravel.com/docs/5.5/eloquent-relationships#inserting-and-updating-related-models
            * 
            **/
            $user->verification()->create([
                'token' => $token
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->created();
    }

    public function authenticate(Request $request) {
        $credentials = $request->only('username', 'password');
        $validator = Validator::make($request->all(), [
            'username' => 'required|min:6|max:20',
            'password' => 'required|min:6'
        ]);
        if ($validator->fails()) {
            throw new StoreResourceFailedException($validator->errors()->first());
        }
        try {
            if (!$token = Auth::attempt($credentials)) throw new NotFoundHttpException(trans('auth.failed'));
            $user = Auth::User();
            switch ($user->status) {
                case 0:
                    throw new UnprocessableEntityHttpException(trans('custom.inactive'));
                case -1:
                    throw new UnprocessableEntityHttpException(trans('custom.banned'));
            }
            $user->increment('login_count');
            $user->forceFill([
                'last_login_at' => Carbon::now()
            ])->save();
            $user = (new UserTransformer)->transform($user);
        } catch (\PDOException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        } catch (JWTException $e) {
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->array(compact('token', 'user'));
    }
}