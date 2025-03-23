<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TeamController extends Controller
{
    /**
     * Display a listing of the teams.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Team::query();

        // Filter by city
        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        // Filter by manager
        if ($request->has('manager_id')) {
            $query->where('manager_id', $request->manager_id);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $teams = $query->with('manager')->paginate(10);

        return response()->json($teams);
    }

    /**
     * Store a newly created team in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $teamManagerUserType = UserType::where('name', 'Team Manager')->first();

        // Check if user is a team manager
        if (!$user->userTypes->contains($teamManagerUserType->id)) {
            return response()->json([
                'message' => 'Only team managers can create teams',
            ], 403);
        }

        $team = new Team();
        $team->name = $request->name;
        $team->city = $request->city;
        $team->description = $request->description;
        $team->manager_id = $user->id;

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/team_logos', $filename);
            $team->logo = $filename;
        }

        $team->save();

        return response()->json([
            'message' => 'Team created successfully',
            'team' => $team->load('manager'),
        ], 201);
    }

    /**
     * Display the specified team.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $team = Team::with(['manager', 'players'])->findOrFail($id);

        return response()->json($team);
    }

    /**
     * Update the specified team in storage.
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
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $team = Team::findOrFail($id);
        $user = $request->user();

        // Check if user is the team manager
        if ($team->manager_id !== $user->id) {
            return response()->json([
                'message' => 'Only the team manager can update the team',
            ], 403);
        }

        if ($request->has('name')) {
            $team->name = $request->name;
        }

        if ($request->has('city')) {
            $team->city = $request->city;
        }

        if ($request->has('description')) {
            $team->description = $request->description;
        }

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/team_logos', $filename);
            $team->logo = $filename;
        }

        $team->save();

        return response()->json([
            'message' => 'Team updated successfully',
            'team' => $team->load('manager'),
        ]);
    }

    /**
     * Remove the specified team from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $team = Team::findOrFail($id);
        $user = $request->user();

        // Check if user is the team manager
        if ($team->manager_id !== $user->id) {
            return response()->json([
                'message' => 'Only the team manager can delete the team',
            ], 403);
        }

        $team->delete();

        return response()->json([
            'message' => 'Team deleted successfully',
        ]);
    }

    /**
     * Add a player to the team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addPlayer(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|string|in:captain,vice-captain,player',
            'jersey_number' => 'nullable|string|max:10',
            'batting_style' => 'nullable|string|max:255',
            'bowling_style' => 'nullable|string|max:255',
            'player_type' => 'nullable|string|in:batsman,bowler,all-rounder,wicket-keeper',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $team = Team::findOrFail($id);
        $user = $request->user();
        $playerToAdd = User::findOrFail($request->user_id);
        $playerUserType = UserType::where('name', 'Player')->first();

        // Check if user is the team manager
        if ($team->manager_id !== $user->id) {
            return response()->json([
                'message' => 'Only the team manager can add players to the team',
            ], 403);
        }

        // Check if the user to add is a player
        if (!$playerToAdd->userTypes->contains($playerUserType->id)) {
            return response()->json([
                'message' => 'User is not a player',
            ], 400);
        }

        // Check if player is already in the team
        if ($team->players->contains($request->user_id)) {
            return response()->json([
                'message' => 'Player is already in the team',
            ], 400);
        }

        // If role is captain or vice-captain, check if there's already a captain or vice-captain
        if ($request->role === 'captain') {
            $existingCaptain = $team->players()->wherePivot('role', 'captain')->first();
            if ($existingCaptain) {
                return response()->json([
                    'message' => 'Team already has a captain',
                ], 400);
            }
        } elseif ($request->role === 'vice-captain') {
            $existingViceCaptain = $team->players()->wherePivot('role', 'vice-captain')->first();
            if ($existingViceCaptain) {
                return response()->json([
                    'message' => 'Team already has a vice-captain',
                ], 400);
            }
        }

        $team->players()->attach($request->user_id, [
            'role' => $request->role ?? 'player',
            'jersey_number' => $request->jersey_number,
            'batting_style' => $request->batting_style,
            'bowling_style' => $request->bowling_style,
            'player_type' => $request->player_type,
        ]);

        return response()->json([
            'message' => 'Player added to team successfully',
            'team' => $team->load('players'),
        ]);
    }

    /**
     * Remove a player from the team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @param  int  $userId
     * @return \Illuminate\Http\Response
     */
    public function removePlayer(Request $request, $id, $userId)
    {
        $team = Team::findOrFail($id);
        $user = $request->user();

        // Check if user is the team manager
        if ($team->manager_id !== $user->id) {
            return response()->json([
                'message' => 'Only the team manager can remove players from the team',
            ], 403);
        }

        // Check if player is in the team
        if (!$team->players->contains($userId)) {
            return response()->json([
                'message' => 'Player is not in the team',
            ], 400);
        }

        $team->players()->detach($userId);

        return response()->json([
            'message' => 'Player removed from team successfully',
            'team' => $team->load('players'),
        ]);
    }

    /**
     * Update player details in the team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @param  int  $userId
     * @return \Illuminate\Http\Response
     */
    public function updatePlayer(Request $request, $id, $userId)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'nullable|string|in:captain,vice-captain,player',
            'jersey_number' => 'nullable|string|max:10',
            'batting_style' => 'nullable|string|max:255',
            'bowling_style' => 'nullable|string|max:255',
            'player_type' => 'nullable|string|in:batsman,bowler,all-rounder,wicket-keeper',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $team = Team::findOrFail($id);
        $user = $request->user();

        // Check if user is the team manager
        if ($team->manager_id !== $user->id) {
            return response()->json([
                'message' => 'Only the team manager can update player details',
            ], 403);
        }

        // Check if player is in the team
        if (!$team->players->contains($userId)) {
            return response()->json([
                'message' => 'Player is not in the team',
            ], 400);
        }

        // If role is captain or vice-captain, check if there's already a captain or vice-captain
        if ($request->has('role')) {
            if ($request->role === 'captain') {
                $existingCaptain = $team->players()->wherePivot('role', 'captain')->first();
                if ($existingCaptain && $existingCaptain->id != $userId) {
                    return response()->json([
                        'message' => 'Team already has a captain',
                    ], 400);
                }
            } elseif ($request->role === 'vice-captain') {
                $existingViceCaptain = $team->players()->wherePivot('role', 'vice-captain')->first();
                if ($existingViceCaptain && $existingViceCaptain->id != $userId) {
                    return response()->json([
                        'message' => 'Team already has a vice-captain',
                    ], 400);
                }
            }
        }

        $updateData = [];
        if ($request->has('role')) {
            $updateData['role'] = $request->role;
        }
        if ($request->has('jersey_number')) {
            $updateData['jersey_number'] = $request->jersey_number;
        }
        if ($request->has('batting_style')) {
            $updateData['batting_style'] = $request->batting_style;
        }
        if ($request->has('bowling_style')) {
            $updateData['bowling_style'] = $request->bowling_style;
        }
        if ($request->has('player_type')) {
            $updateData['player_type'] = $request->player_type;
        }

        $team->players()->updateExistingPivot($userId, $updateData);

        return response()->json([
            'message' => 'Player details updated successfully',
            'team' => $team->load('players'),
        ]);
    }

    /**
     * Get all players in the team.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getPlayers($id)
    {
        $team = Team::findOrFail($id);
        $players = $team->players;

        return response()->json($players);
    }
}

