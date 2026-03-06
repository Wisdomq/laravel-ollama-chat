<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\Chat;

class AttachmentController extends Controller
{
    public function upload(Request $request)
    {
        $sessionId = $request->session()->get('chat_session_id');
        if (!$sessionId) {
            $sessionId = Str::uuid()->toString();
            $request->session()->put('chat_session_id', $sessionId);
        }

        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('file');
        $path = $file->store('attachments', 'public');

        // Optionally link file to chat
        Chat::create([
            'session_id' => $sessionId,
            'user_message' => '[Attachment uploaded]',
            'bot_reply' => '',
            'meta' => ['attachment' => $path],
        ]);

        return response()->json([
            'session_id' => $sessionId,
            'attachment_path' => $path,
        ]);
    }
}
