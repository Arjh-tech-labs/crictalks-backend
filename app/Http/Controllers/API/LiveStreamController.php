<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Match;
use App\Models\LiveStreamSettings;
use App\Models\OverlayTemplate;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Google_Client;
use Google_Service_YouTube;

class LiveStreamController extends Controller
{
    /**
     * Create a YouTube live stream.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function createYouTubeStream(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'match_id' => 'required|exists:matches,id',
            'title' => 'required|string|max:100',
            'description' => 'nullable|string|max:5000',
            'scheduled_start_time' => 'nullable|date',
            'privacy_status' => 'nullable|string|in:public,unlisted,private',
            'youtube_api_key' => 'required|string',
            'youtube_channel_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $match = Match::findOrFail($request->match_id);
        $user = $request->user();
        $streamerUserType = UserType::where('name', 'Live Streamer')->first();

        // Check if user is the assigned streamer
        if (!$user->userTypes->contains($streamerUserType->id) || $match->streamer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the assigned streamer can create a live stream',
            ], 403);
        }

        try {
            // Initialize the YouTube API client
            $client = new Google_Client();
            $client->setDeveloperKey($request->youtube_api_key);
            $youtube = new Google_Service_YouTube($client);

            // Create a liveBroadcast resource
            $broadcastSnippet = new Google_Service_YouTube_LiveBroadcastSnippet();
            $broadcastSnippet->setTitle($request->title);
            $broadcastSnippet->setDescription($request->description ?? '');
            $broadcastSnippet->setScheduledStartTime($request->scheduled_start_time ?? date('c'));

            $status = new Google_Service_YouTube_LiveBroadcastStatus();
            $status->setPrivacyStatus($request->privacy_status ?? 'public');

            $broadcast = new Google_Service_YouTube_LiveBroadcast();
            $broadcast->setSnippet($broadcastSnippet);
            $broadcast->setStatus($status);
            $broadcast->setKind('youtube#liveBroadcast');

            // Create the broadcast
            $broadcastResponse = $youtube->liveBroadcasts->insert('snippet,status', $broadcast);

            // Create a liveStream resource
            $streamSnippet = new Google_Service_YouTube_LiveStreamSnippet();
            $streamSnippet->setTitle($request->title);

            $cdn = new Google_Service_YouTube_CdnSettings();
            $cdn->setFormat('1080p');
            $cdn->setIngestionType('rtmp');

            $stream = new Google_Service_YouTube_LiveStream();
            $stream->setSnippet($streamSnippet);
            $stream->setCdn($cdn);
            $stream->setKind('youtube#liveStream');

            // Create the stream
            $streamResponse = $youtube->liveStreams->insert('snippet,cdn', $stream);

            // Bind the broadcast to the stream
            $youtube->liveBroadcasts->bind(
                $broadcastResponse->getId(),
                'id,contentDetails',
                ['streamId' => $streamResponse->getId()]
            );

            // Save the stream details
            $liveStreamSettings = LiveStreamSettings::firstOrNew(['match_id' => $request->match_id]);
            $liveStreamSettings->youtube_api_key = $request->youtube_api_key;
            $liveStreamSettings->youtube_channel_id = $request->youtube_channel_id;
            $liveStreamSettings->youtube_stream_id = $streamResponse->getId();
            $liveStreamSettings->stream_title = $request->title;
            $liveStreamSettings->stream_description = $request->description;
            $liveStreamSettings->stream_status = 'scheduled';
            $liveStreamSettings->scheduled_start_time = $request->scheduled_start_time;
            $liveStreamSettings->save();

            // Update the match
            $match->youtube_stream_id = $streamResponse->getId();
            $match->save();

            return response()->json([
                'message' => 'YouTube live stream created successfully',
                'broadcast_id' => $broadcastResponse->getId(),
                'stream_id' => $streamResponse->getId(),
                'stream_url' => $streamResponse->getCdn()->getIngestionInfo()->getIngestionAddress(),
                'stream_key' => $streamResponse->getCdn()->getIngestionInfo()->getStreamName(),
                'live_stream_settings' => $liveStreamSettings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating YouTube live stream: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a YouTube live stream.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateYouTubeStream(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:100',
            'description' => 'nullable

