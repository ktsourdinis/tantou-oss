<?php

namespace App\Http\Controllers;

use App\Models\PostIt;
use App\Models\PostItAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    /**
     * Display or download the specified attachment.
     */
    public function show(Request $request, PostItAttachment $attachment): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();

        // 1. Verify user belongs to same tenant
        $postIt = $attachment->postIt;
        abort_unless($postIt && (int) $user->tenant_id === (int) $postIt->tenant_id, 403);

        // 2. Verify authorization to inspect the parent post-it
        abort_unless($this->canInspect($postIt, $user), 403);

        if (! Storage::disk('local')->exists($attachment->file_path)) {
            abort(404, 'File not found.');
        }

        $fullPath = Storage::disk('local')->path($attachment->file_path);

        // If download requested or not an image
        if ($request->boolean('download') || ! $attachment->isImage()) {
            return response()->download(
                $fullPath,
                $attachment->original_name,
                ['Content-Type' => $attachment->mime_type]
            );
        }

        return response()->file(
            $fullPath,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
            ]
        );
    }

    /**
     * Delete an attachment.
     */
    public function destroy(Request $request, PostItAttachment $attachment): Response
    {
        /** @var User $user */
        $user = $request->user();
        $postIt = $attachment->postIt;

        abort_unless($postIt && (int) $user->tenant_id === (int) $postIt->tenant_id, 403);

        $canDelete = $user->role === 'admin'
            || (int) $user->id === (int) $attachment->uploader_id
            || (int) $user->id === (int) $postIt->assignee_id
            || ($user->role === 'coordinator' && ((int) $user->department_id === (int) $postIt->department_id || (int) $user->department_id === (int) $postIt->assignee?->department_id));

        abort_unless($canDelete, 403);

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return response()->noContent();
    }

    /**
     * Check if a user is authorized to inspect/view the post-it.
     */
    protected function canInspect(PostIt $postIt, User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ((int) $postIt->requester_id === (int) $user->id) {
            return true;
        }

        if ((int) $postIt->assignee_id === (int) $user->id || (int) $postIt->helper_id === (int) $user->id) {
            return true;
        }

        if ($user->role === 'coordinator') {
            if ($postIt->status === 'backlog') {
                return true;
            }
            if ((int) $postIt->department_id === (int) $user->department_id || (int) $postIt->assignee?->department_id === (int) $user->department_id) {
                return true;
            }
        }

        if ($user->role === 'worker' && $postIt->status === 'backlog') {
            return true;
        }

        return false;
    }
}
