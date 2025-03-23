<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Kreait\Firebase\Exception\Auth\InvalidToken;

class VerifyFirebaseToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $auth = app('firebase.auth');
        
        try {
            $idToken = $request->bearerToken();
            if (!$idToken) {
                return response()->json(['message' => 'Firebase token not provided'], 401);
            }
            
            $verifiedIdToken = $auth->verifyIdToken($idToken);
            $uid = $verifiedIdToken->claims()->get('sub');
            
            $request->attributes->add(['firebase_uid' => $uid]);
            
            return $next($request);
        } catch (InvalidToken $e) {
            return response()->json(['message' => 'Firebase token is invalid: ' . $e->getMessage()], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Firebase token error: ' . $e->getMessage()], 500);
        }
    }
}

