<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    //use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * 处理身份认证尝试.
     *
     * @return Response
     */
    public function authenticate(Request $request)
    {
        Auth::logout();
        $mobile = $request->get('mobile');
        $username = $request->get('username');

        if (!(strpos($mobile, '1') == 0 && strlen($mobile) == 11)) {
            return response()->json(['status'=> 'failed', 'message' => "手机号码不规范"], 200);
        }

        Log::info("authenticating " . $mobile);
        if (Auth::attempt(['mobile' => $mobile, 'password' => '123456'])) {
            Log::info("login success");
        } else {
            $user = new User();
            $user->name = $username;
            $user->mobile = $mobile;
            $user->password = Hash::make('123456');
            $user->save();
            if (Auth::attempt(['mobile' => $mobile, 'password' => '123456'])) {
                Log::info("login success");
            } else {
                Log::info("login failed");
            }
        }

        $previous_session = \Auth::user()->session_id;
        if ($previous_session) {
            \Session::getHandler()->destroy($previous_session);
        }
        \Auth::user()->session_id = \Session::getId();
        \Auth::user()->name = $username;
        \Auth::user()->save();
        return response()->json(['status'=> 'success', 'message' => "You are logined", 'user' => Auth::user()], 200);
    }

}
