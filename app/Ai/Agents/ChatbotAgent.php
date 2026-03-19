<?php

namespace App\Ai\Agents;

use App\Models\Chat;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * ChatbotAgent
 *
 * Handles conversational responses and natural-language wrapping of
 * AgentGeneral skill results. Tool routing is handled upstream in
 * ChatController::classifyIntent() — this agent just talks.
 */
class ChatbotAgent implements Agent, Conversational
{
    use Promptable;

    public function __construct(
        public ?string $conversationId = null
    ) {}

    /**
     * System instructions for the agent.
     */
    public function instructions(): string
    {
        return <<<INSTRUCTIONS
        You are a concise, friendly, and helpful assistant.
        - Keep responses natural and to the point.
        - Reply in plain text — no JSON, no markdown headers.
        - When presenting results from a skill or tool, present them clearly
          and naturally without adding information that wasn't in the result.
        - For conversational messages, reply warmly and helpfully.
        INSTRUCTIONS;
    }

    /**
     * Load conversation history for this session.
     */
    public function messages(): iterable
    {
        if (!$this->conversationId) {
            return [];
        }

        $chats = Chat::where('session_id', $this->conversationId)
            ->orderBy('created_at')
            ->limit(20) // Keep context window manageable
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