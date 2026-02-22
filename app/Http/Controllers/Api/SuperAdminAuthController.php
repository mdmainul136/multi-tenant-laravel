<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SuperAdminAuthController extends Controller
{
    /**
     * Super admin login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $superAdmin = SuperAdmin::where('email', $request->email)->first();

        if (!$superAdmin || !Hash::check($request->password, $superAdmin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$superAdmin->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is inactive'
            ], 403);
        }

        // Create token
        $token = $superAdmin->createToken('super-admin-token')->plainTextToken;

        // Update last login
        $superAdmin->updateLastLogin();

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'super_admin' => [
                    'id' => $superAdmin->id,
                    'name' => $superAdmin->name,
                    'email' => $superAdmin->email,
                    'role' => $superAdmin->role,
                ],
                'token' => $token,
            ]
        ]);
    }

    /**
     * Get current super admin
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->role,
                'last_login_at' => $request->user()->last_login_at,
            ]
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $superAdmin = $request->user();

        if (!Hash::check($request->current_password, $superAdmin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $superAdmin->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }
}
