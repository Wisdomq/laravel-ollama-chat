<?php

namespace App\Http\Controllers;

use App\Ai\Agents\WorkflowOptimizerAgent;
use App\Models\Chat;
use App\Models\Workflow;
use App\Models\WorkflowJob;
use App\Services\ComfyUIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * WorkflowController
 *
 * Handles the full multi-modal workflow pipeline:
 *
 *   GET  /workflow                  → landing (chat vs workflow choice)
 *   GET  /workflow/create           → workflow refinement chat UI
 *   POST /workflow/upload           → upload an input file (image/video)
 *   POST /workflow/refine           → one refinement turn (SSE stream)
 *   POST /workflow/approve          → user approved prompt → submit to ComfyUI
 *   GET  /workflow/status/{jobId}   → poll for job completion
 *   GET  /workflow/result/{jobId}   → show finished result
 *   POST /workflow/reset            → clear session and start over
 */
class WorkflowController extends Controller
{
    public function __construct(
        protected ComfyUIService $comfyUI
    ) {}

    // -------------------------------------------------------------------------
    // Landing
    // -------------------------------------------------------------------------

    public function landing(): \Illuminate\View\View
    {
        return view('workflow.landing');
    }

    // -------------------------------------------------------------------------
    // Workflow creation – type selector + chat UI
    // -------------------------------------------------------------------------

    public function create(Request $request): \Illuminate\View\View
    {
        $workflowSessionId = $request->session()->get('workflow_session_id');
        if (! $workflowSessionId) {
            $workflowSessionId = Str::uuid()->toString();
            $request->session()->put('workflow_session_id', $workflowSessionId);
        }

        $workflows = Workflow::orderByRaw("FIELD(type, 'image', 'video', 'audio', 'image_to_video', 'video_to_video')")->get();
        $comfyReachable = $this->comfyUI->isReachable();
        $typeLabels = Workflow::typeLabels();
        $typeIcons = Workflow::typeIcons();

        return view('workflow.create', compact(
            'workflowSessionId',
            'workflows',
            'comfyReachable',
            'typeLabels',
            'typeIcons'
        ));
    }

    // -------------------------------------------------------------------------
    // File upload for image_to_video / video_to_video inputs
    // -------------------------------------------------------------------------

