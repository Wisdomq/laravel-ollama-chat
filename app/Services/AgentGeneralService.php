<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AgentGeneralService
 *
 * Communicates with the AgentGeneral FastAPI server running on the host machine.
 * The server is at host.docker.internal:8765 from inside the Docker container,
 * which maps to localhost:8765 on the Windows/WSL host.
 *
 * Flow for all tasks:
 *   1. POST /run/async  → returns job_id immediately (avoids cURL timeout 28)
 *   2. Poll GET /run/status/{job_id} every $pollIntervalMs milliseconds
 *   3. Return result when status == "done" or "error"
 *   4. Give up after $maxWaitSeconds and return a timeout result
 */
class AgentGeneralService
{
    private string $baseUrl;

    /**
     * How long to wait for a job to complete before giving up (seconds).
     * Complex multi-step tasks with skill generation can take 90–120s.
     */
    private int $maxWaitSeconds;

    /**
     * How long to wait between each status poll (milliseconds).
     * 2000ms = poll every 2 seconds.
     */
    private int $pollIntervalMs;

    /**
     * Timeout for individual HTTP requests to AgentGeneral (seconds).
     * Keep short — each request should respond instantly.
     */
    private int $requestTimeout;

    /**
     * Optional callback fired on the first pending poll response.
     * Use this to push an SSE "working" chunk to the client immediately
     * rather than making the user stare at a blank screen.
     * Set via: $service->onBusy(fn() => $this->pushSseChunk('working...'));
     */
    private $onBusyCallback = null;

    public function onBusy(callable $callback): static
    {
        $this->onBusyCallback = $callback;
        return $this;
    }

    public function __construct()
    {
        $this->baseUrl        = config('agentgeneral.url', 'http://host.docker.internal:8765');
        $this->maxWaitSeconds = config('agentgeneral.max_wait', 180);
        $this->pollIntervalMs = config('agentgeneral.poll_interval_ms', 2000);
        $this->requestTimeout = config('agentgeneral.request_timeout', 10);
    }

    /**
     * Check if AgentGeneral server is reachable.
     */
    public function isReachable(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Run a task through AgentGeneral.
     *
     * Uses the async flow:
     *   POST /run/async → job_id → poll /run/status/{job_id} → result
     *
     * Returns an array with:
     *   - result: string            — the agent's answer
     *   - skill_used: string|null   — which skill was matched
     *   - new_tool_generated: bool  — whether a new Laravel Tool was created
     *   - new_tool_class_name: string|null — the PHP class name if generated
     *   - error: string|null        — error message if something went wrong
     */
    public function run(string $task): array
    {
        try {
            Log::info("[AgentGeneral] Sending task: {$task}");

            // ── Step 1: Submit the task, get a job_id immediately ─────────
            // ->asJson() is required — FastAPI expects Content-Type: application/json.
            // Without it, Laravel sends application/x-www-form-urlencoded and FastAPI
            // returns 400 Bad Request (Pydantic cannot parse the body).
            $submitResponse = Http::timeout($this->requestTimeout)
                ->asJson()
                ->post("{$this->baseUrl}/run/async", [
                    'task' => $task,
                ]);

            if ($submitResponse->failed()) {
                Log::error("[AgentGeneral] Failed to submit task. HTTP: " . $submitResponse->status());
                return $this->errorResult("AgentGeneral returned HTTP {$submitResponse->status()} on submit.");
            }

            $submitData = $submitResponse->json();
            $jobId      = $submitData['job_id'] ?? null;

            if (!$jobId) {
                Log::error("[AgentGeneral] No job_id in async response.", $submitData);
                return $this->errorResult('AgentGeneral did not return a job ID.');
            }

            Log::info("[AgentGeneral] Job submitted. job_id: {$jobId}");

            // ── Step 2: Poll until done, error, or timeout ────────────────
            $pollUrl     = "{$this->baseUrl}/run/status/{$jobId}";
            $deadline    = microtime(true) + $this->maxWaitSeconds;
            $attemptNum  = 0;

            while (microtime(true) < $deadline) {
                $attemptNum++;
                usleep($this->pollIntervalMs * 1000); // convert ms → µs

                try {
                    $statusResponse = Http::timeout($this->requestTimeout)
                        ->get($pollUrl);
                } catch (\Exception $e) {
                    // Transient network hiccup — log and keep polling
                    Log::warning("[AgentGeneral] Poll attempt {$attemptNum} failed: " . $e->getMessage());
                    continue;
                }

                if ($statusResponse->failed()) {
                    // 404 means job_id expired or was never created
                    if ($statusResponse->status() === 404) {
                        Log::error("[AgentGeneral] Job {$jobId} not found on server.");
                        return $this->errorResult('AgentGeneral job expired or was not found.');
                    }
                    // Other HTTP errors — keep retrying
                    Log::warning("[AgentGeneral] Poll returned HTTP {$statusResponse->status()} — retrying.");
                    continue;
                }

                $statusData = $statusResponse->json();
                $status     = $statusData['status'] ?? 'pending';

                Log::debug("[AgentGeneral] Poll #{$attemptNum} — status: {$status}");

                if ($status === 'done' || $status === 'error') {
                    $result = $statusData['result'] ?? [];

                    Log::info("[AgentGeneral] Job {$jobId} completed with status: {$status}", $result);

                    return [
                        'result'              => $result['result']              ?? 'No result returned.',
                        'skill_used'          => $result['skill_used']          ?? null,
                        'new_tool_generated'  => $result['new_tool_generated']  ?? false,
                        'new_tool_class_name' => $result['new_tool_class_name'] ?? null,
                        'error'               => $result['error']               ?? null,
                    ];
                }

                // status === 'pending' — notify caller once so UI can show busy state
                if (!isset($notifiedBusy)) {
                    $notifiedBusy = true;
                    if ($this->onBusyCallback !== null) {
                        ($this->onBusyCallback)();
                    }
                }
            }

            // ── Timed out waiting for result ──────────────────────────────
            Log::error("[AgentGeneral] Job {$jobId} timed out after {$this->maxWaitSeconds}s ({$attemptNum} polls).");
            return $this->errorResult(
                "AgentGeneral did not complete within {$this->maxWaitSeconds} seconds."
            );

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("[AgentGeneral] Connection failed: " . $e->getMessage());
            return $this->errorResult(
                'Could not connect to AgentGeneral. Is the server running?',
                $e->getMessage()
            );
        } catch (\Exception $e) {
            Log::error("[AgentGeneral] Unexpected error: " . $e->getMessage());
            return $this->errorResult('An unexpected error occurred.', $e->getMessage());
        }
    }

    /**
     * Helper — build a consistent error result array.
     */
    private function errorResult(string $message, ?string $detail = null): array
    {
        return [
            'result'              => $message,
            'skill_used'          => null,
            'new_tool_generated'  => false,
            'new_tool_class_name' => null,
            'error'               => $detail ?? $message,
        ];
    }
}