<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The `workflows` table stores your ComfyUI workflow JSON templates.
     * Each row is one template (e.g. "Image Generation", "Video Generation").
     * The `workflow_json` column holds the full ComfyUI node graph as JSON,
     * with placeholder tokens like {{POSITIVE_PROMPT}} that get replaced
     * before submission.
     */
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // Human-readable name, e.g. "Image Generation"
            $table->string('type');                        // 'image', 'video', or 'audio'
            $table->text('description')->nullable();       // What this workflow does
            $table->longText('workflow_json');             // The full ComfyUI JSON template
            $table->boolean('is_active')->default(true);  // Soft-disable without deleting
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};