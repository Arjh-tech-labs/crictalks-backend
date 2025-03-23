<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Match;
use App\Models\Tournament;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Models\UserType;
use App\Models\LiveStreamSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MatchController extends Controller
{
    /**
     * Display a listing of the matches.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = CricketMatch::query();

        // Filter by tournament
        if ($request->has('tournament_id')) {
            $query->where('tournament_id', $request->tournament_id);
        }

        // Filter by team
        if ($request->has('team_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('team1_id', $request->team_id)
                  ->orWhere('team2_id', $request->team_id);
            });
        }

        // Filter by venue
        if ($request->has('venue_id')) {
            $query->where('venue_id', $request->venue_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('scheduled_date', [$request->start_date, $request->end_date]);
        } elseif ($request->has('start_date')) {
            $query->where('scheduled_date', '>=', $request->start_date);
        } elseif ($request->has('end_date')) {
            $query->where('scheduled_date', '<=', $request->end_date);
        }

        $matches = $query->with(['team1', 'team2', 'venue', 'tournament'])->paginate(10);

        return response()->json($matches);
    }

    /**
     * Store a newly created match in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tournament_id' => 'nullable|exists:tournaments,id',
            'team1_id' => 'required|exists:teams,id',
            'team2_id' => 'required|exists:teams,id|different:team1_id',
            'venue_id' => 'nullable|exists:venues,id',
            'scheduled_date' => 'required|date',
            'match_type' => 'required|string|in:T20,ODI,Test,Other',
            'round' => 'nullable|string',
            'match_number' => 'nullable|string',
            'youtube_stream_id' => 'nullable|string',
            'overlay_settings' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $organizerUserType = UserType::where('name', 'Organiser')->first();

        // Check if user is an organizer
        if (!$user->userTypes->contains($organizerUserType->id)) {
            return response()->json([
                'message' => 'Only organizers can create matches',
            ], 403);
        }

        // If tournament_id is provided, check if both teams are in the tournament
        if ($request->has('tournament_id')) {
            $tournament = Tournament::findOrFail($request->tournament_id);
            
            if (!$tournament->teams->contains($request->team1_id)) {
                return response()->json([
                    'message' => 'Team 1 is not in the tournament',
                ], 400);
            }
            
            if (!$tournament->teams->contains($request->team2_id)) {
                return response()->json([
                    'message' => 'Team 2 is not in the tournament',
                ], 400);
            }
        }

        $match = new Match();
        $match->tournament_id = $request->tournament_id;
        $match->team1_id = $request->team1_id;
        $match->team2_id = $request->team2_id;
        $match->venue_id = $request->venue_id;
        $match->scheduled_date = $request->scheduled_date;
        $match->match_type = $request->match_type;
        $match->status = 'upcoming';
        $match->round = $request->round;
        $match->match_number = $request->match_number;
        $match->youtube_stream_id = $request->youtube_stream_id;
        $match->overlay_settings = $request->overlay_settings;

        $match->save();

        // Create live stream settings if youtube_stream_id is provided
        if ($request->has('youtube_stream_id')) {
            $liveStreamSettings = new LiveStreamSettings();
            $liveStreamSettings->match_id = $match->id;
            $liveStreamSettings->youtube_stream_id = $request->youtube_stream_id;
            $liveStreamSettings->stream_status = 'not_started';
            $liveStreamSettings->save();
        }

        return response()->json([
            'message' => 'Match created successfully',
            'match' => $match->load(['team1', 'team2', 'venue', 'tournament']),
        ], 201);
    }

    /**
     * Display the specified match.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $match = CricketMatch::with([
            'team1', 
            'team2', 
            'venue', 
            'tournament', 
            'tossWinner', 
            'matchWinner', 
            'playerOfMatch',
            'scorer',
            'streamer',
            'innings',
            'liveStreamSettings',
            'awards'
        ])->findOrFail($id);

        return response()->json($match);
    }

    /**
     * Update the specified match in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'venue_id' => 'nullable|exists:venues,id',
            'scheduled_date' => 'nullable|date',
            'match_type' => 'nullable|string|in:T20,ODI,Test,Other',
            'status' => 'nullable|string|in:upcoming,live,completed,abandoned',
            'toss_winner_id' => 'nullable|exists:teams,id',
            'toss_decision' => 'nullable|string|in:bat,bowl',
            'match_winner_id' => 'nullable|exists:teams,id',
            'result_description' => 'nullable|string',
            'team1_score' => 'nullable|integer|min:0',
            'team1_wickets' => 'nullable|integer|min:0|max:10',
            'team1_overs' => 'nullable|numeric|min:0',
            'team2_score' => 'nullable|integer|min:0',
            'team2_wickets' => 'nullable|integer|min:0|max:10',
            'team2_overs' => 'nullable|numeric|min:0',
            'player_of_match_id' => 'nullable|exists:users,id',
            'youtube_stream_id' => 'nullable|string',
            'overlay_settings' => 'nullable|json',
            'scorer_id' => 'nullable|exists:users,id',
            'streamer_id' => 'nullable|exists:users,id',
            'round' => 'nullable|string',
            'match_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $match = CricketMatch::findOrFail($id);
        $user = $request->user();
        $organizerUserType = UserType::where('name', 'Organiser')->first();
        $scorerUserType = UserType::where('name', 'Scorer')->first();
        $streamerUserType = UserType::where('name', 'Live Streamer')->first();

        // Check if user is an organizer, scorer, or streamer
        $isOrganizer = $user->userTypes->contains($organizerUserType->id);
        $isScorer = $user->userTypes->contains($scorerUserType->id) && $match->scorer_id === $user->id;
        $isStreamer = $user->userTypes->contains($streamerUserType->id) && $match->streamer_id === $user->id;

        if (!$isOrganizer && !$isScorer && !$isStreamer) {
            return response()->json([
                'message' => 'Only organizers, assigned scorers, or assigned streamers can update matches',
            ], 403);
        }

        // Organizers can update all fields
        if ($isOrganizer) {
            if ($request->has('venue_id')) {
                $match->venue_id = $request->venue_id;
            }
            if ($request->has('scheduled_date')) {
                $match->scheduled_date = $request->scheduled_date;
            }
            if ($request->has('match_type')) {
                $match->match_type = $request->match_type;
            }
            if ($request->has('status')) {
                $match->status = $request->status;
            }
            if ($request->has('round')) {
                $match->round = $request->round;
            }
            if ($request->has('match_number')) {
                $match->match_number = $request->match_number;
            }
            if ($request->has('scorer_id')) {
                $match->scorer_id = $request->scorer_id;
            }
            if ($request->has('streamer_id')) {
                $match->streamer_id = $request->streamer_id;
            }
        }

        // Scorers can update match details
        if ($isOrganizer || $isScorer) {
            if ($request->has('toss_winner_id')) {
                $match->toss_winner_id = $request->toss_winner_id;
            }
            if ($request->has('toss_decision')) {
                $match->toss_decision = $request->toss_decision;
            }
            if ($request->has('match_winner_id')) {
                $match->match_winner_id = $request->match_winner_id;
            }
            if ($request->has('result_description')) {
                $match->result_description = $request->result_description;
            }
            if ($request->has('team1_score')) {
                $match->team1_score = $request->team1_score;
            }
            if ($request->has('team1_wickets')) {
                $match->team1_wickets = $request->team1_wickets;
            }
            if ($request->has('team1_overs')) {
                $match->team1_overs = $request->team1_overs;
            }
            if ($request->has('team2_score')) {
                $match->team2_score = $request->team2_score;
            }
            if ($request->has('team2_wickets')) {
                $match->team2_wickets = $request->team2_wickets;
            }
            if ($request->has('team2_overs')) {
                $match->team2_overs = $request->team2_overs;
            }
            if ($request->has('player_of_match_id')) {
                $match->player_of_match_id = $request->player_of_match_id;
            }
        }

        // Streamers can update streaming details
        if ($isOrganizer || $isStreamer) {
            if ($request->has('youtube_stream_id')) {
                $match->youtube_stream_id = $request->youtube_stream_id;
                
                // Update or create live stream settings
                $liveStreamSettings = LiveStreamSettings::firstOrNew(['match_id' => $match->id]);
                $liveStreamSettings->youtube_stream_id = $request->youtube_stream_id;
                $liveStreamSettings->save();
            }
            if ($request->has('overlay_settings')) {
                $match->overlay_settings = $request->overlay_settings;
            }
        }

        $match->save();

        return response()->json([
            'message' => 'Match updated successfully',
            'match' => $match->load(['team1', 'team2', 'venue', 'tournament']),
        ]);
    }

    /**
     * Remove the specified match from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $match = CricketMatch::findOrFail($id);
        $user = $request->user();
        $organizerUserType = UserType::where('name', 'Organiser')->first();

        // Check if user is an organizer
        if (!$user->userTypes->contains($organizerUserType->id)) {
            return response()->json([
                'message' => 'Only organizers can delete matches',
            ], 403);
        }

        // If match is part of a tournament, check if user is the tournament organizer
        if ($match->tournament_id) {
            $tournament = Tournament::findOrFail($match->tournament_id);
            if ($tournament->organizer_id !== $user->id) {
                return response()->json([
                    'message' => 'Only the tournament organizer can delete matches in the tournament',
                ], 403);
            }
        }

        $match->delete();

        return response()->json([
            'message' => 'Match deleted successfully',
        ]);
    }

    /**
     * Assign a scorer to the match.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function assignScorer(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'scorer_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $match = CricketMatch::findOrFail($id);
        $user = $request->user();
        $organizerUserType = UserType::where('name', 'Organiser')->first();
        $scorerUserType = UserType::where('name', 'Scorer')->first();
        $scorer = User::findOrFail($request->scorer_id);

        // Check if user is an organizer
        if (!$user->userTypes->contains($organizerUserType->id)) {
            return response()->json([
                'message' => 'Only organizers can assign scorers',
            ], 403);
        }

        // Check if the user to assign is a scorer
        if (!$scorer->userTypes->contains($scorerUserType->id)) {
            return response()->json([
                'message' => 'User is not a scorer',
            ], 400);
        }

        $match->scorer_id = $request->scorer_id;
        $match->save();

        return response()->json([
            'message' => 'Scorer assigned successfully',
            'match' => $match->load(['team1', 'team2', 'venue', 'tournament', 'scorer']),
        ]);
    }

    /**
     * Assign a streamer to the match.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function assignStreamer(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'streamer_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $match = CricketMatch::findOrFail($id);
        $user = $request->user();
        $organizerUserType = UserType::where('name', 'Organiser')->first();
        $streamerUserType = UserType::where('name', 'Live Streamer')->first();
        $streamer = User::findOrFail($request->streamer_id);

        // Check if user is an organizer
        if (!$user->userTypes->contains($organizerUserType->id)) {
            return response()->json([
                'message' => 'Only organizers can assign streamers',
            ], 403);
        }

        // Check if the user to assign is a streamer
        if (!$streamer->userTypes->contains($streamerUserType->id)) {
            return response()->json([
                'message' => 'User is not a streamer',
            ], 400);
        }

        $match->streamer_id = $request->streamer_id;
        $match->save();

        return response()->json([
            'message' => 'Streamer assigned successfully',
            'match' => $match->load(['team1', 'team2', 'venue', 'tournament', 'streamer']),
        ]);
    }

    /**
     * Update the live stream settings for the match.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateLiveStreamSettings(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'youtube_api_key' => 'nullable|string',
            'youtube_channel_id' => 'nullable|string',
            'youtube_stream_id' => 'nullable|string',
            'stream_title' => 'nullable|string',
            'stream_description' => 'nullable|string',
            'stream_status' => 'nullable|string|in:not_started,scheduled,live,completed',
            'scheduled_start_time' => 'nullable|date',
            'overlay_settings' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $match = CricketMatch::findOrFail($id);
        $user = $request->user();
        $organizerUserType = UserType::where('name', 'Organiser')->first();
        $streamerUserType = UserType::where('name', 'Live Streamer')->first();

        // Check if user is an organizer or the assigned streamer
        $isOrganizer = $user->userTypes->contains($organizerUserType->id);
        $isStreamer = $user->userTypes->contains($streamerUserType->id) && $match->streamer_id === $user->id;

        if (!$isOrganizer && !$isStreamer) {
            return response()->json([
                'message' => 'Only organizers or assigned streamers can update live stream settings',
            ], 403);
        }

        $liveStreamSettings = LiveStreamSettings::firstOrNew(['match_id' => $match->id]);

        if ($request->has('youtube_api_key')) {
            $liveStreamSettings->youtube_api_key = $request->youtube_api_key;
        }
        if ($request->has('youtube_channel_id')) {
            $liveStreamSettings->youtube_channel_id = $request->youtube_channel_id;
        }
        if ($request->has('youtube_stream_id')) {
            $liveStreamSettings->youtube_stream_id = $request->youtube_stream_id;
            $match->youtube_stream_id = $request->youtube_stream_id;
        }
        if ($request->has('stream_title')) {
            $liveStreamSettings->stream_title = $request->stream_title;
        }
        if ($request->has('stream_description')) {
            $liveStreamSettings->stream_description = $request->stream_description;
        }
        if ($request->has('stream_status')) {
            $liveStreamSettings->stream_status = $request->stream_status;
            
            // Update match status if stream status changes
            if ($request->stream_status === 'live' && $match->status === 'upcoming') {
                $match->status = 'live';
            } elseif ($request->stream_status === 'completed' && $match->status === 'live') {
                $match->status = 'completed';
            }
        }
        if ($request->has('scheduled_start_time')) {
            $liveStreamSettings->scheduled_start_time = $request->scheduled_start_time;
        }
        if ($request->has('overlay_settings')) {
            $liveStreamSettings->overlay_settings = $request->overlay_settings;
            $match->overlay_settings = $request->overlay_settings;
        }

        $liveStreamSettings->save();
        $match->save();

        return response()->json([
            'message' => 'Live stream settings updated successfully',
            'match' => $match->load(['team1', 'team2', 'venue', 'tournament', 'liveStreamSettings']),
        ]);
    }
}

