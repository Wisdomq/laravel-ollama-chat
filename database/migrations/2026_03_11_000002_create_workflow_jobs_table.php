<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The `workflow_jobs` table tracks every job submitted to ComfyUI.
     * When a user approves a refined prompt, we submit it to ComfyUI and
     * store the returned `prompt_id` here. The frontend polls our Laravel
     * endpoint using `session_id`, which checks this table for job status.
     *
     * Status flow: pending → processing → completed | failed
     *
     * `refined_prompt`   - the final optimised text the LLM produced
     * `comfy_prompt_id`  - the UUID ComfyUI returns when you POST to /prompt
     * `result_paths`     - JSON array of output file paths from ComfyUI
     * `result_type`      - 'image', 'video', or 'audio'
     * `error_message`    - filled if ComfyUI returns an error
     */
    public function up(): void
    {
        Schema::create('workflow_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->text('refined_prompt');
            $table->string('comfy_prompt_id')->nullable()->index();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('result_type')->nullable();    // image, video, audio
            $table->json('result_paths')->nullable();     // array of output file paths
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_jobs');
    }
};