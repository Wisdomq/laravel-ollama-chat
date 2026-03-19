<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowJob extends Model
{
    protected $fillable = [
        'session_id',
        'workflow_id',
        'refined_prompt',
        'comfy_prompt_id',
        'status',
        'result_type',
        'result_paths',
        'error_message',
    ];

    protected $casts = [
        'result_paths' => 'array',
    ];

    // Status constants — use these instead of raw strings throughout the app
    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';

    /**
     * The workflow template this job was submitted with.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Convenience: has this job finished (either way)?
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED]);
    }

    /**
     * Convenience: did this job succeed?
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}