<?php

namespace App\Ai\Agents;

use App\Models\Chat;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * WorkflowOptimizerAgent
 *
 * Turns a user's rough idea into an optimised ComfyUI prompt through
 * genuine multi-turn dialogue — the agent asks ONE question per turn,
 * the user answers it, and the prompt improves with each exchange.
 *
 * The agent NEVER answers its own questions. It always waits for the user.
 *
 * Flow:
 *   Turn 1: User describes idea → agent asks first clarifying question
 *   Turn 2: User answers       → agent refines prompt, asks next question OR marks ready
 *   Turn N: Confidence >= 8   → agent outputs [READY_FOR_APPROVAL] block
 */
#[Temperature(0.7)]
class WorkflowOptimizerAgent implements Agent, Conversational
{
    use Promptable;

    public function __construct(
        public ?string $sessionId = null,
        public string  $workflowType = 'image'
    ) {}

    public function instructions(): string
    {
        $typeGuidance = match ($this->workflowType) {
            'video'          => 'You are refining a TEXT-TO-VIDEO prompt. Focus on: motion, scene duration, camera movement, cinematography style, lighting, and the single key action in the shot. Each prompt should describe ONE SHORT MOMENT (2-5 seconds), not a full story.',
            'audio'          => 'You are refining a TEXT-TO-AUDIO prompt. Focus on: genre, instruments, tempo (BPM), mood, energy level, and specific sonic textures. Be precise about what the listener will hear.',
            'image_to_video' => 'You are refining an IMAGE-TO-VIDEO prompt. The user will provide a source image. Focus on: what motion should be added, camera direction, speed, and duration. Describe the animation, not the scene.',
            'video_to_video' => 'You are refining a VIDEO-TO-VIDEO transformation prompt. Focus on: the style transfer target, what should change vs stay the same, and the transformation strength.',
            default          => 'You are refining a TEXT-TO-IMAGE prompt. Focus on: subject, art style, lighting, mood, camera angle, colour palette, and technical quality keywords (4K, cinematic, photorealistic, etc).',
        };

        return <<<INSTRUCTIONS
You are a ComfyUI prompt engineering specialist helping a user create an optimised generation prompt.

GENERATION TYPE: {$typeGuidance}

YOUR CRITICAL RULE:
You MUST ask the user questions. You MUST NOT answer your own questions.
Ask ONE question, then STOP and wait for the user's reply.
The user is a real person at their keyboard. Their answer will come in the next message.
NEVER write "User: ..." or "Answer: ..." or pretend to be the user.
NEVER write "Based on that, I'll assume..." and then carry on alone.

YOUR PROCESS EACH TURN:
1. Read what the user said carefully.
2. Update your internal draft prompt based on what they told you.
3. Count how many turns have happened so far (including this one).
4. Score your confidence that the draft is ready to generate (1-10).
5. Respond based on these rules:

TURN 1 (first message from user) — ALWAYS ask a question, no matter how specific the description:
- Show your current draft: "Current prompt: [draft here]"
- Ask ONE targeted question to make it even better.
- NEVER output [READY_FOR_APPROVAL] on the first turn. Always ask at least one question first.

TURN 2+ AND CONFIDENCE < 9:
- Show your updated draft: "Current prompt: [draft here]"
- Ask ONE more targeted question if there is still something meaningful to clarify.
- Stop. Wait for the user.

TURN 2+ AND CONFIDENCE >= 9 (prompt is truly excellent):
Output EXACTLY this block, nothing before or after:

[READY_FOR_APPROVAL]
PROMPT: {the complete optimised prompt, 30-80 words}
EXPLANATION: {one sentence: why this prompt will produce great results}
[/READY_FOR_APPROVAL]

WHAT MAKES A GOOD CLARIFYING QUESTION:
- Target the single biggest gap in the current draft
- Be specific: offer 2-3 concrete options where helpful so the user can answer quickly
- Example: "What mood should the lighting have — warm golden hour, cold blue night, or dramatic studio?" NOT "Tell me more"
- Never ask about things the user already mentioned

PROMPT QUALITY RULES:
- Be specific: subject, style, lighting, mood, camera/composition, quality tags
- 30-80 words: precise, not a wall of adjectives
- For video: describe ONE moment of motion, not a narrative
- For audio: include tempo, instruments, energy level, and mood
- Never include negative instructions in the positive prompt
- Never mention ComfyUI, AI, or generation tools in the prompt itself

EXAMPLE GOOD PROMPTS:
Image: "a lone wolf standing on a snowy ridge at dusk, dramatic side lighting, frosted fur detail, misty valley below, cinematic wide shot, cool blue tones, photorealistic, 4K"
Video: "a single golden autumn leaf detaching from a branch and spiraling slowly downward, soft backlight, shallow depth of field, 0.5x slow motion, warm amber tones"
Audio: "cinematic orchestral piece, 80 BPM, swelling strings with distant choir, building tension then releasing into silence, epic and emotional, film score style"

Remember: ONE question, then stop. Wait for the user to reply before continuing.
INSTRUCTIONS;
    }

    /**
     * Load this session's workflow refinement conversation history.
     */
    public function messages(): iterable
    {
        if (!$this->sessionId) {
            return [];
        }

        return Chat::where('session_id', $this->sessionId)
            ->whereJsonContains('meta->type', 'workflow')
            ->orderBy('created_at')
            ->get()
            ->flatMap(function ($chat) {
                $messages = [new Message('user', $chat->user_message)];
                if ($chat->bot_reply) {
                    $messages[] = new Message('assistant', $chat->bot_reply);
                }
                return $messages;
            })
            ->all();
    }
}