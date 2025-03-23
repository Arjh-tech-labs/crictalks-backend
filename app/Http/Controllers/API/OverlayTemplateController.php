<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OverlayTemplate;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OverlayTemplateController extends Controller
{
    /**
     * Display a listing of the overlay templates.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = OverlayTemplate::query();

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by creator
        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // Filter by default
        if ($request->has('is_default')) {
            $query->where('is_default', $request->is_default);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $templates = $query->with('creator')->paginate(10);

        return response()->json($templates);
    }

    /**
     * Store a newly created overlay template in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:scorecard,wicket,boundary,milestone,player,team,match,custom',
            'template_data' => 'required|json',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $streamerUserType = UserType::where('name', 'Live Streamer')->first();

        // Check if user is a streamer
        if (!$user->userTypes->contains($streamerUserType->id)) {
            return response()->json([
                'message' => 'Only streamers can create overlay templates',
            ], 403);
        }

        $template = new OverlayTemplate();
        $template->name = $request->name;
        $template->description = $request->description;
        $template->type = $request->type;
        $template->template_data = $request->template_data;
        $template->is_default = $request->is_default ?? false;
        $template->created_by = $user->id;
        $template->save();

        return response()->json([
            'message' => 'Overlay template created successfully',
            'template' => $template,
        ], 201);
    }

    /**
     * Display the specified overlay template.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $template = OverlayTemplate::with('creator')->findOrFail($id);

        return response()->json($template);
    }

    /**
     * Update the specified overlay template in storage.
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
            'type' => 'nullable|string|in:scorecard,wicket,boundary,milestone,player,team,match,custom',
            'template_data' => 'nullable|json',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $template = OverlayTemplate::findOrFail($id);
        $user = $request->user();

        // Check if user is the creator of the template
        if ($template->created_by !== $user->id) {
            return response()->json([
                'message' => 'Only the creator can update the template',
            ], 403);
        }

        if ($request->has('name')) {
            $template->name = $request->name;
        }

        if ($request->has('description')) {
            $template->description = $request->description;
        }

        if ($request->has('type')) {
            $template->type = $request->type;
        }

        if ($request->has('template_data')) {
            $template->template_data = $request->template_data;
        }

        if ($request->has('is_default')) {
            $template->is_default = $request->is_default;
        }

        $template->save();

        return response()->json([
            'message' => 'Overlay template updated successfully',
            'template' => $template,
        ]);
    }

    /**
     * Remove the specified overlay template from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $template = OverlayTemplate::findOrFail($id);
        $user = $request->user();

        // Check if user is the creator of the template
        if ($template->created_by !== $user->id) {
            return response()->json([
                'message' => 'Only the creator can delete the template',
            ], 403);
        }

        $template->delete();

        return response()->json([
            'message' => 'Overlay template deleted successfully',
        ]);
    }

    /**
     * Get overlay templates by type.
     *
     * @param  string  $type
     * @return \Illuminate\Http\Response
     */
    public function getByType($type)
    {
        $templates = OverlayTemplate::where('type', $type)
            ->with('creator')
            ->get();

        return response()->json($templates);
    }

    /**
     * Get default overlay template by type.
     *
     * @param  string  $type
     * @return \Illuminate\Http\Response
     */
    public function getDefaultByType($type)
    {
        $template = OverlayTemplate::where('type', $type)
            ->where('is_default', true)
            ->with('creator')
            ->first();

        if (!$template) {
            return response()->json([
                'message' => 'Default template not found for this type',
            ], 404);
        }

        return response()->json($template);
    }
}

