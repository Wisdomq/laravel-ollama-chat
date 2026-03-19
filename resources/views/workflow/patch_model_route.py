base = '/home/wizmboya/Projects/chatbotapp'

# ── 1. Workflow model — add avatar_video ──────────────────────────────────
with open(base + '/app/Models/Workflow.php', 'r') as f:
    model = f.read()

model = model.replace(
    "    const TYPE_VIDEO_TO_VIDEO = 'video_to_video';",
    "    const TYPE_VIDEO_TO_VIDEO = 'video_to_video';\n    const TYPE_AVATAR_VIDEO  = 'avatar_video';"
)

model = model.replace(
    "            self::TYPE_VIDEO_TO_VIDEO => 'Video → Video',",
    "            self::TYPE_VIDEO_TO_VIDEO => 'Video → Video',\n            self::TYPE_AVATAR_VIDEO  => 'Talking Avatar',"
)

model = model.replace(
    "            self::TYPE_VIDEO_TO_VIDEO => '🔄',",
    "            self::TYPE_VIDEO_TO_VIDEO => '🔄',\n            self::TYPE_AVATAR_VIDEO  => '🗣️',"
)

with open(base + '/app/Models/Workflow.php', 'w') as f:
    f.write(model)

print("Workflow model updated")

# ── 2. WorkflowController — add generations() method ─────────────────────
with open(base + '/app/Http/Controllers/WorkflowController.php', 'r') as f:
    ctrl = f.read()

generations_method = '''
    // -------------------------------------------------------------------------
    // My Generations
    // -------------------------------------------------------------------------

    public function generations(Request $request): \\Illuminate\\View\\View
    {
        $typeFilter = $request->query('type');

        $query = WorkflowJob::with('workflow')
            ->where('status', WorkflowJob::STATUS_COMPLETED)
            ->latest();

        if ($typeFilter) {
            $query->where('result_type', $typeFilter);
        }

        $jobs       = $query->paginate(24)->withQueryString();
        $typeLabels = Workflow::typeLabels();
        $typeIcons  = Workflow::typeIcons();

        return view('workflow.generations', compact('jobs', 'typeLabels', 'typeIcons'));
    }

'''

# Insert before the reset() method
ctrl = ctrl.replace(
    '    // -------------------------------------------------------------------------\n    // Session reset',
    generations_method + '    // -------------------------------------------------------------------------\n    // Session reset'
)

with open(base + '/app/Http/Controllers/WorkflowController.php', 'w') as f:
    f.write(ctrl)

print("WorkflowController generations() added")

# ── 3. Routes — add generations route ────────────────────────────────────
with open(base + '/routes/web.php', 'r') as f:
    routes = f.read()

routes = routes.replace(
    "    Route::get('/status/{jobId}',          [WorkflowController::class, 'status'])->name('status');",
    "    Route::get('/generations',             [WorkflowController::class, 'generations'])->name('generations');\n    Route::get('/status/{jobId}',          [WorkflowController::class, 'status'])->name('status');"
)

with open(base + '/routes/web.php', 'w') as f:
    f.write(routes)

print("Route added")
print("All done")
