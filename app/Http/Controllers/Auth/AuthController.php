<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ResponseApi;
use App\Http\Controllers\Controller;
use App\Jobs\SendMailForgotPassword;
use App\Jobs\SendMailRegisterAccount;
use App\Models\User;
use App\Models\UserVerification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    private $responseApi;

    public function __construct()
    {
        $this->responseApi = new ResponseApi();
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->responseApi->BadRequest($validator->errors()->first());
        }

        $param = $validator->validated();
        try {
            $user = $this->findUser($param['email']);
            if (!$user || $user->status == User::STATUS_BANNED) {
                return $this->responseApi->BadRequest('User not found or banned');
            }
            if (!$user->password || !Hash::check($param['password'], $user->password)) {
                return $this->responseApi->BadRequest('Password is incorrect');
            }

            Auth::login($user);
            $success = $user->createToken($user->id);
            $user->update([
                'device_token' => $success->accessToken,
            ]);
            $success->user_info = $user;

            return $this->responseApi->success($success);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->responseApi->BadRequest($e->getMessage());
        }
    }

    public function loginWithGoogle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->responseApi->BadRequest($validator->errors()->first());
        }

        try {
            $accessToken = $request->input('access_token');
            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
            ])->get('https://www.googleapis.com/oauth2/v3/userinfo');

            if ($response->failed()) {
                return response()->json(['error' => 'Invalid token'], 401);
            }

            $googleUser = $response->json();
            if (empty($googleUser['email'])) {
                return response()->json(['error' => 'Invalid Google profile'], 401);
            }

            $user = $this->findUser($googleUser['email']);
            if ($user && $user->status == User::STATUS_BANNED) {
                return $this->responseApi->BadRequest('User is banned');
            }

            if (!$user) {
                $user = User::create([
                    'name' => $googleUser['name'] ?? $googleUser['email'],
                    'email' => $googleUser['email'],
                    'avatar' => $googleUser['picture'] ?? null,
                    'role' => User::ROLE_CUSTOMER,
                    'status' => User::STATUS_ACTIVE,
                    'email_verified_at' => Carbon::now(),
                ]);
            }

            Auth::login($user);
            $success = $user->createToken($user->id);
            $user->update([
                'device_token' => $success->accessToken,
            ]);
            $success->user_info = $user;

            return $this->responseApi->success($success);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->responseApi->InternalServerError();
        }
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->responseApi->BadRequest($validator->errors()->first());
        }

        $param = $validator->validated();
        $user = $this->findUser($param['email']);
        if ($user) {
            return $this->responseApi->BadRequest('User already exists');
        }

        $user = User::create([
            'name' => $param['name'],
            'email' => $param['email'],
            'password' => Hash::make($param['password']),
            'role' => User::ROLE_CUSTOMER,
            'status' => User::STATUS_ACTIVE,
        ]);

        SendMailRegisterAccount::dispatch($user->id);

        return $this->responseApi->success();
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->responseApi->BadRequest($validator->errors()->first());
        }

        $param = $validator->validated();
        $user = $this->findUser($param['email']);
        if (!$user || $user->status == User::STATUS_BANNED) {
            return $this->responseApi->BadRequest('User not found or banned');
        }

        SendMailForgotPassword::dispatch($user->id);

        return $this->responseApi->success();
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->responseApi->BadRequest($validator->errors()->first());
        }

        $param = $validator->validated();
        $user = $this->findUser($param['email']);
        if (!$user || $user->status == User::STATUS_BANNED) {
            return $this->responseApi->BadRequest('User not found or banned');
        }

        $userVerification = UserVerification::where('user_id', $user->id)->first();
        if (!$userVerification) {
            return $this->responseApi->BadRequest('Verification code not found');
        }
        if ($userVerification->expires_at < Carbon::now()) {
            return $this->responseApi->BadRequest('Expired code');
        }
        if ($userVerification->code != $param['code']) {
            return $this->responseApi->BadRequest('Invalid code');
        }

        $user->update([
            'password' => Hash::make($param['password']),
            'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
        ]);
        $userVerification->delete();

        return $this->responseApi->success();
    }

    public function verifyAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->responseApi->BadRequest($validator->errors()->first());
        }

        $param = $validator->validated();
        $user = $this->findUser($param['email']);
        if (!$user || $user->status == User::STATUS_BANNED) {
            return $this->responseApi->BadRequest('User not found or banned');
        }

        $userVerification = UserVerification::where('user_id', $user->id)->first();
        if (!$userVerification) {
            return $this->responseApi->BadRequest('Verification code not found');
        }
        if ($userVerification->expires_at < Carbon::now()) {
            return $this->responseApi->BadRequest('Expired code');
        }
        if ($userVerification->code != $param['code']) {
            return $this->responseApi->BadRequest('Invalid code');
        }

        if (!$user->email_verified_at) {
            $user->update(['email_verified_at' => Carbon::now()]);
        }
        $userVerification->delete();

        return $this->responseApi->success();
    }

    public function resendCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->responseApi->BadRequest($validator->errors()->first());
        }

        $user = $this->findUser($request->input('email'));
        if (!$user || $user->status == User::STATUS_BANNED) {
            return $this->responseApi->BadRequest('User not found or banned');
        }

        SendMailRegisterAccount::dispatch($user->id);

        return $this->responseApi->success();
    }

    private function findUser($email)
    {
        return User::where('email', $email)->first();
    }
}
