<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Match;
use App\Models\Innings;
use App\Models\BatsmanInnings;
use App\Models\BowlerInnings;
use App\Models\Ball;
use App\Models\User;
use App\Models\UserType;
use App\Models\PlayerMilestone;
use App\Models\PlayerStatistic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ScoringController extends Controller
{
    /**
     * Start a new innings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function startInnings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'match_id' => 'required|exists:matches,id',
            'batting_team_id' => 'required|exists:teams,id',
            'bowling_team_id' => 'required|exists:teams,id|different:batting_team_id',
            'innings_number' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $match = CricketMatch::findOrFail($request->match_id);
        $user = $request->user();
        $scorerUserType = UserType::where('name', 'Scorer')->first();

        // Check if user is the assigned scorer
        if (!$user->userTypes->contains($scorerUserType->id) || $match->scorer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the assigned scorer can start an innings',
            ], 403);
        }

        // Check if the match is live
        if ($match->status !== 'live') {
            return response()->json([
                'message' => 'Match must be live to start an innings',
            ], 400);
        }

        // Check if the teams are part of the match
        if (
            ($match->team1_id !== $request->batting_team_id && $match->team2_id !== $request->batting_team_id) ||
            ($match->team1_id !== $request->bowling_team_id && $match->team2_id !== $request->bowling_team_id)
        ) {
            return response()->json([
                'message' => 'Teams must be part of the match',
            ], 400);
        }

        // Check if an innings with the same number already exists
        $existingInnings = Innings::where('match_id', $request->match_id)
            ->where('innings_number', $request->innings_number)
            ->first();

        if ($existingInnings) {
            return response()->json([
                'message' => 'An innings with this number already exists',
            ], 400);
        }

        $innings = new Innings();
        $innings->match_id = $request->match_id;
        $innings->batting_team_id = $request->batting_team_id;
        $innings->bowling_team_id = $request->bowling_team_id;
        $innings->innings_number = $request->innings_number;
        $innings->status = 'ongoing';
        $innings->save();

        return response()->json([
            'message' => 'Innings started successfully',
            'innings' => $innings,
        ], 201);
    }

    /**
     * End an innings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function endInnings(Request $request, $id)
    {
        $innings = Innings::findOrFail($id);
        $match = CricketMatch::findOrFail($innings->match_id);
        $user = $request->user();
        $scorerUserType = UserType::where('name', 'Scorer')->first();

        // Check if user is the assigned scorer
        if (!$user->userTypes->contains($scorerUserType->id) || $match->scorer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the assigned scorer can end an innings',
            ], 403);
        }

        // Check if the innings is ongoing
        if ($innings->status !== 'ongoing') {
            return response()->json([
                'message' => 'Innings must be ongoing to end it',
            ], 400);
        }

        $innings->status = 'completed';
        $innings->save();

        // Update match score
        if ($innings->batting_team_id === $match->team1_id) {
            $match->team1_score = $innings->total_runs;
            $match->team1_wickets = $innings->total_wickets;
            $match->team1_overs = $innings->total_overs;
        } else {
            $match->team2_score = $innings->total_runs;
            $match->team2_wickets = $innings->total_wickets;
            $match->team2_overs = $innings->total_overs;
        }
        $match->save();

        return response()->json([
            'message' => 'Innings ended successfully',
            'innings' => $innings,
        ]);
    }

    /**
     * Add batsmen to the innings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addBatsmen(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'batsmen' => 'required|array',
            'batsmen.*.user_id' => 'required|exists:users,id',
            'batsmen.*.batting_position' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $innings = Innings::findOrFail($id);
        $match = CricketMatch::findOrFail($innings->match_id);
        $user = $request->user();
        $scorerUserType = UserType::where('name', 'Scorer')->first();

        // Check if user is the assigned scorer
        if (!$user->userTypes->contains($scorerUserType->id) || $match->scorer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the assigned scorer can add batsmen',
            ], 403);
        }

        // Check if the innings is ongoing
        if ($innings->status !== 'ongoing') {
            return response()->json([
                'message' => 'Innings must be ongoing to add batsmen',
            ], 400);
        }

        $batsmenAdded = [];

        foreach ($request->batsmen as $batsmanData) {
            // Check if the batsman is already in the innings
            $existingBatsman = BatsmanInnings::where('innings_id', $id)
                ->where('user_id', $batsmanData['user_id'])
                ->first();

            if ($existingBatsman) {
                continue;
            }

            // Check if the batting position is already taken
            $existingPosition = BatsmanInnings::where('innings_id', $id)
                ->where('batting_position', $batsmanData['batting_position'])
                ->first();

            if ($existingPosition) {
                continue;
            }

            $batsman = new BatsmanInnings();
            $batsman->innings_id = $id;
            $batsman->user_id = $batsmanData['user_id'];
            $batsman->batting_position = $batsmanData['batting_position'];
            $batsman->status = 'yet_to_bat';
            $batsman->save();

            $batsmenAdded[] = $batsman;
        }

        return response()->json([
            'message' => 'Batsmen added successfully',
            'batsmen' => $batsmenAdded,
        ]);
    }

    /**
     * Add bowlers to the innings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addBowlers(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'bowlers' => 'required|array',
            'bowlers.*' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $innings = Innings::findOrFail($id);
        $match = CricketMatch::findOrFail($innings->match_id);
        $user = $request->user();
        $scorerUserType = UserType::where('name', 'Scorer')->first();

        // Check if user is the assigned scorer
        if (!$user->userTypes->contains($scorerUserType->id) || $match->scorer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the assigned scorer can add bowlers',
            ], 403);
        }

        // Check if the innings is ongoing
        if ($innings->status !== 'ongoing') {
            return response()->json([
                'message' => 'Innings must be ongoing to add bowlers',
            ], 400);
        }

        $bowlersAdded = [];

        foreach ($request->bowlers as $bowlerId) {
            // Check if the bowler is already in the innings
            $existingBowler = BowlerInnings::where('innings_id', $id)
                ->where('user_id', $bowlerId)
                ->first();

            if ($existingBowler) {
                continue;
            }

            $bowler = new BowlerInnings();
            $bowler->innings_id = $id;
            $bowler->user_id = $bowlerId;
            $bowler->save();

            $bowlersAdded[] = $bowler;
        }

        return response()->json([
            'message' => 'Bowlers added successfully',
            'bowlers' => $bowlersAdded,
        ]);
    }

    /**
     * Record a ball.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function recordBall(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'innings_id' => 'required|exists:innings,id',
            'bowler_id' => 'required|exists:users,id',
            'batsman_id' => 'required|exists:users,id',
            'non_striker_id' => 'required|exists:users,id|different:batsman_id',
            'over_number' => 'required|integer|min:0',
            'ball_number' => 'required|integer|min:1|max:6',
            'runs_scored' => 'required|integer|min:0',
            'is_wide' => 'required|boolean',
            'is_no_ball' => 'required|boolean',
            'is_bye' => 'required|boolean',
            'is_leg_bye' => 'required|boolean',
            'is_wicket' => 'required|boolean',
            'wicket_type' => 'required_if:is_wicket,true|nullable|string',
            'wicket_player_out_id' => 'required_if:is_wicket,true|nullable|exists:users,id',
            'wicket_fielder_id' => 'nullable|exists:users,id',
            'commentary' => 'nullable|string',
            'wagon_wheel_data' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $innings = Innings::findOrFail($request->innings_id);
        $match = CricketMatch::findOrFail($innings->match_id);
        $user = $request->user();
        $scorerUserType = UserType::where('name', 'Scorer')->first();

        // Check if user is the assigned scorer
        if (!$user->userTypes->contains($scorerUserType->id) || $match->scorer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the assigned scorer can record balls',
            ], 403);
        }

        // Check if the innings is ongoing
        if ($innings->status !== 'ongoing') {
            return response()->json([
                'message' => 'Innings must be ongoing to record balls',
            ], 400);
        }

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Create the ball record
            $ball = new Ball();
            $ball->innings_id = $request->innings_id;
            $ball->bowler_id = $request->bowler_id;
            $ball->batsman_id = $request->batsman_id;
            $ball->non_striker_id = $request->non_striker_id;
            $ball->over_number = $request->over_number;
            $ball->ball_number = $request->ball_number;
            $ball->runs_scored = $request->runs_scored;
            $ball->is_wide = $request->is_wide;
            $ball->is_no_ball = $request->is_no_ball;
            $ball->is_bye = $request->is_bye;
            $ball->is_leg_bye = $request->is_leg_bye;
            $ball->is_wicket = $request->is_wicket;
            $ball->wicket_type = $request->wicket_type;
            $ball->wicket_player_out_id = $request->wicket_player_out_id;
            $ball->wicket_fielder_id = $request->wicket_fielder_id;
            $ball->commentary = $request->commentary;
            $ball->wagon_wheel_data = $request->wagon_wheel_data;
            $ball->save();

            // Update batsman innings
            $batsmanInnings = BatsmanInnings::where('innings_id', $request->innings_id)
                ->where('user_id', $request->batsman_id)
                ->first();

            if (!$batsmanInnings) {
                throw new \Exception('Batsman not found in the innings');
            }

            if ($batsmanInnings->status === 'yet_to_bat') {
                $batsmanInnings->status = 'batting';
            }

            // Update batsman statistics
            if (!$request->is_wide && !$request->is_bye && !$request->is_leg_bye) {
                $batsmanInnings->runs_scored += $request->runs_scored;
                $batsmanInnings->balls_faced += 1;

                if ($request->runs_scored === 4) {
                    $batsmanInnings->fours += 1;
                } elseif ($request->runs_scored === 6) {
                    $batsmanInnings->sixes += 1;
                }
            }

            // Update wagon wheel data
            if ($request->wagon_wheel_data) {
                $wagonWheelData = $batsmanInnings->wagon_wheel_data ?? [];
                $wagonWheelData[] = $request->wagon_wheel_data;
                $batsmanInnings->wagon_wheel_data = $wagonWheelData;
            }

            // Handle wicket
            if ($request->is_wicket) {
                $playerOutInnings = BatsmanInnings::where('innings_id', $request->innings_id)
                    ->where('user_id', $request->wicket_player_out_id)
                    ->first();

                if (!$playerOutInnings) {
                    throw new \Exception('Player out not found in the innings');
                }

                $playerOutInnings->is_out = true;
                $playerOutInnings->dismissal_type = $request->wicket_type;
                $playerOutInnings->bowler_id = $request->bowler_id;
                $playerOutInnings->fielder_id = $request->wicket_fielder_id;
                $playerOutInnings->status = 'out';
                $playerOutInnings->save();

                // Update innings wickets
                $innings->total_wickets += 1;
            }

            $batsmanInnings->save();

            // Update bowler innings
            $bowlerInnings = BowlerInnings::where('innings_id', $request->innings_id)
                ->where('user_id', $request->bowler_id)
                ->first();

            if (!$bowlerInnings) {
                throw new \Exception('Bowler not found in the innings');
            }

            // Update bowler statistics
            if (!$request->is_wide && !$request->is_no_ball) {
                $bowlerInnings->overs = floor($bowlerInnings->overs) + ($request->ball_number === 6 ? 1 : 0) + ($request->ball_number / 10);
            }

            if ($request->is_wide) {
                $bowlerInnings->wides += 1;
                $bowlerInnings->runs_conceded += 1 + $request->runs_scored;
            } elseif ($request->is_no_ball) {
                $bowlerInnings->no_balls += 1;
                $bowlerInnings->runs_conceded += 1 + $request->runs_scored;
            } else {
                $bowlerInnings->runs_conceded += $request->runs_scored;
            }

            if ($request->is_wicket && in_array($request->wicket_type, ['bowled', 'lbw', 'caught', 'hit wicket', 'stumped'])) {
                $bowlerInnings->wickets += 1;
            }

            $bowlerInnings->save();

            // Update innings statistics
            if ($request->is_wide) {
                $innings->wides += 1;
                $innings->extras += 1 + $request->runs_scored;
                $innings->total_runs += 1 + $request->runs_scored;
            } elseif ($request->is_no_ball) {
                $innings->no_balls += 1;
                $innings->extras += 1 + $request->runs_scored;
                $innings->total_runs += 1 + $request->runs_scored;
            } elseif ($request->is_bye) {
                $innings->byes += $request->runs_scored;
                $innings->extras += $request->runs_scored;
                $innings->total_runs += $request->runs_scored;
            } elseif ($request->is_leg_bye) {
                $innings->leg_byes += $request->runs_scored;
                $innings->extras += $request->runs_scored;
                $innings->total_runs += $request->runs_scored;
            } else {
                $innings->total_runs += $request->runs_scored;
            }

            if (!$request->is_wide && !$request->is_no_ball) {
                $innings->total_overs = floor($innings->total_overs) + ($request->ball_number === 6 && $request->over_number > floor($innings->total_overs) ? 1 : 0) + ($request->ball_number / 10);
            }

            $innings->save();

            // Update match score
            if ($innings->batting_team_id === $match->team1_id) {
                $match->team1_score = $innings->total_runs;
                $match->team1_wickets = $innings->total_wickets;
                $match->team1_overs = $innings->total_overs;
            } else {
                $match->team2_score = $innings->total_runs;
                $match->team2_wickets = $innings->total_wickets;
                $match->team2_overs = $innings->total_overs;
            }
            $match->save();

            // Check for milestones
            $this->checkBatsmanMilestones($request->batsman_id, $batsmanInnings, $match);
            $this->checkBowlerMilestones($request->bowler_id, $bowlerInnings, $match);
            if ($request->is_wicket && $request->wicket_fielder_id) {
                $this->checkFielderMilestones($request->wicket_fielder_id, $request->wicket_type, $match);
            }

            // Commit the transaction
            DB::commit();

            return response()->json([
                'message' => 'Ball recorded successfully',
                'ball' => $ball,
                'innings' => $innings,
                'match' => $match,
            ]);
        } catch (\Exception $e) {
            // Rollback the transaction in case of an error
            DB::rollBack();

            return response()->json([
                'message' => 'Error recording ball: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check for batsman milestones.
     *
     * @param  int  $batsmanId
     * @param  \App\Models\BatsmanInnings  $batsmanInnings
     * @param  \App\Models\Match  $match
     * @return void
     */
    private function checkBatsmanMilestones($batsmanId, $batsmanInnings, $match)
    {
        // Get player statistics
        $playerStatistic = PlayerStatistic::where('user_id', $batsmanId)
            ->where('format', $match->match_type)
            ->first();

        if (!$playerStatistic) {
            $playerStatistic = new PlayerStatistic();
            $playerStatistic->user_id = $batsmanId;
            $playerStatistic->format = $match->match_type;
            $playerStatistic->save();
        }

        // Update player statistics
        $playerStatistic->runs_scored += $batsmanInnings->runs_scored;
        $playerStatistic->balls_faced += $batsmanInnings->balls_faced;
        $playerStatistic->fours += $batsmanInnings->fours;
        $playerStatistic->sixes += $batsmanInnings->sixes;

        if ($batsmanInnings->runs_scored > $playerStatistic->highest_score) {
            $playerStatistic->highest_score = $batsmanInnings->runs_scored;
        }

        // Check for fifties and hundreds
        if ($batsmanInnings->runs_scored >= 50 && $batsmanInnings->runs_scored < 100) {
            $playerStatistic->fifties += 1;
            
            // Create milestone
            PlayerMilestone::create([
                'user_id' => $batsmanId,
                'milestone_type' => 'fifty',
                'milestone_value' => $batsmanInnings->runs_scored,
                'match_id' => $match->id,
                'achieved_at' => now(),
                'description' => 'Scored ' . $batsmanInnings->runs_scored . ' runs',
            ]);
        } elseif ($batsmanInnings->runs_scored >= 100) {
            $playerStatistic->hundreds += 1;
            
            // Create milestone
            PlayerMilestone::create([
                'user_id' => $batsmanId,
                'milestone_type' => 'hundred',
                'milestone_value' => $batsmanInnings->runs_scored,
                'match_id' => $match->id,
                'achieved_at' => now(),
                'description' => 'Scored ' . $batsmanInnings->runs_scored . ' runs',
            ]);
        }

        // Check for run milestones (1000, 5000, 10000)
        $runMilestones = [1000, 5000, 10000];
        foreach ($runMilestones as $milestone) {
            if ($playerStatistic->runs_scored - $batsmanInnings->runs_scored < $milestone && $playerStatistic->runs_scored >= $milestone) {
                // Create milestone
                PlayerMilestone::create([
                    'user_id' => $batsmanId,
                    'milestone_type' => 'runs',
                    'milestone_value' => $milestone,
                    'match_id' => $match->id,
                    'achieved_at' => now(),
                    'description' => 'Reached ' . $milestone . ' runs in ' . $match->match_type,
                ]);
            }
        }

        $playerStatistic->save();
    }

    /**
     * Check for bowler milestones.
     *
     * @param  int  $bowlerId
     * @param  \App\Models\BowlerInnings  $bowlerInnings
     * @param  \App\Models\Match  $match
     * @return void
     */
    private function checkBowlerMilestones($bowlerId, $bowlerInnings, $match)
    {
        // Get player statistics
        $playerStatistic = PlayerStatistic::where('user_id', $bowlerId)
            ->where('format', $match->match_type)
            ->first();

        if (!$playerStatistic) {
            $playerStatistic = new PlayerStatistic();
            $playerStatistic->user_id = $bowlerId;
            $playerStatistic->format = $match->match_type;
            $playerStatistic->save();
        }

        // Update player statistics
        $playerStatistic->overs_bowled += $bowlerInnings->overs;
        $playerStatistic->runs_conceded += $bowlerInnings->runs_conceded;
        $playerStatistic->wickets_taken += $bowlerInnings->wickets;

        // Check for 4 and 5 wicket hauls
        if ($bowlerInnings->wickets >= 4 && $bowlerInnings->wickets < 5) {
            $playerStatistic->four_wickets += 1;
            
            // Create milestone
            PlayerMilestone::create([
                'user_id' => $bowlerId,
                'milestone_type' => 'four_wickets',
                'milestone_value' => $bowlerInnings->wickets,
                'match_id' => $match->id,
                'achieved_at' => now(),
                'description' => 'Took ' . $bowlerInnings->wickets . ' wickets',
            ]);
        } elseif ($bowlerInnings->wickets >= 5) {
            $playerStatistic->five_wickets += 1;
            
            // Create milestone
            PlayerMilestone::create([
                'user_id' => $bowlerId,
                'milestone_type' => 'five_wickets',
                'milestone_value' => $bowlerInnings->wickets,
                'match_id' => $match->id,
                'achieved_at' => now(),
                'description' => 'Took ' . $bowlerInnings->wickets . ' wickets',
            ]);
        }

        // Check for wicket milestones (100, 200, 300, 400, 500)
        $wicketMilestones = [100, 200, 300, 400, 500];
        foreach ($wicketMilestones as $milestone) {
            if ($playerStatistic->wickets_taken - $bowlerInnings->wickets < $milestone && $playerStatistic->wickets_taken >= $milestone) {
                // Create milestone
                PlayerMilestone::create([
                    'user_id' => $bowlerId,
                    'milestone_type' => 'wickets',
                    'milestone_value' => $milestone,
                    'match_id' => $match->id,
                    'achieved_at' => now(),
                    'description' => 'Reached ' . $milestone . ' wickets in ' . $match->match_type,
                ]);
            }
        }

        $playerStatistic->save();
    }

    /**
     * Check for fielder milestones.
     *
     * @param  int  $fielderId
     * @param  string  $wicketType
     * @param  \App\Models\Match  $match
     * @return void
     */
    private function checkFielderMilestones($fielderId, $wicketType, $match)
    {
        // Get player statistics
        $playerStatistic = PlayerStatistic::where('user_id', $fielderId)
            ->where('format', $match->match_type)
            ->first();

        if (!$playerStatistic) {
            $playerStatistic = new PlayerStatistic();
            $playerStatistic->user_id = $fielderId;
            $playerStatistic->format = $match->match_type;
            $playerStatistic->save();
        }

        // Update player statistics based on wicket type
        if ($wicketType === 'caught') {
            $playerStatistic->catches += 1;
            
            // Check for catch milestones (100, 200, 300)
            $catchMilestones = [100, 200, 300];
            foreach ($catchMilestones as $milestone) {
                if ($playerStatistic->catches - 1 < $milestone && $playerStatistic->catches >= $milestone) {
                    // Create milestone
                    PlayerMilestone::create([
                        'user_id' => $fielderId,
                        'milestone_type' => 'catches',
                        'milestone_value' => $milestone,
                        'match_id' => $match->id,
                        'achieved_at' => now(),
                        'description' => 'Reached ' . $milestone . ' catches in ' . $match->match_type,
                    ]);
                }
            }
        } elseif ($wicketType === 'stumped') {
            $playerStatistic->stumpings += 1;
            
            // Check for stumping milestones (100, 200, 300)
            $stumpingMilestones = [100, 200, 300];
            foreach ($stumpingMilestones as $milestone) {
                if ($playerStatistic->stumpings - 1 < $milestone && $playerStatistic->stumpings >= $milestone) {
                    // Create milestone
                    PlayerMilestone::create([
                        'user_id' => $fielderId,
                        'milestone_type' => 'stumpings',
                        'milestone_value' => $milestone,
                        'match_id' => $match->id,
                        'achieved_at' => now(),
                        'description' => 'Reached ' . $milestone . ' stumpings in ' . $match->match_type,
                    ]);
                }
            }
        } elseif ($wicketType === 'run out') {
            $playerStatistic->run_outs += 1;
            
            // Check for run out milestones (100, 200, 300)
            $runOutMilestones = [100, 200, 300];
            foreach ($runOutMilestones as $milestone) {
                if ($playerStatistic->run_outs - 1 < $milestone && $playerStatistic->run_outs >= $milestone) {
                    // Create milestone
                    PlayerMilestone::create([
                        'user_id' => $fielderId,
                        'milestone_type' => 'run_outs',
                        'milestone_value' => $milestone,
                        'match_id' => $match->id,
                        'achieved_at' => now(),
                        'description' => 'Reached ' . $milestone . ' run outs in ' . $match->match_type,
                    ]);
                }
            }
        }

        $playerStatistic->save();
    }

    /**
     * Get the current innings details.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getInningsDetails($id)
    {
        $innings = Innings::with([
            'match',
            'battingTeam',
            'bowlingTeam',
            'batsmanInnings',
            'bowlerInnings',
            'balls' => function ($query) {
                $query->orderBy('over_number', 'desc')
                      ->orderBy('ball_number', 'desc')
                      ->limit(10);
            }
        ])->findOrFail($id);

        return response()->json($innings);
    }

    /**
     * Get the current match score.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getMatchScore($id)
    {
        $match = CricketMatch::with([
            'team1',
            'team2',
            'innings' => function ($query) {
                $query->orderBy('innings_number', 'desc');
            }
        ])->findOrFail($id);

        return response()->json($match);
    }

    /**
     * Get the current batsmen at the crease.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getCurrentBatsmen($id)
    {
        $innings = Innings::findOrFail($id);
        $batsmen = BatsmanInnings::with('batsman')
            ->where('innings_id', $id)
            ->where('status', 'batting')
            ->get();

        return response()->json($batsmen);
    }

    /**
     * Get the current bowler.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getCurrentBowler($id)
    {
        $innings = Innings::findOrFail($id);
        
        // Get the last ball to find the current bowler
        $lastBall = Ball::where('innings_id', $id)
            ->orderBy('over_number', 'desc')
            ->orderBy('ball_number', 'desc')
            ->first();
        
        if (!$lastBall) {
            return response()->json([
                'message' => 'No balls bowled yet',
            ], 404);
        }
        
        $bowler = BowlerInnings::with('bowler')
            ->where('innings_id', $id)
            ->where('user_id', $lastBall->bowler_id)
            ->first();

        return response()->json($bowler);
    }

    /**
     * Update batsman status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateBatsmanStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:yet_to_bat,batting,out,retired_hurt,retired_not_out',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $batsmanInnings = BatsmanInnings::findOrFail($id);
        $innings = Innings::findOrFail($batsmanInnings->innings_id);
        $match = CricketMatch::findOrFail($innings->match_id);
        $user = $request->user();
        $scorerUserType = UserType::where('name', 'Scorer')->first();

        // Check if user is the assigned scorer
        if (!$user->userTypes->contains($scorerUserType->id) || $match->scorer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the assigned scorer can update batsman status',
            ], 403);
        }

        // Check if the innings is ongoing
        if ($innings->status !== 'ongoing') {
            return response()->json([
                'message' => 'Innings must be ongoing to update batsman status',
            ], 400);
        }

        $batsmanInnings->status = $request->status;
        
        // If batsman is out or retired, update is_out
        if (in_array($request->status, ['out', 'retired_hurt'])) {
            $batsmanInnings->is_out = true;
        } elseif ($request->status === 'retired_not_out') {
            $batsmanInnings->is_out = false;
        }
        
        $batsmanInnings->save();

        return response()->json([
            'message' => 'Batsman status updated successfully',
            'batsman_innings' => $batsmanInnings,
        ]);
    }

    /**
     * Get the wagon wheel data for a batsman.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getBatsmanWagonWheel($id)
    {
        $batsmanInnings = BatsmanInnings::findOrFail($id);
        
        return response()->json([
            'wagon_wheel_data' => $batsmanInnings->wagon_wheel_data,
        ]);
    }

    /**
     * Get the over details.
     *
     * @param  int  $id
     * @param  int  $overNumber
     * @return \Illuminate\Http\Response
     */
    public function getOverDetails($id, $overNumber)
    {
        $innings = Innings::findOrFail($id);
        $balls = Ball::with(['bowler', 'batsman', 'nonStriker', 'playerOut', 'fielder'])
            ->where('innings_id', $id)
            ->where('over_number', $overNumber)
            ->orderBy('ball_number')
            ->get();
        
        return response()->json($balls);
    }

    /**
     * Get the partnership details.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getPartnerships($id)
    {
        $innings = Innings::findOrFail($id);
        $balls = Ball::with(['batsman', 'nonStriker'])
            ->where('innings_id', $id)
            ->orderBy('over_number')
            ->orderBy('ball_number')
            ->get();
        
        $partnerships = [];
        $currentPartnership = null;
        $currentBatsmen = [];
        $runsInPartnership = 0;
        $ballsInPartnership = 0;
        
        foreach ($balls as $ball) {
            $batsmanId = $ball->batsman_id;
            $nonStrikerId = $ball->non_striker_id;
            
            // Sort batsmen IDs to create a unique key for the partnership
            $partnershipKey = $batsmanId < $nonStrikerId ? 
                $batsmanId . '-' . $nonStrikerId : 
                $nonStrikerId . '-' . $batsmanId;
            
            if ($currentPartnership !== $partnershipKey) {
                // Save the previous partnership
                if ($currentPartnership) {
                    $partnerships[] = [
                        'batsmen' => $currentBatsmen,
                        'runs' => $runsInPartnership,
                        'balls' => $ballsInPartnership,
                    ];
                }
                
                // Start a new partnership
                $currentPartnership = $partnershipKey;
                $currentBatsmen = [
                    $ball->batsman,
                    $ball->nonStriker,
                ];
                $runsInPartnership = 0;
                $ballsInPartnership = 0;
            }
            
            // Add runs to the partnership
            $runsInPartnership += $ball->runs_scored;
            
            // Count legal deliveries
            if (!$ball->is_wide && !$ball->is_no_ball) {
                $ballsInPartnership += 1;
            }
            
            // If a wicket falls, end the partnership
            if ($ball->is_wicket) {
                $partnerships[] = [
                    'batsmen' => $currentBatsmen,
                    'runs' => $runsInPartnership,
                    'balls' => $ballsInPartnership,
                ];
                
                $currentPartnership = null;
                $currentBatsmen = [];
                $runsInPartnership = 0;
                $ballsInPartnership = 0;
            }
        }
        
        // Add the last partnership if it exists
        if ($currentPartnership) {
            $partnerships[] = [
                'batsmen' => $currentBatsmen,
                'runs' => $runsInPartnership,
                'balls' => $ballsInPartnership,
            ];
        }
        
        return response()->json($partnerships);
    }
}

