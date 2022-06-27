<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Certificate;
use App\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Auth;
use DB;
class DashboardController extends Controller
{
    protected $key;

    public function __construct() {
        $this->key = env('JWT_KEY');
    }

    public function isAuthenticated($token) {
        $user = JWT::decode($token, new Key($this->key, 'HS256'));
        return Auth::attempt(['email' => $user->email, 'password' => $user->password]);
    }

    public function getDashboardDetails(Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        $certificates = Certificate::groupBy('status')->selectRaw('status, COUNT(*) as total');
        $logs = DB::table('logs as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->select('l.*', 'u.name')
            ->orderBy('l.id', 'DESC')
            ->limit(30);
        if(Auth::user()->access_level != 'admin') {
            $logs->where('user_id', Auth::user()->id);
        }
        return response()->json([
            'certificates' => $certificates->get(),
            'logs' => $logs->get()
        ]);
    }
}
