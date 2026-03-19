<?php

namespace App\Ai\Agents;

use App\Models\Chat;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use App\Models\Workflow;


/**
 * WorkflowOptimizerAgent
 *
 * This agent's sole job is to take a user's creative idea and
 * iteratively refine it into a precise, optimised ComfyUI prompt.
 *
 * HOW THE REFINEMENT LOOP WORKS:
 * --------------------------------
 * Each time the user sends a message, this agent:
 *   1. Refines the prompt based on the conversation so far
 *   2. Internally scores its own confidence (1–10)
 *   3. If confidence >= 8: returns the final prompt with a special
 *      [READY_FOR_APPROVAL] marker so the controller knows to show
 *      the approval UI
 *   4. If confidence < 8: asks one targeted clarifying question
 *      and shows the current draft prompt
 *
 * The confidence score and internal reasoning are NEVER shown to the
 * user — only the natural conversational response is shown.
 *
 * WHAT GOES INTO A GOOD COMFYUI PROMPT:
 * ----------------------------------------
 * ComfyUI (and diffusion models in general) respond best to prompts
 * that are specific about: subject, style, lighting, mood, camera
 * angle, color palette, and technical quality descriptors.
 * Bad:  "a knight in a forest"
 * Good: "a lone medieval knight in battered silver armor, standing in
 *        a misty ancient forest at golden hour, dramatic side lighting,
 *        cinematic composition, 4K, photorealistic, volumetric fog"
 *
 * SCENE DECOMPOSITION:
 * ----------------------
 * For video or multi-scene requests, the agent breaks the idea into
 * singular, discrete moments. Each moment becomes one focused prompt.
 * This keeps ComfyUI processing fast and the output quality high.
 */
#[Temperature(0.7)]
class WorkflowOptimizerAgent implements Agent, Conversational
{
    use Promptable;


    private Workflow $workflow;
    private string $workflowType;

    public function __construct(
        public ?string $sessionId = null,
        public int $workflowId){
            $this->workflow=Workflow::findOrFail($workflowId);
            $this->workflowType = $this->workflow->type;
        }

    /**
     * System instructions for the LLM.
     * This shapes the entire behaviour of the agent.
     */
    public function instructions(): string
    {
        $typeGuidance = match($this->workflowType) {
            'video' => 'The output is a VIDEO. Focus on motion, scene transitions, cinematography, and temporal flow. Break complex scenes into singular discrete moments — one focused action or visual per prompt. Keep each moment achievable in a short clip (2-5 seconds).',
            'audio' => 'The output is AUDIO. Focus on mood, instruments, tempo, genre, and emotional tone. Describe the soundscape precisely.',
            default => 'The output is an IMAGE. Focus on visual composition, lighting, style, color palette, and technical quality.',
        };

        return <<<INSTRUCTIONS
        You are a ComfyUI prompt engineering specialist. Your job is to help users turn their creative ideas into optimised, precise prompts that produce excellent results in ComfyUI (an AI generation tool).

        {$typeGuidance}

        YOUR PROCESS FOR EACH TURN:
        1. Understand what the user wants to create
        2. Refine the prompt — make it more specific, vivid, and technically precise
        3. Internally score your confidence that this prompt is ready (1-10)
        4. Respond based on your score:

        IF CONFIDENCE < 8 (still refining):
        - Show the current draft prompt clearly, labelled as "Current prompt:"
        - Ask ONE specific clarifying question to improve it
        - Keep your response concise

        IF CONFIDENCE >= 8 (prompt is ready):
        - Output EXACTLY this format, nothing else:

        [READY_FOR_APPROVAL]
        PROMPT: {the final optimised prompt text here}
        EXPLANATION: {one sentence explaining what makes this prompt effective}
        [/READY_FOR_APPROVAL]

        PROMPT QUALITY RULES:
        - Be specific about subject, style, lighting, mood, camera angle, and quality
        - Include technical quality descriptors: "4K", "cinematic", "photorealistic", "sharp focus"
        - For video: break into ONE singular moment, not a whole story
        - Never include negative instructions in the positive prompt
        - Keep prompts between 30-80 words — precise, not a wall of text
        - Never mention ComfyUI, AI, or generation tools in the prompt itself

        EXAMPLES OF GOOD PROMPTS:
        Image: "a lone wolf standing on a snowy mountain ridge at dusk, dramatic side lighting, fur detail, misty valley below, cinematic wide shot, cool blue tones, photorealistic, 4K"
        Video: "a single golden leaf detaching from a branch and slowly spiraling downward, soft autumn sunlight, shallow depth of field, slow motion, warm tones"

        Stay in character as a prompt engineer. Be encouraging but focused.
        INSTRUCTIONS;
    }

    /**
     * Load conversation history for this session from the chats table.
     * We store workflow refinement conversations in the same chats table
     * as regular chat, distinguished by a 'workflow' key in the meta column.
     */
    public function messages(): iterable
    {
        if (!$this->sessionId) {
            return [];
        }

        $chats = Chat::where('session_id', $this->sessionId)
            ->where(function ($q) {
                // Only load messages from workflow refinement turns
                $q->whereJsonContains('meta->type', 'workflow');
            })
            ->orderBy('created_at')
            ->get();

        $messages = [];
        foreach ($chats as $chat) {
            $messages[] = new Message('user', $chat->user_message);
            if ($chat->bot_reply) {
                $messages[] = new Message('assistant', $chat->bot_reply);
            }
        }

        return $messages;
    }
}