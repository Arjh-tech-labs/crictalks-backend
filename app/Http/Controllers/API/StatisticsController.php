<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\Match;
use App\Models\PlayerStatistic;
use App\Models\BatsmanInnings;
use App\Models\BowlerInnings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    /**
     * Get player statistics.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getPlayerStatistics($id)
    {
        $user = User::findOrFail($id);
        $statistics = PlayerStatistic::where('user_id', $id)->get();
        $batsmanInnings = BatsmanInnings::where('user_id', $id)
            ->with(['innings.match', 'innings.battingTeam', 'innings.bowlingTeam'])
            ->get();
        $bowlerInnings = BowlerInnings::where('user_id', $id)
            ->with(['innings.match', 'innings.battingTeam', 'innings.bowlingTeam'])
            ->get();

        return response()->json([
            'user' => $user,
            'statistics' => $statistics,
            'batting_innings' => $batsmanInnings,
            'bowling_innings' => $bowlerInnings,
        ]);
    }

    /**
     * Get team statistics.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getTeamStatistics($id)
    {
        $team = Team::with('players')->findOrFail($id);
        
        // Get matches
        $matches = CricketMatch::where('team1_id', $id)
            ->orWhere('team2_id', $id)
            ->with(['team1', 'team2', 'venue', 'tournament'])
            ->get();
        
        // Calculate win/loss ratio
        $totalMatches = $matches->count();
        $wins = $matches->where('match_winner_id', $id)->count();
        $losses = $matches->where('status', 'completed')
            ->where('match_winner_id', '!=', $id)
            ->where(function ($query) use ($id) {
                $query->where('team1_id', $id)
                    ->orWhere('team2_id', $id);
            })
            ->count();
        $draws = $totalMatches - $wins - $losses;
        
        // Get top performers
        $topBatsmen = BatsmanInnings::whereHas('innings', function ($query) use ($id) {
                $query->where('batting_team_id', $id);
            })
            ->with('batsman')
            ->select('user_id', DB::raw('SUM(runs_scored) as total_runs'), DB::raw('COUNT(*) as innings_count'))
            ->groupBy('user_id')
            ->orderBy('total_runs', 'desc')
            ->limit(5)
            ->get();
        
        $topBowlers = BowlerInnings::whereHas('innings', function ($query) use ($id) {
                $query->where('bowling_team_id', $id);
            })
            ->with('bowler')
            ->select('user_id', DB::raw('SUM(wickets) as total_wickets'), DB::raw('COUNT(*) as innings_count'))
            ->groupBy('user_id')
            ->orderBy('total_wickets', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'team' => $team,
            'matches' => [
                'total' => $totalMatches,
                'wins' => $wins,
                'losses' => $losses,
                'draws' => $draws,
                'win_percentage' => $totalMatches > 0 ? round(($wins / $totalMatches) * 100, 2) : 0,
            ],
            'top_batsmen' => $topBatsmen,
            'top_bowlers' => $topBowlers,
            'recent_matches' => $matches->sortByDesc('scheduled_date')->take(5)->values(),
        ]);
    }

    /**
     * Get tournament statistics.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getTournamentStatistics($id)
    {
        $tournament = Tournament::with(['teams', 'matches'])->findOrFail($id);
        
        // Get top run scorers
        $topRunScorers = BatsmanInnings::whereHas('innings.match', function ($query) use ($id) {
                $query->where('tournament_id', $id);
            })
            ->with('batsman')
            ->select('user_id', DB::raw('SUM(runs_scored) as total_runs'), DB::raw('COUNT(*) as innings_count'))
            ->groupBy('user_id')
            ->orderBy('total_runs', 'desc')
            ->limit(10)
            ->get();
        
        // Get top wicket takers
        $topWicketTakers = BowlerInnings::whereHas('innings.match', function ($query) use ($id) {
                $query->where('tournament_id', $id);
            })
            ->with('bowler')
            ->select('user_id', DB::raw('SUM(wickets) as total_wickets'), DB::raw('COUNT(*) as innings_count'))
            ->groupBy('user_id')
            ->orderBy('total_wickets', 'desc')
            ->limit(10)
            ->get();
        
        // Get highest team scores
        $highestTeamScores = CricketMatch::where('tournament_id', $id)
            ->whereNotNull('team1_score')
            ->whereNotNull('team2_score')
            ->with(['team1', 'team2'])
            ->get()
            ->map(function ($match) {
                return [
                    'match_id' => $match->id,
                    'team1' => [
                        'id' => $match->team1_id,
                        'name' => $match->team1->name,
                        'score' => $match->team1_score,
                        'wickets' => $match->team1_wickets,
                        'overs' => $match->team1_overs,
                    ],
                    'team2' => [
                        'id' => $match->team2_id,
                        'name' => $match->team2->name,
                        'score' => $match->team2_score,
                        'wickets' => $match->team2_wickets,
                        'overs' => $match->team2_overs,
                    ],
                    'date' => $match->scheduled_date,
                ];
            })
            ->sortByDesc(function ($match) {
                return max($match['team1']['score'], $match['team2']['score']);
            })
            ->take(5)
            ->values();
        
        // Get highest individual scores
        $highestIndividualScores = BatsmanInnings::whereHas('innings.match', function ($query) use ($id) {
                $query->where('tournament_id', $id);
            })
            ->with(['batsman', 'innings.match', 'innings.battingTeam', 'innings.bowlingTeam'])
            ->orderBy('runs_scored', 'desc')
            ->limit(5)
            ->get();
        
        // Get best bowling figures
        $bestBowlingFigures = BowlerInnings::whereHas('innings.match', function ($query) use ($id) {
                $query->where('tournament_id', $id);
            })
            ->with(['bowler', 'innings.match', 'innings.battingTeam', 'innings.bowlingTeam'])
            ->orderBy('wickets', 'desc')
            ->orderBy('runs_conceded', 'asc')
            ->limit(5)
            ->get();

        return response()->json([
            'tournament' => $tournament,
            'top_run_scorers' => $topRunScorers,
            'top_wicket_takers' => $topWicketTakers,
            'highest_team_scores' => $highestTeamScores,
            'highest_individual_scores' => $highestIndividualScores,
            'best_bowling_figures' => $bestBowlingFigures,
        ]);
    }

    /**
     * Get match statistics.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getMatchStatistics($id)
    {
        $match = CricketMatch::with([
            'team1', 
            'team2', 
            'venue', 
            'tournament', 
            'innings.batsmanInnings.batsman',
            'innings.bowlerInnings.bowler',
            'innings.balls'
        ])->findOrFail($id);
        
        // Calculate match summary
        $matchSummary = [
            'total_runs' => 0,
            'total_wickets' => 0,
            'total_overs' => 0,
            'total_fours' => 0,
            'total_sixes' => 0,
            'run_rate' => 0,
        ];
        
        foreach ($match->innings as $innings) {
            $matchSummary['total_runs'] += $innings->total_runs;
            $matchSummary['total_wickets'] += $innings->total_wickets;
            $matchSummary['total_overs'] += $innings->total_overs;
            
            foreach ($innings->batsmanInnings as $batsmanInnings) {
                $matchSummary['total_fours'] += $batsmanInnings->fours;
                $matchSummary['total_sixes'] += $batsmanInnings->sixes;
            }
        }
        
        if ($matchSummary['total_overs'] > 0) {
            $matchSummary['run_rate'] = round($matchSummary['total_runs'] / $matchSummary['total_overs'], 2);
        }
        
        // Get top performers
        $topBatsmen = BatsmanInnings::whereHas('innings', function ($query) use ($id) {
                $query->where('match_id', $id);
            })
            ->with('batsman')
            ->orderBy('runs_scored', 'desc')
            ->limit(3)
            ->get();
        
        $topBowlers = BowlerInnings::whereHas('innings', function ($query) use ($id) {
                $query->where('match_id', $id);
            })
            ->with('bowler')
            ->orderBy('wickets', 'desc')
            ->orderBy('runs_conceded', 'asc')
            ->limit(3)
            ->get();
        
        // Calculate partnerships
        $partnerships = [];
        foreach ($match->innings as $innings) {
            $inningsPartnerships = [];
            $balls = $innings->balls->sortBy(function ($ball) {
                return $ball->over_number * 10 + $ball->ball_number;
            });
            
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
                        $inningsPartnerships[] = [
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
                    $inningsPartnerships[] = [
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
                $inningsPartnerships[] = [
                    'batsmen' => $currentBatsmen,
                    'runs' => $runsInPartnership,
                    'balls' => $ballsInPartnership,
                ];
            }
            
            $partnerships[] = [
                'innings_number' => $innings->innings_number,
                'batting_team' => $innings->battingTeam,
                'partnerships' => $inningsPartnerships,
            ];
        }

        return response()->json([
            'match' => $match,
            'match_summary' => $matchSummary,
            'top_batsmen' => $topBatsmen,
            'top_bowlers' => $topBowlers,
            'partnerships' => $partnerships,
        ]);
    }

    /**
     * Get leaderboards.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getLeaderboards(Request $request)
    {
        $format = $request->format ?? 'T20';
        
        // Top run scorers
        $topRunScorers = PlayerStatistic::where('format', $format)
            ->with('user')
            ->orderBy('runs_scored', 'desc')
            ->limit(10)
            ->get();
        
        // Top wicket takers
        $topWicketTakers = PlayerStatistic::where('format', $format)
            ->with('user')
            ->orderBy('wickets_taken', 'desc')
            ->limit(10)
            ->get();
        
        // Highest batting averages (min 10 innings)
        $highestBattingAverages = PlayerStatistic::where('format', $format)
            ->where('innings_batted', '>=', 10)
            ->with('user')
            ->orderBy('batting_average', 'desc')
            ->limit(10)
            ->get();
        
        // Best bowling averages (min 10 wickets)
        $bestBowlingAverages = PlayerStatistic::where('format', $format)
            ->where('wickets_taken', '>=', 10)
            ->with('user')
            ->orderBy('bowling_average', 'asc')
            ->limit(10)
            ->get();
        
        // Highest strike rates (min 10 innings)
        $highestStrikeRates = PlayerStatistic::where('format', $format)
            ->where('innings_batted', '>=', 10)
            ->with('user')
            ->orderBy('batting_strike_rate', 'desc')
            ->limit(10)
            ->get();
        
        // Best economy rates (min 10 wickets)
        $bestEconomyRates = PlayerStatistic::where('format', $format)
            ->where('wickets_taken', '>=', 10)
            ->with('user')
            ->orderBy('economy_rate', 'asc')
            ->limit(10)
            ->get();

        return response()->json([
            'format' => $format,
            'top_run_scorers' => $topRunScorers,
            'top_wicket_takers' => $topWicketTakers,
            'highest_batting_averages' => $highestBattingAverages,
            'best_bowling_averages' => $bestBowlingAverages,
            'highest_strike_rates' => $highestStrikeRates,
            'best_economy_rates' => $bestEconomyRates,
        ]);
    }
}

