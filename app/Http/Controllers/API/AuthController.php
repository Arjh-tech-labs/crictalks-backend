<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'mobile_number' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8',
            'city' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'user_types' => 'required|array',
            'user_types.*' => 'exists:user_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile_number' => $request->mobile_number,
            'password' => Hash::make($request->password),
            'city' => $request->city,
            'location' => $request->location,
        ]);

        // Attach user types
        foreach ($request->user_types as $userTypeId) {
            $user->userTypes()->attach($userTypeId, [
                'profile_data' => json_encode([]),
            ]);
        }

        // Create default player statistics if user is a player
        $playerUserType = UserType::where('name', 'Player')->first();
        if ($playerUserType && in_array($playerUserType->id, $request->user_types)) {
            $formats = ['T20', 'ODI', 'Test'];
            foreach ($formats as $format) {
                $user->statistics()->create([
                    'format' => $format,
                ]);
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user->load('userTypes'),
            'token' => $token,
        ], 201);
    }

    /**
     * Login user and create token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid login credentials',
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user->load('userTypes'),
            'token' => $token,
        ]);
    }

    /**
     * Logout user (revoke the token).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Get the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('userTypes'),
        ]);
    }

    /**
     * Update user profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/profile_pictures', $filename);
            $user->profile_picture = $filename;
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('city')) {
            $user->city = $request->city;
        }

        if ($request->has('location')) {
            $user->location = $request->location;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->load('userTypes'),
        ]);
    }

    /**
     * Update user type specific profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $userTypeId
     * @return \Illuminate\Http\Response
     */
    public function updateUserTypeProfile(Request $request, $userTypeId)
    {
        $user = $request->user();
        $userType = UserType::findOrFail($userTypeId);

        if (!$user->userTypes->contains($userTypeId)) {
            return response()->json([
                'message' => 'User does not have this user type',
            ], 403);
        }

        $profileData = $request->all();
        
        $user->userTypes()->updateExistingPivot($userTypeId, [
            'profile_data' => json_encode($profileData),
        ]);

        return response()->json([
            'message' => $userType->name . ' profile updated successfully',
            'user' => $user->load('userTypes'),
        ]);
    }

    /**
     * Add a user type to the user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $userTypeId
     * @return \Illuminate\Http\Response
     */
    public function addUserType(Request $request, $userTypeId)
    {
        $user = $request->user();
        $userType = UserType::findOrFail($userTypeId);

        if ($user->userTypes->contains($userTypeId)) {
            return response()->json([
                'message' => 'User already has this user type',
            ], 400);
        }

        $user->userTypes()->attach($userTypeId, [
            'profile_data' => json_encode([]),
        ]);

        // Create default player statistics if user is a player
        if ($userType->name === 'Player') {
            $formats = ['T20', 'ODI', 'Test'];
            foreach ($formats as $format) {
                $user->statistics()->create([
                    'format' => $format,
                ]);
            }
        }

        return response()->json([
            'message' => $userType->name . ' role added successfully',
            'user' => $user->load('userTypes'),
        ]);
    }
}

