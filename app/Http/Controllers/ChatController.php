<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Services\AgentGeneralService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;

class ChatController extends Controller
{
    // ── Intent classification ─────────────────────────────────────────────────

    private array $taskPatterns = [
        '/\b(what|current|tell me).*(time|date|day|today|now)\b/i',
        '/\bwhat time\b/i',
        '/\bwhat.*date\b/i',
        '/\b(calculate|compute|math|add|subtract|multiply|divide|sum|total)\b/i',
        '/\d+\s*[\+\-\*\/]\s*\d+/',
        '/\b(find|search|look up|get me|show me|list|fetch)\b/i',
        '/\b(hotels|restaurants|flights|weather|news|price|stock)\b/i',
        '/\b(read|write|open|save|create|delete|count lines|file)\b/i',
        '/\b(generate|make|build|create a|produce)\b/i',
        '/\b(trip|itinerary|plan|travel|visit|tour)\b/i',
        '/\b(workout|exercise|fitness|routine|training)\b/i',
        '/\b(recipe|meal|food|diet|nutrition)\b/i',
        '/\b(convert|translate|calculate|compute)\b/i',
        '/\b(guide|tutorial|learn|beginner|introduction|how.?to)\b/i',
    ];

    private array $conversationalPatterns = [
        '/^(hi|hello|hey|howdy|greetings|good morning|good evening|good afternoon)/i',
        '/\b(how are you|how\'s it going|what\'s up|how do you do)\b/i',
        '/\b(thank you|thanks|cheers|great|awesome|cool|nice)\b/i',
        '/\b(my name is|i am|i\'m|tell me about yourself|who are you)\b/i',
        '/\b(i feel|i think|i believe|i want to|i\'d like to talk)\b/i',
        '/\b(hungry|tired|bored|happy|sad|angry|excited)\b/i',
        '/^(yes|no|ok|okay|sure|maybe|perhaps|alright|fine)[\.\!]*$/i',
        '/\b(explain|what is|what are|describe|define|meaning of)\b/i',
        '/\b(joke|funny|story|poem|quote)\b/i',
    ];

    private function classifyIntent(string $message): string
    {
        $message = trim($message);

        if (str_word_count($message) <= 2) {
            return 'conversational';
        }

        foreach ($this->conversationalPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return 'conversational';
            }
        }

