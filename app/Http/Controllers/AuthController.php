<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Auth;
use App\Log;

class AuthController extends Controller
{
    protected $key;

    public function __construct() {
        $this->key = env('JWT_KEY');
    }
    public function login(Request $request) {
        $login = Auth::attempt(['email' => $request->email, 'password' => $request->password]);
        if ($login)
        {
            $payload = [
                'email' => $request->email,
                'password' => $request->password
            ];
    
            $token = $jwt = JWT::encode($payload, $this->key, 'HS256');

            $response = [
                'token' => $token,
                'access_level' => Auth::user()->access_level
            ];
            
            
            saveLog(Auth::user()->id, 'Logged In.'); // Saving Log
            
            return response()->json($response, 200);
        }

        return response()->json(['message' => 'Access Denied!'], 401);
    }
}
