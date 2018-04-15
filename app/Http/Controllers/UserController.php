<?php

namespace App\Http\Controllers;

use Auth;
use Log;
use Validator;
use Carbon\Carbon;
use App\Mail\ForgetPasswordMail;
use App\Mail\VerificationMail;
use App\Models\PasswordReset;
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

/**
 * @Resource("User", uri="/member")
 */
class UserController extends Controller
{
    use Helpers;

    /**
     * Account registration
     *
     * @Post("/register")
     * @Versions({"v1"})
     * @Request("username=foo&password=bar&password_confirmation=bar&first_name=foo&last_name=bar&gender=M&income=0&age=2&email=foo@bar.com", contentType="application/x-www-form-urlencoded")
     * @Response(201)
     */
    public function register(Request $request)
    {
        $IP = $request->header('CF-Connecting-IP') ?? $request->ip();
        $validator = Validator::make($request->all(), [
            'username' => 'required|min:6|max:20|unique:users',
            'password' => 'required|min:6|confirmed',
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'gender' => 'required|in:M,F',
            'income' => 'required|exists:income_groups,id',
            'age' => 'required|exists:age_groups,id',
            'email' => 'required|email|max:255|unique:users'
        ]);

        if ($validator->fails()) {
            throw new StoreResourceFailedException($validator->errors()->first());
        }

        try {
            $verifyToken = Str::random(40);
            DB::beginTransaction();
            // Sending Verification mail
            // Mail::to($request->email)->queue(new VerificationMail($verifyToken));
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'gender' => $request->gender,
                'age' => $request->age,
                'income' => $request->income,
                'password' => Hash::make($request->password),
                'regip' => $IP
            ]);

            $user->verification()->create([
                'token' => $verifyToken
            ]);
            $token = Auth::tokenById($user->id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->array(compact('token', 'user'))->setStatusCode(201);
    }

    /**
     * Authentication
     *
     * @Post("/authentication")
     * @Versions({"v1"})
     * @Request("username=foo&password=bar", contentType="application/x-www-form-urlencoded")
     * @Response(200, body={"token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.TJVA95OrM7E2cBab30RMHrHDcEfxjoYZgeFONFh7HgQ", "user": {"id": 27, "status": 1, "username": "testing0278", "email": "tripletest@gmail.co22m", "first_name": "Test", "last_name": "ing", "age": 1, "gender": "M", "income": 1, "created_at": 1518771320, "updated_at": 1519158413}})
     */
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

    /**
     * Forget Password
     *
     * @Post("/password/forget")
     * @Versions({"v1"})
     * @Request("username=foo&email=foo@bar.com", contentType="application/x-www-form-urlencoded")
     * @Response(201)
     */
    public function forgetPassword(Request $request) {
        $validator = Validator::make($request->all(), [
            'username' => 'required|min:6|max:20',
            'email' => 'required|email|max:255',
        ]);
        if ($validator->fails()) {
            throw new UnprocessableEntityHttpException($validator->errors()->first());
        }
        try {
            $token = Str::random(40);
            if (!$member = User::where('username', $request->username)->where('email', $request->email)->first()) {
                throw new NotFoundHttpException(trans('custom.notfound.member'));
            }
            if ($forget = $member->passwordReset) {
                if (Carbon::now()->diffInMinutes($forget->updated_at) < 15) {
                    throw new UnprocessableEntityHttpException(trans('custom.limit'));
                }
            }
            // Mail::to($request->email, new ForgetPasswordMail($token));
            PasswordReset::updateOrCreate([
                'user_id' => $member->id,
            ],[
                'token' => $token
            ]);
        } catch (\PDOException $e) {
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->noContent();
    }

    /**
     * Reset Password
     *
     * @Post("/password/reset")
     * @Versions({"v1"})
     * @Request("username=foo&password=bar&password_confirmation=bar&email=foo@bar.com&token=123456", contentType="application/x-www-form-urlencoded")
     * @Response(201)
     */
    public function resetPassword(Request $request) {
        throw new ServiceUnavailableHttpException('', trans('custom.implementation'));
    }
}