<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * API认证控制器
 * 提供Token生成和管理接口
 */
class AuthController extends Controller
{
    /**
     * 用户登录获取Token
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        // 检查用户状态
        if ($user->status == 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account is banned'
            ], 403);
        }

        // 创建Token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => $token,
                'type' => 'user',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                ]
            ]
        ]);
    }

    /**
     * 管理员登录获取Token
     */
    public function adminLogin(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('username', $request->username)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        // 创建Token
        $token = $admin->createToken('admin-api-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => $token,
                'type' => 'admin',
                'admin' => [
                    'id' => $admin->id,
                    'username' => $admin->username,
                    'email' => $admin->email,
                ]
            ]
        ]);
    }

    /**
     * 注销Token
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * 获取当前用户信息
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user instanceof Admin;

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'type' => $isAdmin ? 'admin' : 'user',
            ]
        ]);
    }
}
