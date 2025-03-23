<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserType;
use Illuminate\Http\Request;

class UserTypeController extends Controller
{
    /**
     * Display a listing of the user types.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $userTypes = UserType::all();

        return response()->json($userTypes);
    }

    /**
     * Display the specified user type.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $userType = UserType::findOrFail($id);

        return response()->json($userType);
    }
}

