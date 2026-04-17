# Laravel Ollama Chat 💬

A Laravel 12 multi-provider AI chatbot with tool-based intent classification, session management, streaming responses, and an integrated **ComfyUI workflow generation** pipeline. Supports Ollama, OpenAI, Mistral, Gemini, Groq, Anthropic, and many more AI providers out of the box.

---

## Overview

Laravel Ollama Chat is a full-stack conversational AI application. It routes user messages through a pattern-based intent classifier to decide whether to respond conversationally or invoke a specialized tool (workout generator, travel itinerary planner, TikTok trends finder, etc.). The app also includes a separate workflow pipeline that lets users upload files, refine prompts with an LLM, and dispatch ComfyUI generation jobs.

---

## Architecture

```
User Message
    │
    ▼
ChatController::classifyIntent()
    │  Pattern matching → "conversational" or "task"
    ├── conversational → ChatbotAgent (Ollama LLM, plain text response)
    │
    └── task → ToolMatcher → matching Tool
                   │  Keyword scoring against tool descriptions
                   ▼
              AgentGeneralService
                   │  Executes tool, returns structured result
                   ▼
              ChatbotAgent (wraps result in natural language)
```

---

## Features

- **Multi-provider AI** — switch between Ollama, OpenAI, Mistral, Gemini, Groq, Anthropic, DeepSeek, xAI, Cohere, ElevenLabs, and more via `config/ai.php`
- **Session management** — persistent conversation history per session; switch between multiple sessions
- **Streaming responses** — real-time token streaming via `/chat/stream`
- **Intent classification** — fast regex rules first, LLM fallback for ambiguous cases
- **Tool matching** — keyword-scored tool selection; automatically routes to the most relevant tool
- **File/attachment upload** — users can attach files to chat messages
- **ComfyUI workflow pipeline** — dedicated workflow UI for image/video generation with prompt refinement
- **Dockerized** — includes `compose.yaml` for containerized deployment with Sail

---

## Available Tools

Located in `app/Ai/Tools/`:

| Tool | Description |
|---|---|
| `WorkoutRoutineGeneratorTool` | Generates personalized workout routines |
| `TouristAttractionsInDestinationTool` | Finds tourist attractions for a given destination |
| `FindAccommodationOptionsTool` | Searches for accommodation options |
| `FindAccommodationsForFiveDaysTool` | Plans 5-day accommodation itinerary |
| `PopularDestinations7DaysTool` | Suggests popular destinations for a 7-day trip |
| `BookTravelItineraryTool` | Creates a full travel itinerary and booking plan |
| `TopTikTokTrendsTool` | Fetches current TikTok trends |

New tools can be added by implementing the `Tool` contract and dropping a new class into `app/Ai/Tools/`.

---

## Intent Classification

The `ChatController` uses two-stage classification:

**Stage 1 — Fast regex patterns:**

- Task patterns include keywords like `find`, `search`, `generate`, `calculate`, `trip`, `workout`, `recipe`, math expressions, and file extensions.
- Conversational patterns include greetings, emotional statements, acknowledgements, and questions about the agent itself.
- Time/date queries always route to `task` (LLMs hallucinate incorrect times).

**Stage 2 — LLM fallback** for messages that don't match either pattern set.

Short messages (≤ 2 words) are always classified as `conversational`.

---

## Routes

### Chat

| Method | Path | Description |
|---|---|---|
| `GET` | `/chat` | Chat interface view |
| `POST` | `/chat` | Send a message (non-streaming) |
| `POST` | `/chat/stream` | Send a message with streaming response |
| `POST` | `/chat/upload` | Upload an attachment |
| `POST` | `/session/init` | Initialize a new chat session |
| `GET` | `/session` | Get current session info |
| `GET` | `/sessions` | List all sessions |
| `POST` | `/session/switch` | Switch to a different session |
| `POST` | `/session/delete` | Delete a session |

### Workflow Pipeline

| Method | Path | Description |
|---|---|---|
| `GET` | `/` | Landing page |
| `GET` | `/workflow` | Workflow creation UI |
| `POST` | `/workflow/upload` | Upload a file for workflow input |
| `POST` | `/workflow/refine` | LLM-refine the workflow prompt |
| `POST` | `/workflow/approve` | Approve and dispatch the workflow job |
| `POST` | `/workflow/reset` | Reset current workflow session |
| `GET` | `/workflow/generations` | View past generations |
| `GET` | `/workflow/status/{jobId}` | Poll job execution status |
| `GET` | `/workflow/result/{jobId}` | View generation result |

---

## Database Schema

| Table | Description |
|---|---|
| `chats` | Chat messages with session_id, user_message, bot_reply |
| `agent_conversations` | Agent conversation state |
| `workflows` | Workflow definitions |
| `workflow_jobs` | Dispatched ComfyUI job tracking |

---

## AI Configuration

All providers are configured in `config/ai.php`. The default text provider is set to `mistral`.

```php
// config/ai.php
'default' => 'mistral',
'default_for_images' => 'gemini',
'default_for_audio' => 'openai',
```

Set API keys in `.env`:

```env
MISTRAL_API_KEY=
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
GEMINI_API_KEY=
GROQ_API_KEY=
DEEPSEEK_API_KEY=
ELEVENLABS_API_KEY=
OLLAMA_BASE_URL=http://host.docker.internal:11434
```

---

## ComfyUI Integration

ComfyUI endpoint is configured in `config/comfyui.php`. The `ComfyUIService` handles job submission and status polling. Workflow generation follows a multi-step review flow:

1. User uploads input (optional) and writes a prompt
2. `WorkflowOptimizerAgent` refines the prompt
3. User approves the refined prompt
4. Job is dispatched to ComfyUI via `WorkflowJob`
5. User polls `/workflow/status/{jobId}` until complete
6. Result available at `/workflow/result/{jobId}`

---

## Setup

### Requirements

- PHP 8.5+
- Laravel 12
- Composer
- Node.js + NPM
- Docker + Sail (recommended)
- Ollama (local) or any supported cloud provider key

### Installation with Sail (Docker)

```bash
# Clone and install
composer install

# Copy env
cp .env.example .env
php artisan key:generate

# Set your AI provider keys in .env (at minimum, set OLLAMA_BASE_URL or a cloud key)

# Start Docker services
vendor/bin/sail up -d

# Run migrations
vendor/bin/sail artisan migrate

# Seed workflows (optional)
vendor/bin/sail artisan db:seed

# Build frontend
vendor/bin/sail npm run build

# Start queue worker
vendor/bin/sail artisan queue:work
```

### Direct Installation

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve
```

---

## ChatbotAgent

The `ChatbotAgent` implements `Agent` and `Conversational` from Laravel AI. It loads the last 20 chat messages from the database as conversation history for context, ensuring coherent multi-turn responses without blowing up the context window.

Key instructions:
- Plain text only — no JSON, no markdown headers
- Present tool results naturally without embellishment
- Keep responses concise and friendly

---

## Tech Stack

- **Laravel 12** — PHP framework
- **Laravel AI** — Agent/Tool abstraction layer
- **Laravel Sail** — Docker development environment
- **Ollama** — Local LLM inference
- **ComfyUI** — Image/video generation backend
- **Blade** — Templating

---

## Development Notes

This project follows Laravel Boost guidelines (see `AGENTS.md` / `CLAUDE.md`):
- PHP 8.5, Laravel 12 streamlined structure
- All commands via `vendor/bin/sail`
- PHPUnit for testing (`vendor/bin/sail artisan test`)
- Code formatting via Pint (`vendor/bin/sail bin pint`)

---

## License

This project is unlicensed. All rights reserved to the author.
