<?php

namespace App\Services;

use App\Models\WorkflowJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ComfyUIService
 *
 * Handles all communication with the ComfyUI server.
 *
 * ComfyUI REST API:
 *   POST /prompt          — submit a workflow JSON, returns a prompt_id
 *   GET  /queue           — see what's currently queued/running
 *   GET  /history/{id}    — check job status and get output file paths
 *   GET  /view?...        — download an output file (image/video/audio)
 *   GET  /system_stats    — health check
 */
class ComfyUIService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('comfyui.base_url', 'http://192.168.1.50:8188'), '/');
    }

    // -------------------------------------------------------------------------
    // Submitting a workflow
    // -------------------------------------------------------------------------

    /**
     * Submit a workflow JSON string to ComfyUI.
     *
     * ComfyUI expects: { "prompt": { ...node graph... }, "client_id": "..." }
     * It returns:      { "prompt_id": "uuid", "number": 1, "node_errors": {} }
     *
     * IMPORTANT: "Server got itself in trouble" (500) almost always means
     * the prompt field is null or malformed. We validate before sending.
     *
     * @param  string  $workflowJson  Complete workflow JSON string (prompt injected)
     * @return string  The prompt_id returned by ComfyUI
     * @throws \Exception
     */
    public function submitWorkflow(string $workflowJson): string
    {
        $clientId = Str::uuid()->toString();

        // ── Validate JSON before sending ──────────────────────────────────────
        // This is the #1 cause of ComfyUI 500 errors. If workflow_json stored
        // in the DB is double-encoded or truncated, json_decode returns null
        // and ComfyUI receives {"prompt": null} which crashes it.
        $promptData = json_decode($workflowJson, true);

        if ($promptData === null) {
            $jsonError = json_last_error_msg();
            Log::error('ComfyUI: Workflow JSON is invalid, refusing to submit', [
                'json_error' => $jsonError,
                'json_preview' => substr($workflowJson, 0, 200),
            ]);
            throw new \Exception("Workflow JSON is malformed and cannot be submitted: {$jsonError}");
        }

        if (!is_array($promptData) || empty($promptData)) {
            Log::error('ComfyUI: Workflow JSON decoded to empty/non-array', [
                'type' => gettype($promptData),
            ]);
            throw new \Exception("Workflow JSON decoded to an empty or non-array value. Check the workflow template in the database.");
        }

        // ── Check for node_errors before even submitting ──────────────────────
        // ComfyUI will also validate; but we can give a better error message
        $payload = [
            'prompt'    => $promptData,
            'client_id' => $clientId,
        ];

        Log::info('ComfyUI: Submitting workflow', [
            'client_id'  => $clientId,
            'node_count' => count($promptData),
        ]);

        $response = Http::timeout(30)
            ->post("{$this->baseUrl}/prompt", $payload);

        if ($response->failed()) {
            $body   = $response->body();
            $status = $response->status();

            // Try to extract a useful message from ComfyUI's error body
            $detail = $this->parseComfyError($body);

            Log::error('ComfyUI: Submission failed', [
                'status'  => $status,
                'body'    => $body,
                'detail'  => $detail,
            ]);

            throw new \Exception("ComfyUI rejected the workflow (HTTP {$status}): {$detail}");
        }

        $data = $response->json();

        // node_errors means the graph has missing nodes (custom nodes not installed, etc.)
        if (!empty($data['node_errors'])) {
            $nodeErrors = json_encode($data['node_errors']);
            Log::error('ComfyUI: Workflow has node errors', ['node_errors' => $data['node_errors']]);
            throw new \Exception("Workflow contains node errors — some nodes may not be installed in ComfyUI: {$nodeErrors}");
        }

        if (empty($data['prompt_id'])) {
            throw new \Exception('ComfyUI did not return a prompt_id. Response: ' . json_encode($data));
        }

        Log::info('ComfyUI: Workflow accepted', ['prompt_id' => $data['prompt_id']]);

        return $data['prompt_id'];
    }

    // -------------------------------------------------------------------------
    // Polling for job completion
    // -------------------------------------------------------------------------

    /**
     * Check the status of a submitted job.
     *
     * Strategy:
     *   1. Check /queue — if the job is still in pending/running, return not done.
     *   2. Check /history/{id} — job appears here once finished (success or error).
     *
     * @return array ['done' => bool, 'success' => bool, 'files' => array, 'error' => string|null]
     */
    public function checkJobStatus(string $promptId): array
    {
        // First, check if still in queue (fast check)
        try {
            $queueResponse = Http::timeout(10)->get("{$this->baseUrl}/queue");
            if ($queueResponse->ok()) {
                $queue = $queueResponse->json();
                $running = array_column($queue['queue_running'] ?? [], 1);
                $pending = array_column($queue['queue_pending'] ?? [], 1);

                if (in_array($promptId, $running) || in_array($promptId, $pending)) {
                    return ['done' => false, 'success' => false, 'files' => [], 'error' => null];
                }
            }
        } catch (\Exception $e) {
            // Queue check failed — fall through to history check
            Log::warning('ComfyUI: Queue check failed, falling back to history', ['error' => $e->getMessage()]);
        }

        // Check history
        $response = Http::timeout(10)->get("{$this->baseUrl}/history/{$promptId}");

        if ($response->failed()) {
            Log::warning('ComfyUI: History check failed', ['prompt_id' => $promptId]);
            return ['done' => false, 'success' => false, 'files' => [], 'error' => null];
        }

        $history = $response->json();

        if (empty($history) || !isset($history[$promptId])) {
            return ['done' => false, 'success' => false, 'files' => [], 'error' => null];
        }

        $jobData = $history[$promptId];
        $status  = $jobData['status'] ?? [];

        if (isset($status['status_str']) && $status['status_str'] === 'error') {
            $messages = $status['messages'] ?? [];
            $errorMsg = 'Unknown ComfyUI error';
            foreach ($messages as $msg) {
                if (($msg[0] ?? '') === 'execution_error') {
                    $errorMsg = $msg[1]['exception_message'] ?? $msg[1] ?? $errorMsg;
                    break;
                }
            }
            Log::error('ComfyUI: Job failed', ['prompt_id' => $promptId, 'error' => $errorMsg]);
            return ['done' => true, 'success' => false, 'files' => [], 'error' => $errorMsg];
        }

        if (empty($status['completed'])) {
            return ['done' => false, 'success' => false, 'files' => [], 'error' => null];
        }

        $files = $this->extractOutputFiles($jobData['outputs'] ?? []);

        Log::info('ComfyUI: Job completed', ['prompt_id' => $promptId, 'files' => count($files)]);

        return ['done' => true, 'success' => true, 'files' => $files, 'error' => null];
    }

    /**
     * Build the URL to view/download an output file from ComfyUI.
     */
    public function getFileUrl(string $filename, string $subfolder = '', string $type = 'output'): string
    {
        $params = http_build_query([
            'filename'  => $filename,
            'subfolder' => $subfolder,
            'type'      => $type,
        ]);

        return "{$this->baseUrl}/view?{$params}";
    }

    // -------------------------------------------------------------------------
    // Connectivity check
    // -------------------------------------------------------------------------

    public function isReachable(): bool
    {
        try {
            return Http::timeout(5)->get("{$this->baseUrl}/system_stats")->successful();
        } catch (\Exception $e) {
            Log::warning('ComfyUI: Server unreachable', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Extract all output file references from a completed job's outputs.
     * Handles images, video (gifs/mp4), and audio output nodes.
     */
    protected function extractOutputFiles(array $outputs): array
    {
        $files = [];

        foreach ($outputs as $nodeId => $nodeOutput) {
            foreach ($nodeOutput['images'] ?? [] as $file) {
                $files[] = $this->buildFileDescriptor($file);
            }
            foreach ($nodeOutput['gifs'] ?? [] as $file) {
                $files[] = $this->buildFileDescriptor($file);
            }
            foreach ($nodeOutput['videos'] ?? [] as $file) {
                $files[] = $this->buildFileDescriptor($file);
            }
            foreach ($nodeOutput['audio'] ?? [] as $file) {
                $files[] = array_merge($file, [
                    'media_type' => 'audio',
                    'url'        => $this->getFileUrl($file['filename'], $file['subfolder'] ?? '', $file['type'] ?? 'output'),
                ]);
            }
        }

        return $files;
    }

    protected function buildFileDescriptor(array $file): array
    {
        return array_merge($file, [
            'media_type' => $this->guessMediaType($file['filename']),
            'url'        => $this->getFileUrl($file['filename'], $file['subfolder'] ?? '', $file['type'] ?? 'output'),
        ]);
    }

    protected function guessMediaType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'png', 'jpg', 'jpeg', 'webp' => 'image',
            'mp4', 'webm'                => 'video',
            'gif'                        => 'gif',
            'mp3', 'wav', 'flac', 'ogg'  => 'audio',
            default                      => 'file',
        };
    }

    /**
     * Try to extract a human-readable error from ComfyUI's 500 response body.
     * ComfyUI returns plain text for some errors, JSON for others.
     */
    protected function parseComfyError(string $body): string
    {
        // Try JSON first
        $json = json_decode($body, true);
        if ($json && isset($json['error'])) {
            $msg = $json['error']['message'] ?? $json['error'];
            return is_string($msg) ? $msg : json_encode($msg);
        }

        // Plain text — truncate to something readable
        $clean = trim(strip_tags($body));
        return strlen($clean) > 200 ? substr($clean, 0, 200) . '...' : $clean;
    }
}