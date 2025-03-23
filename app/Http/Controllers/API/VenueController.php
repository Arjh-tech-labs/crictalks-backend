<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VenueController extends Controller
{
    /**
     * Display a listing of the venues.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Venue::query();

        // Filter by city
        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        // Filter by country
        if ($request->has('country')) {
            $query->where('country', $request->country);
        }

        //  {
            $query->where('country', $request->country);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $venues = $query->paginate(10);

        return response()->json($venues);
    }

    /**
     * Store a newly created venue in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'address' => 'nullable|string',
            'capacity' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $organizerUserType = UserType::where('name', 'Organiser')->first();

        // Check if user is an organizer
        if (!$user->userTypes->contains($organizerUserType->id)) {
            return response()->json([
                'message' => 'Only organizers can create venues',
            ], 403);
        }

        $venue = new Venue();
        $venue->name = $request->name;
        $venue->city = $request->city;
        $venue->country = $request->country;
        $venue->address = $request->address;
        $venue->capacity = $request->capacity;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/venue_images', $filename);
            $venue->image = $filename;
        }

        $venue->save();

        return response()->json([
            'message' => 'Venue created successfully',
            'venue' => $venue,
        ], 201);
    }

    /**
     * Display the specified venue.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $venue = Venue::findOrFail($id);

        return response()->json($venue);
    }

    /**
     * Update the specified venue in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'capacity' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $venue = Venue::findOrFail($id);
        $user = $request->user();
        $organizerUserType = UserType::where('name', 'Organiser')->first();

        // Check if user is an organizer
        if (!$user->userTypes->contains($organizerUserType->id)) {
            return response()->json([
                'message' => 'Only organizers can update venues',
            ], 403);
        }

        if ($request->has('name')) {
            $venue->name = $request->name;
        }

        if ($request->has('city')) {
            $venue->city = $request->city;
        }

        if ($request->has('country')) {
            $venue->country = $request->country;
        }

        if ($request->has('address')) {
            $venue->address = $request->address;
        }

        if ($request->has('capacity')) {
            $venue->capacity = $request->capacity;
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/venue_images', $filename);
            $venue->image = $filename;
        }

        $venue->save();

        return response()->json([
            'message' => 'Venue updated successfully',
            'venue' => $venue,
        ]);
    }

    /**
     * Remove the specified venue from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $venue = Venue::findOrFail($id);
        $user = $request->user();
        $organizerUserType = UserType::where('name', 'Organiser')->first();

        // Check if user is an organizer
        if (!$user->userTypes->contains($organizerUserType->id)) {
            return response()->json([
                'message' => 'Only organizers can delete venues',
            ], 403);
        }

        $venue->delete();

        return response()->json([
            'message' => 'Venue deleted successfully',
        ]);
    }
}