    public function upload(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,mp4,webm|max:51200',
        ]);

        $path = $request->file('file')->store('workflow-inputs', 'public');

        return response()->json(['path' => $path]);
    }

    // -------------------------------------------------------------------------
    // Refinement – SSE streamed response from WorkflowOptimizerAgent
    // -------------------------------------------------------------------------

    public function refine(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $sessionId = $request->session()->get('workflow_session_id');
        $workflowId = (int) $request->input('workflow_id');
        $workflowType = $request->input('workflow_type', Workflow::TYPE_IMAGE);
        $userMessage = $request->input('message');
        $uploadedFilePath = $request->input('uploaded_file_path');
        $chatAttachmentPath = $request->input('chat_attachment_path');

        if (! $sessionId) {
            $sessionId = Str::uuid()->toString();
            $request->session()->put('workflow_session_id', $sessionId);
        }

        // Validate that the requested workflow actually exists
        $workflow = Workflow::findOrFail($workflowId);

        $previousTurns = Chat::where('session_id', $sessionId)
            ->whereJsonContains('meta->type', 'workflow')
            ->count();

        $isFirstTurn = ($previousTurns === 0);

        $agent = new WorkflowOptimizerAgent($sessionId, $workflowId);

        return response()->stream(
            function () use ($agent, $userMessage, $sessionId, $workflowId, $workflowType, $isFirstTurn, $uploadedFilePath, $chatAttachmentPath) {
                $fullResponse = '';

                try {
                    // Append chat attachment context to the prompt if provided
                    $promptWithContext = $userMessage;
                    if ($chatAttachmentPath) {
                        $fileUrl = asset('storage/'.$chatAttachmentPath);
                        $promptWithContext .= "\n\n[Reference file attached: {$fileUrl}]";
                    }

                    foreach ($agent->stream($promptWithContext, provider: 'ollama', model: 'mistral:7b') as $event) {
                        if ($event instanceof \Laravel\Ai\Streaming\Events\TextDelta) {
                            $chunk = $event->delta;
                            $fullResponse .= $chunk;
                            echo 'data: '.json_encode(['chunk' => $chunk])."\n\n";
                            ob_flush();
                            flush();
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('WorkflowOptimizer stream error', ['error' => $e->getMessage()]);
                    echo 'data: '.json_encode(['error' => $e->getMessage()])."\n\n";
                    ob_flush();
                    flush();
                }

                // PHP-level guard: never approve on the first turn
                if ($isFirstTurn && str_contains($fullResponse, '[READY_FOR_APPROVAL]')) {
                    $fullResponse = preg_replace(
                        '/\[READY_FOR_APPROVAL\].*?\[\/READY_FOR_APPROVAL\]/s',
                        '',
                        $fullResponse
                    );

                    $trimmed = trim($fullResponse);
                    if (strlen($trimmed) < 20) {
                        $fullResponse = 'I have a good starting point! Before I finalise, let me ask: '.
                            'what mood or atmosphere are you going for – something bright and cheerful, '.
                            'dark and dramatic, or something else entirely?';
                    }

                    $correctionMsg = "\n\n[Clarifying before we finalise...]\n".trim($fullResponse);
                    echo 'data: '.json_encode(['chunk' => $correctionMsg])."\n\n";
                    ob_flush();
                    flush();
                }

                Chat::create([
                    'session_id' => $sessionId,
                    'user_message' => $userMessage,
                    'bot_reply' => trim($fullResponse),
                    'meta' => [
                        'type' => 'workflow',
                        'workflow_id' => $workflowId,
                        'workflow_type' => $workflowType,
                    ],
                ]);

                $isReady = false;
                $finalPrompt = null;
                $explanation = null;

                if (! $isFirstTurn && str_contains($fullResponse, '[READY_FOR_APPROVAL]')) {
                    $isReady = true;
                    $finalPrompt = $this->extractFinalPrompt($fullResponse);
                    $explanation = $this->extractExplanation($fullResponse);
                }

                echo 'data: '.json_encode([
                    'done' => true,
                    'is_ready' => $isReady,
                    'final_prompt' => $finalPrompt,
                    'explanation' => $explanation,
                    'workflow_id' => $workflowId,   // always echo back the correct ID
                    'workflow_type' => $workflowType,
                    'uploaded_file_path' => $uploadedFilePath,
                ])."\n\n";

                ob_flush();
                flush();
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Approve & submit to ComfyUI
    // -------------------------------------------------------------------------

    public function approve(Request $request): \Illuminate\Http\JsonResponse
    {
        $sessionId = $request->session()->get('workflow_session_id');
        $workflowId = (int) $request->input('workflow_id');
        $refinedPrompt = $request->input('refined_prompt');
        $uploadedFilePath = $request->input('uploaded_file_path');

        if (! $sessionId || ! $workflowId || ! $refinedPrompt) {
            return response()->json(['error' => 'Missing required data'], 400);
        }

        $workflow = Workflow::findOrFail($workflowId);

        if (! $workflow->hasRealWorkflow()) {
            $label = Workflow::typeLabels()[$workflow->type] ?? $workflow->type;

            return response()->json([
                'success' => false,
                'error' => "The '{$label}' workflow doesn't have a ComfyUI API JSON loaded yet. ".
                             'Export your workflow from ComfyUI using Save (API Format) and add it to the database.',
            ], 422);
        }

        try {
            $overrides = [];

            // If an input file was uploaded, resolve its absolute path for ComfyUI
            if ($uploadedFilePath) {
                $absolutePath = Storage::disk('public')->path($uploadedFilePath);
                $overrides['{{INPUT_FILE}}'] = $absolutePath;
            }

            $workflowJson = $workflow->injectPrompt($refinedPrompt, $overrides);
            $comfyPromptId = $this->comfyUI->submitWorkflow($workflowJson);

            $job = WorkflowJob::create([
                'session_id' => $sessionId,
                'workflow_id' => $workflowId,
                'refined_prompt' => $refinedPrompt,
                'comfy_prompt_id' => $comfyPromptId,
                'status' => WorkflowJob::STATUS_PROCESSING,
                'result_type' => $workflow->type,
            ]);

            \Log::info('Workflow job submitted', [
                'job_id' => $job->id,
                'prompt_id' => $comfyPromptId,
                'type' => $workflow->type,
                'session' => $sessionId,
            ]);

            return response()->json([
                'success' => true,
                'job_id' => $job->id,
                'message' => 'Submitted to ComfyUI! Generating your '.$workflow->type.'...',
            ]);

        } catch (\Exception $e) {
            \Log::error('ComfyUI submission failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Status polling
    // -------------------------------------------------------------------------

    public function status(Request $request, int $jobId): \Illuminate\Http\JsonResponse
    {
        $job = WorkflowJob::findOrFail($jobId);

        if ($job->isFinished()) {
            return response()->json([
                'done' => true,
                'success' => $job->isCompleted(),
                'job_id' => $job->id,
                'error' => $job->error_message,
            ]);
        }

        $result = $this->comfyUI->checkJobStatus($job->comfy_prompt_id);

        if (! $result['done']) {
            return response()->json(['done' => false]);
        }

        if ($result['success']) {
            $job->update([
                'status' => WorkflowJob::STATUS_COMPLETED,
                'result_paths' => $result['files'],
            ]);

            return response()->json([
                'done' => true,
                'success' => true,
                'job_id' => $job->id,
            ]);
        }

        $job->update([
            'status' => WorkflowJob::STATUS_FAILED,
            'error_message' => $result['error'],
        ]);

        return response()->json([
            'done' => true,
            'success' => false,
            'error' => $result['error'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Result page
    // -------------------------------------------------------------------------

    public function result(Request $request, int $jobId): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $job = WorkflowJob::with('workflow')->findOrFail($jobId);

        if (! $job->isCompleted()) {
            return redirect()->route('workflow.create')
                ->with('error', "This job hasn't completed yet.");
        }

        return view('workflow.result', compact('job'));
    }

    // -------------------------------------------------------------------------
    // My Generations
    // -------------------------------------------------------------------------

    public function generations(Request $request): \Illuminate\View\View
    {
        $typeFilter = $request->query('type');

        $query = WorkflowJob::with('workflow')
            ->where('status', WorkflowJob::STATUS_COMPLETED)
            ->latest();

        if ($typeFilter) {
            $query->where('result_type', $typeFilter);
        }

        $jobs = $query->paginate(24)->withQueryString();
        $typeLabels = Workflow::typeLabels();
        $typeIcons = Workflow::typeIcons();

        return view('workflow.generations', compact('jobs', 'typeLabels', 'typeIcons'));
    }

    // -------------------------------------------------------------------------
    // Session reset
    // -------------------------------------------------------------------------

    public function reset(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->session()->forget('workflow_session_id');

        return redirect()->route('workflow.create');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    protected function extractFinalPrompt(string $response): ?string
    {
        if (preg_match('/PROMPT:\s*(.+?)(?:\n|EXPLANATION:|\[\/READY_FOR_APPROVAL\])/s', $response, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function extractExplanation(string $response): ?string
    {
        if (preg_match('/EXPLANATION:\s*(.+?)(?:\n|\[\/READY_FOR_APPROVAL\])/s', $response, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
