<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class APIController extends Controller
{
    protected $key;

    public function __construct() {
        $this->key = env('JWT_KEY');
    }
    public function login(Request $request) {
        if (Auth::attempt(['email' => $email, 'password' => $password]))
        {
            return redirect()->intended('dashboard');
        }
        $payload = [
            'email' => $request->email,
            'password' => $request->password
        ];

        $token = $jwt = JWT::encode($payload, $key, 'HS256');
    }
}
