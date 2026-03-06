<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Chat;
use Laravel\Ai\Ai;
use Laravel\Ai\Messages\Message;

class ChatController extends Controller
{
    public function initSession(Request $request)
    {
        $sessionId = Str::uuid()->toString();
        $request->session()->put('chat_session_id', $sessionId);

        \Log::info('New session initialized: ' . $sessionId);

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

    public function deleteSession(Request $request)
    {
        $sessionId = $request->input('session_id');

        if (!$sessionId) {
            return response()->json([
                'error' => 'No session ID provided',
            ], 400);
        }

        // Delete all messages for this session
        Chat::where('session_id', $sessionId)->delete();

        // Clear from session if it was the current one
        if ($request->session()->get('chat_session_id') === $sessionId) {
            $request->session()->forget('chat_session_id');
        }

        return response()->json([
            'message' => 'Session deleted successfully',
        ]);
    }

    public function chat(Request $request)
    {
        $sessionId = $request->session()->get('chat_session_id');

        if (!$sessionId) {
            $sessionId = Str::uuid()->toString();
            $request->session()->put('chat_session_id', $sessionId);
        }

        \Log::info('Chat request for session: ' . $sessionId);
        $prompt = $request->input('message');
        $meta = [];
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('attachments', 'public');
            $meta['attachment'] = $path;
        }

        try {
            $agent = new \App\Ai\Agents\ChatbotAgent($sessionId);

            \Log::info('Using Mistral provider with open-mistral-7b model for session: ' . $sessionId);
            \Log::info('User prompt: ' . $prompt);
            $response = $agent->prompt($prompt, provider: 'mistral', model: 'open-mistral-7b');

            \Log::info('AI Response object: ' . get_class($response));
            \Log::info('AI Response type: ' . gettype($response));
            
            // Handle structured output (array/object)
            if (is_array($response) || is_object($response)) {
                $structured = (array) $response;
                \Log::info('Structured response: ' . json_encode($structured));
                
                $reply = $structured['reply'] ?? '';
                $confidence = $structured['confidence'] ?? 0;
                $thinking = $structured['thinking'] ?? '';
                
                \Log::info('Extracted reply: ' . $reply);
                \Log::info('Confidence: ' . $confidence);
                \Log::info('Thinking: ' . $thinking);
            } else {
                // Fallback for string responses
                $reply = (string) $response;
                $confidence = 1.0;
                $thinking = 'Direct response';
            }

            // Store in Chat model
            Chat::create([
                'session_id' => $sessionId,
                'user_message' => $prompt,
                'bot_reply' => trim($reply),
                'meta' => $meta,
            ]);

            return response()->json([
                'session_id' => $sessionId,
                'reply' => trim($reply),
                'confidence' => $confidence,
                'thinking' => $thinking,
            ]);
        } catch (\Exception $e) {
            \Log::error('AI SDK Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'reply' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function chatStream(Request $request)
    {
        $sessionId = $request->session()->get('chat_session_id');

        if (!$sessionId) {
            $sessionId = Str::uuid()->toString();
            $request->session()->put('chat_session_id', $sessionId);
        }

        $prompt = $request->input('message');

        \Log::info('STREAM Chat request for session: ' . $sessionId);
        \Log::info('STREAM User prompt: ' . $prompt);

        try {
            $agent = new \App\Ai\Agents\ChatbotAgent($sessionId);

            \Log::info('STREAM Using Mistral provider with open-mistral-7b model');

            return response()->stream(function () use ($agent, $prompt, $sessionId) {
                \Log::info('STREAM Starting stream processing');
                $fullResponse = '';

                try {
                    foreach ($agent->stream($prompt, provider: 'mistral', model: 'open-mistral-7b') as $event) {
                        \Log::info('STREAM Event received: ' . get_class($event));
                        // Handle different types of stream events
                        if ($event instanceof \Laravel\Ai\Streaming\Events\TextDelta) {
                            $chunk = $event->delta;
                            \Log::info('STREAM Chunk: ' . $chunk);
                            $fullResponse .= $chunk;

                            // Send chunk as SSE
                            echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
                            ob_flush();
                            flush();
                        } else {
                            \Log::info('STREAM Non-text event: ' . get_class($event));
                        }
                    }

                    \Log::info('STREAM Stream completed, full response: ' . $fullResponse);
                } catch (\Exception $e) {
                    \Log::error('STREAM Inner exception: ' . $e->getMessage());
                    echo "data: " . json_encode(['error' => 'Stream failed: ' . $e->getMessage()]) . "\n\n";
                    ob_flush();
                    flush();
                }

                // Send completion signal
                echo "data: " . json_encode(['done' => true]) . "\n\n";
                ob_flush();
                flush();

                // Save to database after streaming completes
                Chat::create([
                    'session_id' => $sessionId,
                    'user_message' => $prompt,
                    'bot_reply' => trim($fullResponse),
                ]);
                \Log::info('STREAM Saved to database');
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
            ]);
        } catch (\Exception $e) {
            \Log::error('STREAM AI SDK Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Stream error: ' . $e->getMessage()
            ], 500);
        }
    }
}