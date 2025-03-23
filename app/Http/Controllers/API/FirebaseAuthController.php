<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\InvalidToken;

class FirebaseAuthController extends Controller
{
    protected $auth;

    public function __construct()
    {
        $this->auth = app('firebase.auth');
    }

    /**
     * Verify phone number with Firebase.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function verifyPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firebase_uid' => 'required|string',
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Verify the Firebase UID
            $firebaseUser = $this->auth->getUser($request->firebase_uid);
            
            // Check if the phone number matches
            if ($firebaseUser->phoneNumber !== $request->phone_number) {
                return response()->json([
                    'message' => 'Phone number does not match Firebase user',
                ], 400);
            }
            
            // Check if a user with this phone number exists
            $user = User::where('mobile_number', $request->phone_number)->first();
            
            if ($user) {
                // Update the firebase_uid if it's not set
                if (!$user->firebase_uid) {
                    $user->firebase_uid = $request->firebase_uid;
                    $user->save();
                }
                
                // Generate token
                $token = $user->createToken('firebase_auth')->plainTextToken;
                
                return response()->json([
                    'message' => 'Phone number verified successfully',
                    'user' => $user->load('userTypes'),
                    'token' => $token,
                ]);
            } else {
                return response()->json([
                    'message' => 'User not found. Please register.',
                    'phone_verified' => true,
                    'firebase_uid' => $request->firebase_uid,
                ], 404);
            }
        } catch (InvalidToken $e) {
            return response()->json([
                'message' => 'Invalid Firebase token',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error verifying phone: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Register a new user with Firebase.
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
            'firebase_uid' => 'required|string',
            'city' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'user_types' => 'required|array',
            'user_types.*' => 'exists:user_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Verify the Firebase UID
            $firebaseUser = $this->auth->getUser($request->firebase_uid);
            
            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'mobile_number' => $request->mobile_number,
                'firebase_uid' => $request->firebase_uid,
                'city' => $request->city,
                'location' => $request->location,
                'password' => Hash::make(uniqid()), // Generate a random password
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

            $token = $user->createToken('firebase_auth')->plainTextToken;

            return response()->json([
                'message' => 'User registered successfully',
                'user' => $user->load('userTypes'),
                'token' => $token,
            ], 201);
        } catch (InvalidToken $e) {
            return response()->json([
                'message' => 'Invalid Firebase token',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error registering user: ' . $e->getMessage(),
            ], 500);
        }
    }
}

