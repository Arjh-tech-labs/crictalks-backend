<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Team;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TournamentController extends Controller
{
    /**
     * Display a listing of the tournaments.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Tournament::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by format
        if ($request->has('format')) {
            $query->where('format', $request->format);
        }

        // Filter by organizer
        if ($request->has('organizer_id')) {
            $query->where('organizer_id', $request->organizer_id);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $tournaments = $query->with('organizer')->paginate(10);

        return response()->json($tournaments);
    }

    /**
     * Store a newly created tournament in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'location' => 'required|string|max:255',
            'format' => 'required|string|in:T20,ODI,Test,Other',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'tournament_structure' => 'nullable|json',
            'rules' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $organizerUserType = UserType::where('name', 'Organiser')->first();

        // Check if user is an organizer
        if (!$user->userTypes->contains($organizerUserType->id)) {
            return response()->json([
                'message' => 'Only organizers can create tournaments',
            ], 403);
        }

        $tournament = new Tournament();
        $tournament->name = $request->name;
        $tournament->description = $request->description;
        $tournament->start_date = $request->start_date;
        $tournament->end_date = $request->end_date;
        $tournament->location = $request->location;
        $tournament->format = $request->format;
        $tournament->organizer_id = $user->id;
        $tournament->status = 'upcoming';
        $tournament->tournament_structure = $request->tournament_structure;
        $tournament->rules = $request->rules;

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/tournament_logos', $filename);
            $tournament->logo = $filename;
        }

        if ($request->hasFile('banner')) {
            $file = $request->file('banner');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/tournament_banners', $filename);
            $tournament->banner = $filename;
        }

        $tournament->save();

        return response()->json([
            'message' => 'Tournament created successfully',
            'tournament' => $tournament->load('organizer'),
        ], 201);
    }

    /**
     * Display the specified tournament.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $tournament = Tournament::with(['organizer', 'teams', 'matches'])->findOrFail($id);

        return response()->json($tournament);
    }

    /**
     * Update the specified tournament in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'location' => 'nullable|string|max:255',
            'format' => 'nullable|string|in:T20,ODI,Test,Other',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'nullable|string|in:upcoming,ongoing,completed',
            'tournament_structure' => 'nullable|json',
            'rules' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tournament = Tournament::findOrFail($id);
        $user = $request->user();

        // Check if user is the tournament organizer
        if ($tournament->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the tournament organizer can update the tournament',
            ], 403);
        }

        if ($request->has('name')) {
            $tournament->name = $request->name;
        }

        if ($request->has('description')) {
            $tournament->description = $request->description;
        }

        if ($request->has('start_date')) {
            $tournament->start_date = $request->start_date;
        }

        if ($request->has('end_date')) {
            $tournament->end_date = $request->end_date;
        }

        if ($request->has('location')) {
            $tournament->location = $request->location;
        }

        if ($request->has('format')) {
            $tournament->format = $request->format;
        }

        if ($request->has('status')) {
            $tournament->status = $request->status;
        }

        if ($request->has('tournament_structure')) {
            $tournament->tournament_structure = $request->tournament_structure;
        }

        if ($request->has('rules')) {
            $tournament->rules = $request->rules;
        }

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/tournament_logos', $filename);
            $tournament->logo = $filename;
        }

        if ($request->hasFile('banner')) {
            $file = $request->file('banner');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/tournament_banners', $filename);
            $tournament->banner = $filename;
        }

        $tournament->save();

        return response()->json([
            'message' => 'Tournament updated successfully',
            'tournament' => $tournament->load('organizer'),
        ]);
    }

    /**
     * Remove the specified tournament from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $tournament = Tournament::findOrFail($id);
        $user = $request->user();

        // Check if user is the tournament organizer
        if ($tournament->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the tournament organizer can delete the tournament',
            ], 403);
        }

        $tournament->delete();

        return response()->json([
            'message' => 'Tournament deleted successfully',
        ]);
    }

    /**
     * Add a team to the tournament.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addTeam(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|exists:teams,id',
            'group' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tournament = Tournament::findOrFail($id);
        $user = $request->user();
        $team = Team::findOrFail($request->team_id);

        // Check if user is the tournament organizer
        if ($tournament->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the tournament organizer can add teams to the tournament',
            ], 403);
        }

        // Check if team is already in the tournament
        if ($tournament->teams->contains($request->team_id)) {
            return response()->json([
                'message' => 'Team is already in the tournament',
            ], 400);
        }

        $tournament->teams()->attach($request->team_id, [
            'group' => $request->group,
            'points' => 0,
            'matches_played' => 0,
            'matches_won' => 0,
            'matches_lost' => 0,
            'matches_tied' => 0,
            'matches_no_result' => 0,
            'net_run_rate' => 0,
        ]);

        return response()->json([
            'message' => 'Team added to tournament successfully',
            'tournament' => $tournament->load('teams'),
        ]);
    }

    /**
     * Remove a team from the tournament.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @param  int  $teamId
     * @return \Illuminate\Http\Response
     */
    public function removeTeam(Request $request, $id, $teamId)
    {
        $tournament = Tournament::findOrFail($id);
        $user = $request->user();

        // Check if user is the tournament organizer
        if ($tournament->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the tournament organizer can remove teams from the tournament',
            ], 403);
        }

        // Check if team is in the tournament
        if (!$tournament->teams->contains($teamId)) {
            return response()->json([
                'message' => 'Team is not in the tournament',
            ], 400);
        }

        $tournament->teams()->detach($teamId);

        return response()->json([
            'message' => 'Team removed from tournament successfully',
            'tournament' => $tournament->load('teams'),
        ]);
    }

    /**
     * Update team details in the tournament.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @param  int  $teamId
     * @return \Illuminate\Http\Response
     */
    public function updateTeam(Request $request, $id, $teamId)
    {
        $validator = Validator::make($request->all(), [
            'group' => 'nullable|string|max:255',
            'points' => 'nullable|integer|min:0',
            'matches_played' => 'nullable|integer|min:0',
            'matches_won' => 'nullable|integer|min:0',
            'matches_lost' => 'nullable|integer|min:0',
            'matches_tied' => 'nullable|integer|min:0',
            'matches_no_result' => 'nullable|integer|min:0',
            'net_run_rate' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tournament = Tournament::findOrFail($id);
        $user = $request->user();

        // Check if user is the tournament organizer
        if ($tournament->organizer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the tournament organizer can update team details',
            ], 403);
        }

        // Check if team is in the tournament
        if (!$tournament->teams->contains($teamId)) {
            return response()->json([
                'message' => 'Team is not in the tournament',
            ], 400);
        }

        $updateData = [];
        if ($request->has('group')) {
            $updateData['group'] = $request->group;
        }
        if ($request->has('points')) {
            $updateData['points'] = $request->points;
        }
        if ($request->has('matches_played')) {
            $updateData['matches_played'] = $request->matches_played;
        }
        if ($request->has('matches_won')) {
            $updateData['matches_won'] = $request->matches_won;
        }
        if ($request->has('matches_lost')) {
            $updateData['matches_lost'] = $request->matches_lost;
        }
        if ($request->has('matches_tied')) {
            $updateData['matches_tied'] = $request->matches_tied;
        }
        if ($request->has('matches_no_result')) {
            $updateData['matches_no_result'] = $request->matches_no_result;
        }
        if ($request->has('net_run_rate')) {
            $updateData['net_run_rate'] = $request->net_run_rate;
        }

        $tournament->teams()->updateExistingPivot($teamId, $updateData);

        return response()->json([
            'message' => 'Team details updated successfully',
            'tournament' => $tournament->load('teams'),
        ]);
    }

    /**
     * Get all teams in the tournament.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getTeams($id)
    {
        $tournament = Tournament::findOrFail($id);
        $teams = $tournament->teams;

        return response()->json($teams);
    }

    /**
     * Get the points table for the tournament.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getPointsTable($id)
    {
        $tournament = Tournament::findOrFail($id);
        $pointsTable = $tournament->pointsTable;

        return response()->json($pointsTable);
    }
}

