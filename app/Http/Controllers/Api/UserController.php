<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users with pagination, search, and filters.
     */
    public function index(Request $request)
    {
        try {
            $query = User::query();

            // Search by name or email
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            // Filter by role
            if ($request->has('role') && $request->role) {
                $query->where('role', $request->role);
            }

            // Filter by status
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Pagination
            $perPage = $request->get('per_page', 10);
            $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $users->items(),
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'total_pages' => $users->lastPage(),
                        'total' => $users->total(),
                        'per_page' => $users->perPage(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,manager,user',
            'status' => 'required|in:active,inactive',
        ], [
            'name.required' => 'Name is required',
            'name.max' => 'Name cannot exceed 255 characters',
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email is already registered',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters long',
            'role.required' => 'Role is required',
            'role.in' => 'Invalid role selected',
            'status.required' => 'Status is required',
            'status.in' => 'Invalid status selected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'status' => $request->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        try {
            $user = User::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)],
                'password' => 'nullable|string|min:8',
                'role' => 'required|in:admin,manager,user',
                'status' => 'required|in:active,inactive',
            ], [
                'name.required' => 'Name is required',
                'name.max' => 'Name cannot exceed 255 characters',
                'email.required' => 'Email is required',
                'email.email' => 'Please provide a valid email address',
                'email.unique' => 'This email is already registered',
                'password.min' => 'Password must be at least 8 characters long',
                'role.required' => 'Role is required',
                'role.in' => 'Invalid role selected',
                'status.required' => 'Status is required',
                'status.in' => 'Invalid status selected',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->name = $request->name;
            $user->email = $request->email;
            $user->role = $request->role;
            $user->status = $request->status;

            // Only update password if provided
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user.
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Prevent deleting yourself
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ], 403);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
