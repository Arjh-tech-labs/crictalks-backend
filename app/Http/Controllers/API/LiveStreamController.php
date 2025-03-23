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

        $match = CricketMatch::findOrFail($request->match_id);
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
            'description' => 'nullable|string|max:5000',
            'scheduled_start_time' => 'nullable|date',
            'privacy_status' => 'nullable|string|in:public,unlisted,private',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $liveStreamSettings = LiveStreamSettings::findOrFail($id);
        $match = CricketMatch::findOrFail($liveStreamSettings->match_id);
        $user = $request->user();
        $streamerUserType = UserType::where('name', 'Live Streamer')->first();

        // Check if user is the assigned streamer
        if (!$user->userTypes->contains($streamerUserType->id) || $match->streamer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the assigned streamer can update a live stream',
            ], 403);
        }

        try {
            // Initialize the YouTube API client
            $client = new Google_Client();
            $client->setDeveloperKey($liveStreamSettings->youtube_api_key);
            $youtube = new Google_Service_YouTube($client);

            // Get the broadcast
            $broadcasts = $youtube->liveBroadcasts->listLiveBroadcasts('id,snippet,status', [
                'id' => $liveStreamSettings->youtube_stream_id
            ]);

            if (count($broadcasts->getItems()) === 0) {
                return response()->json([
                    'message' => 'Broadcast not found',
                ], 404);
            }

            $broadcast = $broadcasts->getItems()[0];
            $broadcastSnippet = $broadcast->getSnippet();
            $broadcastStatus = $broadcast->getStatus();

            // Update the broadcast
            if ($request->has('title')) {
                $broadcastSnippet->setTitle($request->title);
                $liveStreamSettings->stream_title = $request->title;
            }

            if ($request->has('description')) {
                $broadcastSnippet->setDescription($request->description);
                $liveStreamSettings->stream_description = $request->description;
            }

            if ($request->has('scheduled_start_time')) {
                $broadcastSnippet->setScheduledStartTime($request->scheduled_start_time);
                $liveStreamSettings->scheduled_start_time = $request->scheduled_start_time;
            }

            if ($request->has('privacy_status')) {
                $broadcastStatus->setPrivacyStatus($request->privacy_status);
            }

            $broadcast->setSnippet($broadcastSnippet);
            $broadcast->setStatus($broadcastStatus);

            // Update the broadcast
            $youtube->liveBroadcasts->update('snippet,status', $broadcast);

            // Save the updated settings
            $liveStreamSettings->save();

            return response()->json([
                'message' => 'YouTube live stream updated successfully',
                'live_stream_settings' => $liveStreamSettings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating YouTube live stream: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start a YouTube live stream.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function startYouTubeStream(Request $request, $id)
    {
        $liveStreamSettings = LiveStreamSettings::findOrFail($id);
        $match = CricketMatch::findOrFail($liveStreamSettings->match_id);
        $user = $request->user();
        $streamerUserType = UserType::where('name', 'Live Streamer')->first();

        // Check if user is the assigned streamer
        if (!$user->userTypes->contains($streamerUserType->id) || $match->streamer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the assigned streamer can start a live stream',
            ], 403);
        }

        try {
            // Initialize the YouTube API client
            $client = new Google_Client();
            $client->setDeveloperKey($liveStreamSettings->youtube_api_key);
            $youtube = new Google_Service_YouTube($client);

            // Transition the broadcast to live
            $youtube->liveBroadcasts->transition('live', $liveStreamSettings->youtube_stream_id, 'id,snippet,status');

            // Update the stream status
            $liveStreamSettings->stream_status = 'live';
            $liveStreamSettings->save();

            // Update the match status
            $match->status = 'live';
            $match->save();

            return response()->json([
                'message' => 'YouTube live stream started successfully',
                'live_stream_settings' => $liveStreamSettings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error starting YouTube live stream: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * End a YouTube live stream.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function endYouTubeStream(Request $request, $id)
    {
        $liveStreamSettings = LiveStreamSettings::findOrFail($id);
        $match = CricketMatch::findOrFail($liveStreamSettings->match_id);
        $user = $request->user();
        $streamerUserType = UserType::where('name', 'Live Streamer')->first();

        // Check if user is the assigned streamer
        if (!$user->userTypes->contains($streamerUserType->id) || $match->streamer_id !== $user->id) {
            return response()->json([
                'message' => 'Only the assigned streamer can end a live stream',
            ], 403);
        }

        try {
            // Initialize the YouTube API client
            $client = new Google_Client();
            $client->setDeveloperKey($liveStreamSettings->youtube_api_key);
            $youtube = new Google_Service_YouTube($client);

            // Transition the broadcast to completed
            $youtube->liveBroadcasts->transition('complete', $liveStreamSettings->youtube_stream_id, 'id,snippet,status');

            // Update the stream status
            $liveStreamSettings->stream_status = 'completed';
            $liveStreamSettings->save();

            return response()->json([
                'message' => 'YouTube live stream ended successfully',
                'live_stream_settings' => $liveStreamSettings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error ending YouTube live stream: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the status of a YouTube live stream.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getYouTubeStreamStatus($id)
    {
        $liveStreamSettings = LiveStreamSettings::findOrFail($id);
        $match = CricketMatch::findOrFail($liveStreamSettings->match_id);

        try {
            // Initialize the YouTube API client
            $client = new Google_Client();
            $client->setDeveloperKey($liveStreamSettings->youtube_api_key);
            $youtube = new Google_Service_YouTube($client);

            // Get the broadcast
            $broadcasts = $youtube->liveBroadcasts->listLiveBroadcasts('id,snippet,status', [
                'id' => $liveStreamSettings->youtube_stream_id
            ]);

            if (count($broadcasts->getItems()) === 0) {
                return response()->json([
                    'message' => 'Broadcast not found',
                ], 404);
            }

            $broadcast = $broadcasts->getItems()[0];
            $status = $broadcast->getStatus();

            return response()->json([
                'broadcast_id' => $broadcast->getId(),
                'title' => $broadcast->getSnippet()->getTitle(),
                'description' => $broadcast->getSnippet()->getDescription(),
                'scheduled_start_time' => $broadcast->getSnippet()->getScheduledStartTime(),
                'actual_start_time' => $broadcast->getSnippet()->getActualStartTime(),
                'actual_end_time' => $broadcast->getSnippet()->getActualEndTime(),
                'privacy_status' => $status->getPrivacyStatus(),
                'life_cycle_status' => $status->getLifeCycleStatus(),
                'recording_status' => $status->getRecordingStatus(),
                'concurrent_viewers' => $broadcast->getSnippet()->getConcurrentViewers(),
                'stream_status' => $liveStreamSettings->stream_status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error getting YouTube live stream status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the YouTube authentication URL.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getYouTubeAuthUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string',
            'redirect_uri' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Initialize the YouTube API client
            $client = new Google_Client();
            $client->setClientId($request->client_id);
            $client->setRedirectUri($request->redirect_uri);
            $client->setScopes([
                'https://www.googleapis.com/auth/youtube',
                'https://www.googleapis.com/auth/youtube.force-ssl',
            ]);
            $client->setAccessType('offline');
            $client->setPrompt('consent');

            // Generate the auth URL
            $authUrl = $client->createAuthUrl();

            return response()->json([
                'auth_url' => $authUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error generating YouTube auth URL: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle the YouTube authentication callback.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handleYouTubeAuthCallback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'redirect_uri' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Initialize the YouTube API client
            $client = new Google_Client();
            $client->setClientId($request->client_id);
            $client->setClientSecret($request->client_secret);
            $client->setRedirectUri($request->redirect_uri);

            // Exchange the authorization code for an access token
            $token = $client->fetchAccessTokenWithAuthCode($request->code);

            if (isset($token['error'])) {
                return response()->json([
                    'message' => 'Error exchanging authorization code: ' . $token['error'],
                ], 500);
            }

            return response()->json([
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_in' => $token['expires_in'],
                'token_type' => $token['token_type'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error handling YouTube auth callback: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the YouTube channel information.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getYouTubeChannelInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Initialize the YouTube API client
            $client = new Google_Client();
            $client->setAccessToken($request->access_token);
            $youtube = new Google_Service_YouTube($client);

            // Get the channel information
            $channelsResponse = $youtube->channels->listChannels('snippet,contentDetails,statistics', [
                'mine' => true
            ]);

            if (count($channelsResponse->getItems()) === 0) {
                return response()->json([
                    'message' => 'Channel not found',
                ], 404);
            }

            $channel = $channelsResponse->getItems()[0];
            $snippet = $channel->getSnippet();
            $statistics = $channel->getStatistics();

            return response()->json([
                'channel_id' => $channel->getId(),
                'title' => $snippet->getTitle(),
                'description' => $snippet->getDescription(),
                'thumbnail_url' => $snippet->getThumbnails()->getHigh()->getUrl(),
                'subscriber_count' => $statistics->getSubscriberCount(),
                'view_count' => $statistics->getViewCount(),
                'video_count' => $statistics->getVideoCount(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error getting YouTube channel info: ' . $e->getMessage(),
            ], 500);
        }
    }
}

