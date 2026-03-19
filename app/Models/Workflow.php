<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    protected $fillable = [
        'name',
        'type',
        'description',
        'workflow_json',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Supported workflow types ──────────────────────────────────────────────

    const TYPE_IMAGE = 'image';

    const TYPE_VIDEO = 'video';

    const TYPE_AUDIO = 'audio';

    const TYPE_IMAGE_TO_VIDEO = 'image_to_video';

    const TYPE_VIDEO_TO_VIDEO = 'video_to_video';

    const TYPE_AVATAR_VIDEO = 'avatar_video';

    /**
     * Human-readable labels for each workflow type.
     * Used in the UI selector.
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_IMAGE => 'Text → Image',
            self::TYPE_VIDEO => 'Text → Video',
            self::TYPE_AUDIO => 'Text → Audio',
            self::TYPE_IMAGE_TO_VIDEO => 'Image → Video',
            self::TYPE_VIDEO_TO_VIDEO => 'Video → Video',
            self::TYPE_AVATAR_VIDEO => 'Talking Avatar',
        ];
    }

    /**
     * Icon for each workflow type (used in the UI).
     */
    public static function typeIcons(): array
    {
        return [
            self::TYPE_IMAGE => '🖼️',
            self::TYPE_VIDEO => '🎬',
            self::TYPE_AUDIO => '🎵',
            self::TYPE_IMAGE_TO_VIDEO => '🎞️',
            self::TYPE_VIDEO_TO_VIDEO => '🔄',
            self::TYPE_AVATAR_VIDEO => '🗣️',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function jobs(): HasMany
    {
        return $this->hasMany(WorkflowJob::class);
    }

    // ── Prompt injection ──────────────────────────────────────────────────────

    /**
     * Inject the refined prompt (and optional overrides) into the workflow JSON.
     *
     * Universal placeholders (all workflow types):
     *   {{POSITIVE_PROMPT}}   — the refined descriptive prompt
     *   {{NEGATIVE_PROMPT}}   — what to avoid (sensible default provided)
     *   {{SEED}}              — random seed (-1 = random)
     *
     * Image-specific:
     *   {{STEPS}}             — sampler steps  (default: 8 for Lightning LoRA)
     *   {{CFG}}               — guidance scale (default: 1.0 for Lightning LoRA)
     *   {{WIDTH}}             — output width   (default: 1024)
     *   {{HEIGHT}}            — output height  (default: 1024)
     *
     * Video-specific:
     *   {{FRAME_COUNT}}       — total frames   (default: 25)
     *   {{FPS}}               — frames per sec (default: 8)
     *   {{MOTION_STRENGTH}}   — motion amount  (default: 127)
     *
     * Audio-specific:
     *   {{DURATION}}          — seconds of audio (default: 10)
     *   {{SAMPLE_RATE}}       — audio sample rate (default: 44100)
     *
     * Image/Video-to-Video:
     *   {{DENOISE}}           — denoising strength (default: 0.75)
     *
     * @param  array  $overrides  Optional key=>value pairs for specific nodes
     * @return string Ready-to-submit workflow JSON string
     *
     * @throws \Exception if workflow_json is invalid
     */
    public function injectPrompt(string $positivePrompt, array $overrides = []): string
    {
        // ── Validate stored JSON before touching it ───────────────────────────
        // Double-decode guard: sometimes workflow_json gets double-encoded when
        // stored (JSON string inside a JSON string). Unwrap if needed.
        $raw = $this->workflow_json;

        // If it starts and ends with a quote, it may be double-encoded
        if (is_string($raw) && str_starts_with(trim($raw), '"')) {
            $unwrapped = json_decode($raw, true);
            if (is_string($unwrapped)) {
                $raw = $unwrapped; // was double-encoded — use the inner string
            }
        }

        // Verify the JSON is valid before injection
        json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(
                "Workflow '{$this->name}' has invalid JSON in the database: ".json_last_error_msg()
            );
        }

        // ── Escape prompt strings for safe JSON injection ────────────────────
        // json_encode adds surrounding quotes; we strip them to get just the
        // escaped interior (handles ", \, newlines, unicode etc.)
        $escapeForJson = function (string $value): string {
            return substr(json_encode($value), 1, -1);
        };

        $defaults = [
            // ── Universal ──────────────────────────────────────────────────
            '{{POSITIVE_PROMPT}}' => $escapeForJson($positivePrompt),
            '{{NEGATIVE_PROMPT}}' => $escapeForJson('blurry, low quality, distorted, watermark, text, ugly, deformed'),
            '{{SEED}}' => (string) rand(1, 999_999_999),

            // ── Image ──────────────────────────────────────────────────────
            '{{STEPS}}' => '8',
            '{{CFG}}' => '1.0',
            '{{WIDTH}}' => '1024',
            '{{HEIGHT}}' => '1024',

            // ── Video ──────────────────────────────────────────────────────
            '{{FRAME_COUNT}}' => '25',
            '{{FPS}}' => '8',
            '{{MOTION_STRENGTH}}' => '127',

            // ── Audio ──────────────────────────────────────────────────────
            '{{DURATION}}' => '10',
            '{{SAMPLE_RATE}}' => '44100',

            // ── img2vid / vid2vid ──────────────────────────────────────────
            '{{DENOISE}}' => '0.75',
        ];

        $replacements = array_merge($defaults, $overrides);

        $injected = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $raw
        );

        // ── Final validation: confirm the result is still valid JSON ──────────
        json_decode($injected);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(
                'Workflow JSON became invalid after prompt injection. '
                .'The prompt may contain characters that break JSON. Error: '
                .json_last_error_msg()
            );
        }

        return $injected;
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Returns true only if this workflow has a real ComfyUI API JSON loaded,
     * not a skeleton placeholder. Skeletons contain a "_note" key.
     */
    public function hasRealWorkflow(): bool
    {
        if (empty($this->workflow_json)) {
            return false;
        }
        $decoded = json_decode($this->workflow_json, true);
        if (! is_array($decoded)) {
            return false;
        }
        // Skeleton JSONs have a "_note" key — real ComfyUI API exports never do
        if (isset($decoded['_note'])) {
            return false;
        }
        // Real workflows have numeric-ish node keys and class_type fields
        foreach ($decoded as $key => $node) {
            if (isset($node['class_type'])) {
                return true;
            }
        }

        return false;
    }
}
