<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Chat;

class ChatController extends Controller
{
    public function initSession(Request $request)
    {
        $sessionId = Str::uuid()->toString();
        $request->session()->put('chat_session_id', $sessionId);

        return response()->json([
            'session_id' => $sessionId,
        ]);
    }

    public function getSession(Request $request)
    {
        $sessionId = $request->session()->get('chat_session_id');

        if (!$sessionId) {
            return response()->json([
                'error' => 'No active session',
            ], 400);
        }

        $messages = Chat::where('session_id', $sessionId)
            ->latest()
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'session_id' => $sessionId,
            'messages' => $messages,
        ]);
    }

    public function getSessions(Request $request)
    {
        $sessions = Chat::distinct()
            ->pluck('session_id')
            ->filter()
            ->map(function ($sessionId) {
                $lastMessage = Chat::where('session_id', $sessionId)
                    ->latest()
                    ->first();

                return [
                    'session_id' => $sessionId,
                    'last_message' => $lastMessage?->user_message,
                    'created_at' => $lastMessage?->created_at,
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'sessions' => $sessions,
        ]);
    }

    public function switchSession(Request $request)
    {
        $sessionId = $request->input('session_id');
        $request->session()->put('chat_session_id', $sessionId);

        return response()->json([
            'session_id' => $sessionId,
        ]);
    }

    public function chat(Request $request)
    {
        $sessionId = $request->session()->get('chat_session_id');

        if (!$sessionId) {
            $sessionId = Str::uuid()->toString();
            $request->session()->put('chat_session_id', $sessionId);
        }

        $prompt = $request->input('message');

        // Get last 6 messages from current session only
        $history = Chat::where('session_id', $sessionId)
            ->latest()
            ->take(6)
            ->get()
            ->reverse();

        $messages = [];

        // System instruction
        $messages[] = [
            'role' => 'system',
            'content' => 'You are a concise and friendly assistant.'
        ];

        foreach ($history as $chat) {
            $messages[] = [
                'role' => 'user',
                'content' => $chat->user_message
            ];

            $messages[] = [
                'role' => 'assistant',
                'content' => $chat->bot_reply
            ];
        }

        // Add new message
        $messages[] = [
            'role' => 'user',
            'content' => $prompt
        ];

        $response = Http::timeout(120)->post(
            'http://host.docker.internal:11434/api/chat',
            [
                'model' => 'tinyllama:latest',
                'messages' => $messages,
                'stream' => false
            ]
        );

        if (!$response->successful()) {
            return response()->json([
                'reply' => 'Error contacting Ollama'
            ], 500);
        }

        $data = $response->json();

        $reply = $data['message']['content'] ?? 'No response';

        Chat::create([
            'session_id' => $sessionId,
            'user_message' => $prompt,
            'bot_reply' => trim($reply),
        ]);

        return response()->json([
            'session_id' => $sessionId,
            'reply' => trim($reply),
        ]);
    }

    public function chatStream(Request $request)
    {
        $sessionId = $request->session()->get('chat_session_id');

        if (!$sessionId) {
            $sessionId = Str::uuid()->toString();
            $request->session()->put('chat_session_id', $sessionId);
        }

        $prompt = $request->input('message');

        // Get last 6 messages from current session only
        $history = Chat::where('session_id', $sessionId)
            ->latest()
            ->take(6)
            ->get()
            ->reverse();

        $messages = [];

        // System instruction
        $messages[] = [
            'role' => 'system',
            'content' => 'You are a concise and friendly assistant.'
        ];

        foreach ($history as $chat) {
            $messages[] = [
                'role' => 'user',
                'content' => $chat->user_message
            ];

            $messages[] = [
                'role' => 'assistant',
                'content' => $chat->bot_reply
            ];
        }

        // Add new message
        $messages[] = [
            'role' => 'user',
            'content' => $prompt
        ];

        return response()->stream(function () use ($sessionId, $prompt, $messages) {
            $fullReply = '';

            try {
                // Use cURL for proper streaming support
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'http://host.docker.internal:11434/api/chat');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'model' => 'tinyllama:latest',
                    'messages' => $messages,
                    'stream' => true,
                ]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 120);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

                // Handle streaming output on the fly
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) use (&$fullReply) {
                    $lines = explode("\n", $chunk);

                    foreach ($lines as $line) {
                        if (empty($line)) {
                            continue;
                        }

                        $data = json_decode($line, true);

                        if ($data && isset($data['message']['content'])) {
                            $content = $data['message']['content'];
                            $fullReply .= $content;

                            echo "data: " . json_encode(['chunk' => $content]) . "\n\n";
                            ob_flush();
                            flush();
                        }
                    }

                    return strlen($chunk);
                });

                $result = curl_exec($ch);

                if (curl_errno($ch)) {
                    echo "data: " . json_encode(['error' => 'cURL Error: ' . curl_error($ch)]) . "\n\n";
                    curl_close($ch);
                    return;
                }

                curl_close($ch);

                // Save complete message to database
                if (!empty($fullReply)) {
                    Chat::create([
                        'session_id' => $sessionId,
                        'user_message' => $prompt,
                        'bot_reply' => trim($fullReply),
                    ]);
                }

                // Final message indicating completion
                echo "data: " . json_encode(['done' => true]) . "\n\n";
            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => 'Stream error: ' . $e->getMessage()]) . "\n\n";
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'Connection' => 'keep-alive',
        ]);
    }
}