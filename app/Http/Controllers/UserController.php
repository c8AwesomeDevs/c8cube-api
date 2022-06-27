<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Auth;
use Validator;

class UserController extends Controller
{
    protected $key;

    public function __construct() {
        $this->key = env('JWT_KEY');
    }

    public function isAuthenticated($token) {
        $user = JWT::decode($token, new Key($this->key, 'HS256'));
        return Auth::attempt(['email' => $user->email, 'password' => $user->password]);
    }

    public function getUsers(Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }
        if(Auth::user()->access_level != 'admin') {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        $users = User::orderBy('id', 'ASC');

        return response()->json($users->get());
    }

    public function saveUser(Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }
        if(Auth::user()->access_level != 'admin') {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|unique:users,email|email',
            'name' => 'required',
            'password' => 'required|min:6|confirmed',
            'access_level' => 'required',
        ]);

        if($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()
            ], 403);
        }
 
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->access_level = $request->access_level;
        $user->save();

        return response()->json($user, 201);
    }

    public function updateUser($id, Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }
        if(Auth::user()->access_level != 'admin') {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $id,
            'name' => 'required',
            'access_level' => 'required',
        ]);

        if($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()
            ], 403);
        }
 
        $user = User::find($id);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->access_level = $request->access_level;
        $user->save();

        $users = User::orderBy('id', 'ASC');

        return response()->json($users->get());
    }

    public function deleteUser($id, Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }
        if(Auth::user()->access_level != 'admin') {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::find($id);
        $user->delete();

        $users = User::orderBy('id', 'ASC');

        return response()->json($users->get());
    }

    public function getProfileDetails(Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::find(Auth::user()->id);
        
        return response()->json($user);
    }

    public function resetPassword(Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        $is_pw_correct = Auth::attempt(['email' => Auth::user()->email, 'password' => $request->current_password]);

        if(!$is_pw_correct) {
            return response()->json(['message' => 'Current password is incorrect'], 403);
        }
        else {
            if($request->new_password == $request->confirm_password) {
                $user = User::find(Auth::user()->id);
                $user->password = bcrypt($request->new_password);
                $user->save();

                return response()->json(['message' => 'You will be asked to login again.'], 202);
            }
            else {
                return response()->json(['message' => 'Passwords didn\'t match.'], 403);
            }
        }
    }
}