        foreach ($this->taskPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return 'task';
            }
        }

        return 'conversational';
    }

    // ── Tool matcher ──────────────────────────────────────────────────────────

    /**
     * Stop words — excluded from keyword matching.
     * Action verbs (generate, find, calculate) are kept because they carry signal.
     */
    private array $stopWords = [
        'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be', 'been',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
        'should', 'may', 'might', 'me', 'my', 'your', 'it', 'its', 'this',
        'that', 'these', 'those', 'i', 'you', 'he', 'she', 'we', 'they',
        'what', 'which', 'who', 'how', 'when', 'where', 'why', 'can', 'please',
        'some', 'any', 'all', 'just', 'about', 'up', 'out', 'so', 'if', 'as',
    ];

    /**
     * Extract meaningful keywords from a string.
     * Minimum word length: 2 chars (keeps "pi", "ai", etc.)
     */
    private function extractKeywords(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(
            $words,
            fn ($w) => strlen($w) > 1 && ! in_array($w, $this->stopWords)
        )));
    }

    /**
     * Query-coverage score:
     *   score = |query_words ∩ tool_words| / |query_words|
     *
     * Measures "how much of the user's query does this tool cover?"
     * Rewards tools that match the query well without penalising tools
     * for having rich descriptions. Partial matches (stemming) count 0.7.
     */
    private function scoreToolMatch(string $query, string $toolDescription): float
    {
        $queryKeywords = $this->extractKeywords($query);
        $descKeywords = $this->extractKeywords($toolDescription);

        if (empty($queryKeywords) || empty($descKeywords)) {
            return 0.0;
        }

        $descSet = array_flip($descKeywords); // O(1) lookup
        $matches = 0.0;

        foreach ($queryKeywords as $qw) {
            if (isset($descSet[$qw])) {
                // Exact match
                $matches += 1.0;
            } else {
                // Partial match — one contains the other (e.g. "workouts" ↔ "workout")
                foreach ($descKeywords as $dw) {
                    if (strlen($qw) >= 4 && (str_contains($dw, $qw) || str_contains($qw, $dw))) {
                        $matches += 0.7;
                        break;
                    }
                }
            }
        }

        return $matches / count($queryKeywords);
    }

    /**
     * Find the best matching Laravel Tool for the user's message.
     *
     * Two-gate selection:
     *   Gate 1 — score >= $matchThreshold (0.35): basic relevance
     *   Gate 2 — winner leads 2nd place by >= $minMargin (0.15): confidence
     *
     * Both gates must pass. If the best tool barely beats the second-best,
     * the match is considered ambiguous and null is returned — the request
     * falls through to AgentGeneral which uses FAISS for better precision.
     *
     * Why two gates?
     *   A single threshold lets a tool win with 0.46 while a more appropriate
     *   tool scores 0.45 — a meaningless 0.01 gap. The margin gate ensures the
     *   winner is genuinely the best fit, not just the least-bad option.
     *
     * Returns [Tool instance, score, class name] or null.
     */
    private function findMatchingTool(string $message): ?array
    {
        $toolsPath = app_path('Ai/Tools');
        if (! is_dir($toolsPath)) {
            return null;
        }

        $matchThreshold = 0.35;
        $minMargin = 0.15;

        $scores = [];
        $tools = [];

        foreach (glob($toolsPath.'/*.php') as $file) {
            $className = 'App\\Ai\\Tools\\'.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($className)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($className);
                if ($reflection->isAbstract() || ! $reflection->implementsInterface(Tool::class)) {
                    continue;
                }

                $instance = new $className;
                $description = (string) $instance->description();
                $score = $this->scoreToolMatch($message, $description);

                // Log matched words for debugging
                $queryWords = $this->extractKeywords($message);
                $descWords = $this->extractKeywords($description);
                $matched = implode(', ', array_intersect($queryWords, $descWords)) ?: '—';

                \Log::debug(sprintf(
                    '[ToolMatcher] %-55s → score: %.2f | matched: %s',
                    $className,
                    $score,
                    $matched
                ));

                $scores[$className] = $score;
                $tools[$className] = $instance;

            } catch (\Throwable $e) {
                \Log::warning("[ToolMatcher] Could not load {$className}: ".$e->getMessage());
            }
        }

        if (empty($scores)) {
            return null;
        }

        arsort($scores);
        $ranked = array_keys($scores);
        $bestClass = $ranked[0];
        $bestScore = $scores[$bestClass];
        $secondScore = isset($ranked[1]) ? $scores[$ranked[1]] : 0.0;
        $margin = $bestScore - $secondScore;

        // Gate 1: minimum relevance
        if ($bestScore < $matchThreshold) {
            \Log::info(sprintf(
                '[ToolMatcher] No match — best score %.2f below threshold %.2f.',
                $bestScore, $matchThreshold
            ));

            return null;
        }

        // Gate 2: must clearly beat second place
        if ($margin < $minMargin) {
            \Log::info(sprintf(
                '[ToolMatcher] No match — %s (%.2f) leads by %.2f, below margin %.2f. Ambiguous → AgentGeneral.',
                class_basename($bestClass), $bestScore, $margin, $minMargin
            ));

            return null;
        }

        \Log::info(sprintf(
            '[ToolMatcher] Match: %s (score: %.2f, margin: %.2f)',
            $bestClass, $bestScore, $margin
        ));

        return [$tools[$bestClass], $bestScore, $bestClass];
    }

    /**
     * Execute a matched Laravel Tool.
     *
     * All auto-generated tools delegate to AgentGeneralService internally:
     *   - needs_input tools:     AgentGeneralService::run($request['input'])
     *   - self-contained tools:  AgentGeneralService::run('SkillName')
     *
     * We always pass ['input' => $message] so needs_input tools have the
     * user message available. Self-contained tools ignore the request entirely.
     *
     * Errors are caught and logged; empty string return triggers fallback
     * to AgentGeneral with the original message in the caller.
     */
    private function executeTool(Tool $tool, string $message): string
    {
        try {
            $request = new \Laravel\Ai\Tools\Request(['input' => $message]);
            $result = $tool->handle($request);

            if (is_array($result)) {
                return $result['result'] ?? json_encode($result);
            }

            $str = trim((string) $result);

            if (empty($str)) {
                \Log::warning('[ToolMatcher] Tool '.get_class($tool).' returned empty string.');
            }

            return $str;

        } catch (\Throwable $e) {
            \Log::error('[ToolMatcher] Tool '.get_class($tool).' threw: '.$e->getMessage());

            return '';
        }
    }

    // ── Task routing ──────────────────────────────────────────────────────────

    /**
     * Route a task message:
     *   1. Try Laravel Tools (fast, local, no HTTP)
     *   2. Fall back to AgentGeneral (FAISS match or skill generation)
     *
     * Laravel Tools are always preferred. AgentGeneral is last resort.
     */
    private function handleTask(string $message): array
    {
        // ── Step 1: Laravel Tools ────────────────────────────────────────────
        $match = $this->findMatchingTool($message);

        if ($match) {
            [$toolInstance, $score, $className] = $match;
            \Log::info("[ChatController] Laravel Tool matched: {$className} (score: {$score})");

            $result = $this->executeTool($toolInstance, $message);

            if (! empty(trim($result))) {
                return [
                    'source' => 'laravel_tool',
                    'result' => $result,
                    'skill_used' => $className,
                    'new_tool' => null,
                ];
            }

            // Tool returned empty — fall through to AgentGeneral
            \Log::warning("[ChatController] Tool {$className} returned empty result — falling back to AgentGeneral. Check that AgentGeneral is running and asJson() is in AgentGeneralService.");
        }

        // ── Step 2: AgentGeneral ─────────────────────────────────────────────
        $agentGeneral = app(AgentGeneralService::class);

        if (! $agentGeneral->isReachable()) {
            \Log::warning('[ChatController] AgentGeneral not reachable — falling back to LLM.');

            return ['source' => 'llm_fallback', 'result' => null];
        }

        $response = $agentGeneral->run($message);

        if (! empty($response['new_tool_generated']) && ! empty($response['new_tool_class_name'])) {
            \Log::info("[ChatController] New Laravel Tool generated: {$response['new_tool_class_name']}");
        }

        return [
            'source' => 'agentgeneral',
            'result' => $response['result'] ?? null,
            'skill_used' => $response['skill_used'] ?? null,
            'new_tool' => $response['new_tool_class_name'] ?? null,
        ];
    }

    // ── Session management ────────────────────────────────────────────────────

    public function initSession(Request $request)
    {
        $sessionId = Str::uuid()->toString();
        $request->session()->put('chat_session_id', $sessionId);
        \Log::info('New session initialized: '.$sessionId);

        return response()->json(['session_id' => $sessionId]);
    }

    public function getSession(Request $request)
    {
        $sessionId = $request->session()->get('chat_session_id');
        if (! $sessionId) {
            return response()->json(['error' => 'No active session'], 400);
        }
        $messages = Chat::where('session_id', $sessionId)
            ->latest()->get()->reverse()->values();

        return response()->json(['session_id' => $sessionId, 'messages' => $messages]);
    }

    public function getSessions(Request $request)
    {
        $sessions = Chat::distinct()->pluck('session_id')->filter()
            ->map(function ($sessionId) {
                $lastMessage = Chat::where('session_id', $sessionId)->latest()->first();

                return [
                    'session_id' => $sessionId,
                    'last_message' => $lastMessage?->user_message,
                    'created_at' => $lastMessage?->created_at,
                ];
            })
            ->sortByDesc('created_at')->values();

        return response()->json(['sessions' => $sessions]);
    }

    public function switchSession(Request $request)
    {
        $sessionId = $request->input('session_id');
        $request->session()->put('chat_session_id', $sessionId);

        return response()->json(['session_id' => $sessionId]);
    }

    public function deleteSession(Request $request)
    {
        $sessionId = $request->input('session_id');
        if (! $sessionId) {
            return response()->json(['error' => 'No session ID provided'], 400);
        }
        Chat::where('session_id', $sessionId)->delete();
        if ($request->session()->get('chat_session_id') === $sessionId) {
            $request->session()->forget('chat_session_id');
        }

        return response()->json(['message' => 'Session deleted successfully']);
    }

    // ── Main chat endpoint ────────────────────────────────────────────────────

    public function chat(Request $request)
    {
        $sessionId = $request->session()->get('chat_session_id');
        if (! $sessionId) {
            $sessionId = Str::uuid()->toString();
            $request->session()->put('chat_session_id', $sessionId);
        }

        $userMessage = $request->input('message');
        $meta = [];

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('attachments', 'public');
            $meta['attachment'] = $path;
        }

        \Log::info("[ChatController] Session: {$sessionId} | Message: {$userMessage}");

        $intent = $this->classifyIntent($userMessage);
        $skillUsed = null;
        $newTool = null;
        $source = 'llm';
        $reply = '';

        \Log::info("[ChatController] Intent: {$intent}");

        try {
            if ($intent === 'task') {
                $taskResult = $this->handleTask($userMessage);
                $source = $taskResult['source'];
                $skillUsed = $taskResult['skill_used'] ?? null;
                $newTool = $taskResult['new_tool'] ?? null;

                if ($source !== 'llm_fallback' && ! empty($taskResult['result'])) {
                    $agent = new \App\Ai\Agents\ChatbotAgent($sessionId);
                    $wrappingPrompt = "The user asked: \"{$userMessage}\"\n\nThe result is:\n{$taskResult['result']}\n\nPresent this result to the user in a friendly, natural way. Do not add information not already in the result.";
                    $reply = trim((string) $agent->prompt($wrappingPrompt, provider: 'ollama', model: 'mistral:7b'));
                } else {
                    $agent = new \App\Ai\Agents\ChatbotAgent($sessionId);
                    $reply = trim((string) $agent->prompt($userMessage, provider: 'ollama', model: 'mistral:7b'));
                }

            } else {
                $agent = new \App\Ai\Agents\ChatbotAgent($sessionId);
                $reply = trim((string) $agent->prompt($userMessage, provider: 'ollama', model: 'mistral:7b'));
            }

            Chat::create([
                'session_id' => $sessionId,
                'user_message' => $userMessage,
                'bot_reply' => $reply,
                'meta' => array_merge($meta, array_filter([
                    'intent' => $intent,
                    'source' => $source,
                    'skill_used' => $skillUsed,
                    'new_tool' => $newTool,
                ])),
            ]);

            return response()->json([
                'session_id' => $sessionId,
                'reply' => $reply,
                'intent' => $intent,
                'source' => $source,
                'skill_used' => $skillUsed,
                'new_tool' => $newTool,
            ]);

        } catch (\Exception $e) {
            \Log::error('[ChatController] Error: '.$e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json(['reply' => 'Error: '.$e->getMessage()], 500);
        }
    }

    // ── Streaming endpoint ────────────────────────────────────────────────────

    public function chatStream(Request $request)
    {
        $sessionId = $request->session()->get('chat_session_id');
        if (! $sessionId) {
            $sessionId = Str::uuid()->toString();
            $request->session()->put('chat_session_id', $sessionId);
        }

        $userMessage = $request->input('message');
        $attachmentPath = $request->input('attachment_path');
        \Log::info("[ChatController STREAM] Session: {$sessionId} | Message: {$userMessage}");

        $intent = $this->classifyIntent($userMessage);
        $skillUsed = null;
        $newTool = null;
        $source = 'llm';

        // Build base prompt — append attachment context if a file was uploaded
        $attachmentContext = '';
        if ($attachmentPath) {
            $fileUrl = asset('storage/'.$attachmentPath);
            $attachmentContext = "\n\n[The user has attached a file for context: {$fileUrl}]";
        }
        $streamPrompt = $userMessage.$attachmentContext;

        if ($intent === 'task') {
            // ── Step 1: Laravel Tools ────────────────────────────────────────
            $match = $this->findMatchingTool($userMessage);

            if ($match) {
                [$toolInstance, $score, $className] = $match;
                \Log::info("[ChatController STREAM] Laravel Tool matched: {$className}");

                $result = $this->executeTool($toolInstance, $userMessage);

                if (! empty(trim($result))) {
                    $source = 'laravel_tool';
                    $skillUsed = $className;
                    $streamPrompt = "The user asked: \"{$userMessage}\"\n\nThe result is:\n{$result}\n\nPresent this result naturally. Do not add extra information.";
                } else {
                    \Log::warning("[ChatController STREAM] Tool {$className} returned empty — falling through to AgentGeneral.");
                    $match = null; // Tool empty — fall through
                }
            }

            // ── Step 2: AgentGeneral ─────────────────────────────────────────
            if (! $match) {
                $agentGeneral = app(AgentGeneralService::class);
                if ($agentGeneral->isReachable()) {
                    // ── Idea 1: Stream "working" status immediately ────────────
                    // Fire on first pending poll so the user sees a response
                    // within 2s instead of staring at a blank screen for 30-90s.
                    $workingChunkSent = false;
                    $agentGeneral->onBusy(function () use (&$workingChunkSent) {
                        if (! $workingChunkSent) {
                            $workingChunkSent = true;
                            echo 'data: '.json_encode(['chunk' => 'Let me work on that for you...'])."\n\n";
                            ob_flush();
                            flush();
                        }
                    });
                    $taskResult = $agentGeneral->run($userMessage);
                    $source = 'agentgeneral';
                    $skillUsed = $taskResult['skill_used'] ?? null;
                    $newTool = $taskResult['new_tool_class_name'] ?? null;
                    $agentResult = $taskResult['result'] ?? '';
                    $streamPrompt = "The user asked: \"{$userMessage}\"\n\nThe result is:\n{$agentResult}\n\nPresent this result naturally. Do not add extra information.";
                }
                // If not reachable, $streamPrompt stays as $userMessage → LLM answers directly
            }
        }

        try {
            $agent = new \App\Ai\Agents\ChatbotAgent($sessionId);

            return response()->stream(
                function () use ($agent, $streamPrompt, $userMessage, $sessionId, $intent, $source, $skillUsed, $newTool) {
                    $fullResponse = '';

                    // Send metadata first so the client knows what handled the request
                    echo 'data: '.json_encode([
                        'meta' => [
                            'intent' => $intent,
                            'source' => $source,
                            'skill_used' => $skillUsed,
                            'new_tool' => $newTool,
                        ],
                    ])."\n\n";
                    ob_flush();
                    flush();

                    try {
                        foreach ($agent->stream($streamPrompt, provider: 'ollama', model: 'mistral:7b') as $event) {
                            if ($event instanceof \Laravel\Ai\Streaming\Events\TextDelta) {
                                $chunk = $event->delta;
                                $fullResponse .= $chunk;
                                echo 'data: '.json_encode(['chunk' => $chunk])."\n\n";
                                ob_flush();
                                flush();
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('[ChatController STREAM] Inner: '.$e->getMessage());
                        echo 'data: '.json_encode(['error' => $e->getMessage()])."\n\n";
                        ob_flush();
                        flush();
                    }

                    echo 'data: '.json_encode(['done' => true])."\n\n";
                    ob_flush();
                    flush();

                    Chat::create([
                        'session_id' => $sessionId,
                        'user_message' => $userMessage,
                        'bot_reply' => trim($fullResponse),
                        'meta' => array_filter([
                            'intent' => $intent,
                            'source' => $source,
                            'skill_used' => $skillUsed,
                            'new_tool' => $newTool,
                            'attachment' => $attachmentPath,
                        ]),
                    ]);
                },
                200,
                [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                    'Connection' => 'keep-alive',
                ]
            );

        } catch (\Exception $e) {
            \Log::error('[ChatController STREAM] Outer: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
