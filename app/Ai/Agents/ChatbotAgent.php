<?php

namespace App\Ai\Agents;

use App\Models\Chat;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Illuminate\Contracts\JsonSchema\JsonSchema;

use App\Ai\Tools\CalculatorTool;

class ChatbotAgent implements Agent, Conversational, HasTools
{
    public function tools(): iterable
    {
        return [
            new CalculatorTool(),
        ];
    }
    use Promptable;

    public function __construct(
        public ?string $conversationId = null
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return 'You are a concise and friendly assistant. Keep your responses helpful and to the point. Reply with plain text, not JSON.';
    }

    /**
     * Get the list of messages comprising the conversation so far.
     */
    public function messages(): iterable
    {
        if (!$this->conversationId) {
            return [];
        }

        $chats = Chat::where('session_id', $this->conversationId)
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

    // Only define schema if structured output is needed (non-streaming)
}