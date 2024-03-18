<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request){
        $validator = Validator::make($request->all(),[
            'email' => 'required',
            'password' => 'required|min:5'
        ]);

        if($validator->fails()){
            return response()->json([
                'message' => 'Invalid field',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only(['email', 'password']);
        if(!auth()->attempt($credentials)){
            return response()->json([
                'message' => 'Email or password incorrect',
            ], 401);
        }

        $user = User::where('email', $request->email)->first();
        $token = $user->createToken("SANCTUM TOKEN")->plainTextToken;

        $user->accessToken = $token;
        return response()->json([
            'message' => 'Login success',
            'user' => $user
        ], 200);
    }

    public function logout(Request $request){
        $request->user()->Tokens()->delete();
        return response()->json([
            'message' => 'Logout success',
        ], 200);
    }
}
