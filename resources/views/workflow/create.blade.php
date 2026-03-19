@extends('layouts.app')

@section('title', 'Create Workflow AI Studio')

@push('styles')
<style>

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f9f7; }
        .container { display: flex; height: 100vh; }
        .sidebar { width: 280px; background: #1e4d2b; color: white; padding: 20px; display: flex; flex-direction: column; gap: 16px; }
        .sidebar h3 { color: #a8d5ba; font-size: 18px; }
        .back-btn { display: inline-block; padding: 8px 14px; background: #2d5a38; color: #a8d5ba; border-radius: 6px; font-size: 13px; text-decoration: none; transition: background 0.2s; }
        .back-btn:hover { background: #35694a; }
        .type-selector label { display: block; color: #a8d5ba; font-size: 12px; margin-bottom: 8px; }
        .type-btn { width: 100%; padding: 10px; margin-bottom: 6px; background: #2d5a38; color: white; border: 2px solid transparent; border-radius: 6px; cursor: pointer; font-size: 13px; text-align: left; transition: all 0.2s; }
        .type-btn:hover { background: #35694a; }
        .type-btn.active { border-color: #7ec87f; background: #35694a; }
        .type-btn.not-ready { opacity: 0.5; cursor: not-allowed; }
        .type-btn.not-ready:hover { background: #2d5a38; }
        .coming-soon { display: inline-block; font-size: 9px; background: #5a7d5f; color: #c8e6c9; padding: 1px 5px; border-radius: 3px; margin-left: 4px; vertical-align: middle; text-transform: uppercase; letter-spacing: 0.5px; }
        .comfy-status { font-size: 12px; display: flex; align-items: center; gap: 6px; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; }
        .status-dot.online { background: #7ec87f; }
        .status-dot.offline { background: #e57373; }
        .sidebar-footer { margin-top: auto; font-size: 11px; color: #5a7d5f; }
        .main { flex: 1; display: flex; flex-direction: column; }
        .header { background: linear-gradient(135deg, #3d6b3a 0%, #2d5a38 100%); padding: 16px 20px; color: white; }
        .header h2 { font-size: 18px; margin-bottom: 4px; }
        .header p { font-size: 12px; opacity: 0.8; }
        .chat-area { flex: 1; padding: 20px; overflow-y: auto; background: #f9fbfa; }
        .message { margin: 14px 0; display: flex; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
        .message-content { padding: 12px 16px; border-radius: 8px; max-width: 75%; word-wrap: break-word; line-height: 1.6; }
        .user-msg { justify-content: flex-end; }
        .user-msg .message-content { background: #3d6b3a; color: white; }
        .bot-msg { justify-content: flex-start; }
        .bot-msg .message-content { background: #f0f6f3; color: #2d5a38; border: 1px solid #dbe8e2; }
        .typing-indicator { display: flex; align-items: center; gap: 4px; padding: 12px 16px; }
        .typing-dot { width: 8px; height: 8px; border-radius: 50%; background: #7ec87f; animation: typing 1.4s infinite; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing { 0%,60%,100%{opacity:0.3;} 30%{opacity:1;} }

        .approval-card { background: white; border: 2px solid #7ec87f; border-radius: 10px; padding: 20px; margin: 16px 0; max-width: 75%; }
        .approval-card h4 { color: #1e4d2b; margin-bottom: 10px; font-size: 15px; }
        .approval-prompt { background: #f0f6f3; border-left: 4px solid #7ec87f; padding: 12px 14px; border-radius: 4px; font-style: italic; color: #2d5a38; margin-bottom: 10px; font-size: 14px; line-height: 1.6; }
        .approval-explanation { font-size: 12px; color: #5a7d5f; margin-bottom: 16px; }
        .approval-buttons { display: flex; gap: 10px; }
        .approve-btn { padding: 10px 20px; background: #3d6b3a; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
        .approve-btn:hover { background: #2d5a38; }
        .approve-btn:disabled { background: #b8d4ba; cursor: not-allowed; }
        .refine-btn { padding: 10px 20px; background: white; color: #3d6b3a; border: 2px solid #3d6b3a; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
        .refine-btn:hover { background: #f0f6f3; }
        .input-area { padding: 16px 20px; border-top: 2px solid #dbe8e2; background: #f9fbfa; }
        .input-row { display: flex; gap: 10px; align-items: center; }
        .input-row input[type="text"] { flex: 1; padding: 12px; border: 2px solid #dbe8e2; border-radius: 6px; font-size: 14px; background: white; transition: border-color 0.3s; }
        .input-row input[type="text"]:focus { outline: none; border-color: #7ec87f; }
        .send-btn { padding: 12px 24px; background: #3d6b3a; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; transition: all 0.2s; white-space: nowrap; }
        .send-btn:hover { background: #2d5a38; }
        .send-btn:disabled { background: #b8d4ba; cursor: not-allowed; }
        .attach-btn { padding: 12px 14px; background: #f0f6f3; color: #3d6b3a; border: 2px solid #dbe8e2; border-radius: 6px; cursor: pointer; font-size: 16px; transition: all 0.2s; flex-shrink: 0; }
        .attach-btn:hover { background: #dbe8e2; border-color: #7ec87f; }
        .attach-btn.has-file { background: #d4edda; border-color: #7ec87f; }
        .chat-file-preview { display: none; align-items: center; gap: 8px; padding: 6px 12px; background: #f0f6f3; border-radius: 6px; font-size: 12px; color: #2d5a38; border: 1px solid #dbe8e2; margin-bottom: 6px; }
        .chat-file-preview.visible { display: flex; }
        .chat-file-preview .remove-chat-file { cursor: pointer; color: #e57373; font-weight: bold; background: none; border: none; font-size: 14px; padding: 0 2px; }
        .upload-strip { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding: 8px 12px; background: #f0f6f3; border-radius: 6px; border: 1px dashed #b8d4ba; }
        .upload-strip label { font-size: 12px; color: #5a7d5f; cursor: pointer; display: flex; align-items: center; gap: 6px; }
        .upload-strip input[type="file"] { display: none; }
        .upload-strip .file-name { font-size: 12px; color: #2d5a38; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .upload-strip .clear-file { font-size: 11px; color: #e57373; cursor: pointer; background: none; border: none; padding: 0; }
        .upload-type-note { font-size: 11px; color: #5a7d5f; margin-left: auto; }
        .popup { display: none; position: fixed; bottom: 24px; right: 24px; background: #1e4d2b; color: white; padding: 16px 20px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); z-index: 1000; max-width: 320px; animation: popIn 0.4s ease-out; }
        .popup.show { display: block; }
        @keyframes popIn { from{opacity:0;transform:translateY(20px);} to{opacity:1;transform:translateY(0);} }
        .popup h4 { margin-bottom: 6px; font-size: 15px; }
        .popup p { font-size: 13px; opacity: 0.85; margin-bottom: 12px; }
        .popup-btn { padding: 8px 18px; background: #7ec87f; color: #1e4d2b; border: none; border-radius: 5px; cursor: pointer; font-weight: 700; font-size: 13px; }
        .popup-btn:hover { background: #6db86e; }
        .minigame-overlay { display: none; position: fixed; inset: 0; background: rgba(20,40,25,0.92); z-index: 500; flex-direction: column; align-items: center; justify-content: center; gap: 16px; }
        .minigame-overlay.active { display: flex; }
        .minigame-title { color: #a8d5ba; font-size: 18px; font-weight: 700; }
        .minigame-subtitle { color: #7ec87f; font-size: 13px; }
        #gameCanvas { border: 2px solid #3d6b3a; border-radius: 8px; background: #0d1f10; cursor: none; }
        .game-score { color: #7ec87f; font-size: 14px; font-family: monospace; }
        .game-hint { color: #5a7d5f; font-size: 12px; }
        .skip-game-btn { padding: 8px 20px; background: transparent; color: #5a7d5f; border: 1px solid #3d6b3a; border-radius: 5px; cursor: pointer; font-size: 12px; margin-top: 4px; }
        .skip-game-btn:hover { color: #a8d5ba; border-color: #7ec87f; }
    
    /* ── Layout shell ── */
    .workflow-shell { display: flex; height: calc(100vh - 52px); }
    .workflow-sidebar { width: 260px; min-width: 260px; overflow-y: auto; }
    .workflow-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .type-btn { width: 100%; padding: 9px 12px; margin-bottom: 5px; background: var(--green-800); color: white; border: 2px solid transparent; border-radius: var(--radius-md); cursor: pointer; font-size: 13px; text-align: left; transition: all var(--transition); }
    .type-btn:hover { background: var(--green-600); }
    .type-btn.active { border-color: var(--green-400); background: var(--green-600); }
    .type-btn.not-ready { opacity: 0.5; cursor: not-allowed; }
    .type-btn.not-ready:hover { background: var(--green-800); }
</style>
@endpush

@section('content')
<div class="workflow-shell">

    {{-- Sidebar --}}
    <div class="sidebar workflow-sidebar">
        <a href="{{ route('landing') }}" class="back-btn">Back to Home</a>
        <h3>🎨 Workflow Creator</h3>

        <div class="type-selector">
            <label>OUTPUT TYPE</label>
            @forelse($workflows as $wf)
                @php
                    $isReady  = $wf->hasRealWorkflow();
                    $isFirst  = $loop->first;
                    $btnClass = 'type-btn' . ($isFirst ? ' active' : '') . ($isReady ? '' : ' not-ready');
                @endphp
                <button
                    class="{{ $btnClass }}"
                    data-workflow-id="{{ $wf->id }}"
                    data-workflow-type="{{ $wf->type }}"
                    data-is-ready="{{ $isReady ? 'true' : 'false' }}"
                    onclick="selectWorkflow(this, {{ $wf->id }}, '{{ $wf->type }}', {{ $isReady ? 'true' : 'false' }})"
                    title="{{ $isReady ? $wf->description : 'No workflow JSON loaded yet' }}"
                >
                    {{ $typeIcons[$wf->type] ?? '🎨' }}
                    {{ $typeLabels[$wf->type] ?? $wf->name }}
                    @if(!$isReady)<span class="coming-soon">soon</span>@endif
                </button>
            @empty
                <p style="font-size:12px;color:#5a7d5f;">No workflow templates found.<br>Run the seeder first.</p>
            @endforelse
        </div>

        <div class="comfy-status">
            <div class="status-dot {{ $comfyReachable ? 'online' : 'offline' }}"></div>
            <span>ComfyUI: {{ $comfyReachable ? 'Connected' : 'Offline' }}</span>
        </div>

        <div class="sidebar-footer">
            Session: {{ substr($workflowSessionId, 0, 8) }}<br>
            Mistral · open-mistral-7b
        </div>
    </div>

    {{-- Main --}}
    <div class="main workflow-main">
        <div class="header">
            <h2 id="mainHeader">Describe Your Vision</h2>
            <p id="mainSubheader">Tell me what you want to create  EI'll ask a few questions to refine your prompt</p>
        </div>

        <div class="chat-area" id="chatbox">
            <div class="message bot-msg" id="welcomeMessage">
                <div class="message-content">
                    👋 Hi! I'm your prompt engineer. Tell me what you'd like to generate and I'll ask a few targeted questions to build the perfect prompt.<br><br>
                    <strong>For example:</strong> "I want a moody image of a lone wolf in a snowy forest" or "Create audio that sounds like a cinematic film score".
                </div>
            </div>
        </div>

        <div class="input-area">
            {{-- File upload strip for image_to_video / video_to_video source files --}}
            <div class="upload-strip" id="uploadStrip" style="display:none;">
                <label for="workflowFile">
                    📎 <span id="uploadLabel">Attach input file</span>
                    <input type="file" id="workflowFile" accept="image/*,video/*" onchange="onFileSelected(this)">
                </label>
                <span class="file-name" id="selectedFileName">No file chosen</span>
                <button class="clear-file" id="clearFileBtn" onclick="clearFile()" style="display:none;">✁E/button>
                <span class="upload-type-note" id="uploadTypeNote"></span>
            </div>
            {{-- General chat file attachment preview --}}
            <div class="chat-file-preview" id="chatFilePreview">
                <span id="chatFilePreviewName"></span>
                <button class="remove-chat-file" onclick="removeChatAttachment()" title="Remove">&#x2715;</button>
            </div>
            <div class="input-row">
                <input type="file" id="chatFileInput" style="display:none" accept="image/*,video/*,audio/*,.pdf,.txt" onchange="onChatFileChosen(this)">
                <button class="attach-btn" id="chatAttachBtn" onclick="document.getElementById('chatFileInput').click()" title="Attach reference file">&#x1F4CE;</button>
                <input type="text" id="message" placeholder="Describe what you want to create..." onkeypress="handleKeyPress(event)" autocomplete="off">
                <button class="send-btn" id="sendBtn" onclick="sendMessage()">Send</button>
            </div>
        </div>
    </div>
</div>

{{-- Completion popup --}}
<div class="popup" id="completionPopup">
    <h4>✁EGeneration Complete!</h4>
    <p>Your ComfyUI job has finished. Click below to see the result.</p>
    <button class="popup-btn" id="viewResultBtn" onclick="viewResult()">View Result ↁE/button>
</div>

{{-- Mini-game overlay --}}
<div class="minigame-overlay" id="minigameOverlay">
    <div class="minigame-title">⏳ Generating your content…</div>
    <div class="minigame-subtitle">Play while you wait!</div>
    <canvas id="gameCanvas" width="400" height="300"></canvas>
    <div class="game-score">Score: <span id="gameScore">0</span> &nbsp;|&nbsp; Best: <span id="gameBest">0</span></div>
    <div class="game-hint">Arrow keys or WASD to move · eat the green dots · avoid red</div>
    <button class="skip-game-btn" onclick="hideMinigame()">Skip game</button>
</div>








@endsection

@push('scripts')
<script>
const CSRF_TOKEN = '{{ csrf_token() }}';
const SESSION_ID = '{{ $workflowSessionId }}';

let selectedWorkflowId   = {{ $workflows->first()?->id ?? 'null' }};
let selectedWorkflowType = '{{ $workflows->first()?->type ?? "image" }}';
let isStreaming           = false;
let pendingJobId          = null;
let pollInterval          = null;
let resultJobId           = null;
let uploadedFilePath      = null;   // for image_to_video / video_to_video source
let chatAttachmentPath    = null;   // for general chat reference file

const typeLabels = @json($typeLabels);
const typeIcons  = @json($typeIcons);

const typeWelcome = {
    'image':          "Tell me what image you want to create I'll ask a few questions to build the perfect prompt.",
    'video':          "Describe the video you want, e.g. what's happening, what mood, what style? I'll ask a few questions.",
    'audio':          "Tell me about the audio you want; What genre, mood, instruments? I'll help refine it.",
    'image_to_video': "Describe what motion you want to add to your image.I'll ask a few questions about the animation.",
    'video_to_video': "Describe the style transformation you want applied to your video.",
};

// Types that require a dedicated source-file upload strip
const uploadTypes = {
    'image_to_video': { accept: 'image/*', label: 'Attach source image', note: 'JPG / PNG / WEBP' },
    'video_to_video': { accept: 'video/*', label: 'Attach source video',  note: 'MP4 / WEBM' },
};

// ── Workflow type selection ────────────────────────────────────────────────
function selectWorkflow(btn, id, type, isReady) {
    if (!isReady) { showNotReadyMessage(type); return; }

    document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    selectedWorkflowId   = id;
    selectedWorkflowType = type;

    const label = typeLabels[type] || type;
    const icon  = typeIcons[type]  || '🎨';
    document.getElementById('mainHeader').textContent    = icon + ' ' + label;
    document.getElementById('mainSubheader').textContent = "I'll ask you a few questions to craft the perfect prompt";

    const wm = document.getElementById('welcomeMessage');
    if (wm) {
        wm.querySelector('.message-content').innerHTML =
            "👋 Hi! I'm your prompt engineer. " + (typeWelcome[type] || "Tell me what you want to create.") +
            "<br><br><strong>Just describe your idea</strong> I'll guide you with questions, one at a time.";
    }

    // Show / hide source-file upload strip
    const strip = document.getElementById('uploadStrip');
    if (uploadTypes[type]) {
        const cfg = uploadTypes[type];
        document.getElementById('workflowFile').accept = cfg.accept;
        document.getElementById('uploadLabel').textContent = cfg.label;
        document.getElementById('uploadTypeNote').textContent = cfg.note;
        strip.style.display = 'flex';
    } else {
        strip.style.display = 'none';
        clearFile();
    }
}

function showNotReadyMessage(type) {
    const name = typeLabels[type] || type;
    appendBotMessage("⚠�E�E<strong>" + name + "</strong> isn't set up yet  Eit needs its own ComfyUI workflow JSON. " +
        "Export a workflow in API format from ComfyUI for this type, then add it to the database via the seeder or directly." +
        "<br><br>For now, <strong>Text ↁEImage</strong> is ready to use!");
}

// ── Source-file upload helpers (image_to_video / video_to_video) ──────────
function onFileSelected(input) {
    if (!input.files.length) return;
    document.getElementById('selectedFileName').textContent = input.files[0].name;
    document.getElementById('clearFileBtn').style.display = 'inline';
    uploadedFilePath = null;
}

function clearFile() {
    document.getElementById('workflowFile').value = '';
    document.getElementById('selectedFileName').textContent = 'No file chosen';
    document.getElementById('clearFileBtn').style.display = 'none';
    uploadedFilePath = null;
}

async function uploadWorkflowFile() {
    const input = document.getElementById('workflowFile');
    if (!input.files.length) return null;
    const formData = new FormData();
    formData.append('file', input.files[0]);
    formData.append('_token', CSRF_TOKEN);
    const res = await fetch('/workflow/upload', { method: 'POST', body: formData });
    if (!res.ok) throw new Error('File upload failed');
    const data = await res.json();
    return data.path ?? null;
}

// ── General chat attachment helpers ───────────────────────────────────────
function onChatFileChosen(input) {
    if (!input.files.length) return;
    document.getElementById('chatFilePreviewName').textContent = '📎 ' + input.files[0].name;
    document.getElementById('chatFilePreview').classList.add('visible');
    document.getElementById('chatAttachBtn').classList.add('has-file');
    chatAttachmentPath = null;
}

function removeChatAttachment() {
    document.getElementById('chatFileInput').value = '';
    document.getElementById('chatFilePreview').classList.remove('visible');
    document.getElementById('chatAttachBtn').classList.remove('has-file');
    chatAttachmentPath = null;
}

async function uploadChatAttachment() {
    const input = document.getElementById('chatFileInput');
    if (!input.files.length) return null;
    const formData = new FormData();
    formData.append('file', input.files[0]);
    formData.append('_token', CSRF_TOKEN);
    const res = await fetch('/workflow/upload', { method: 'POST', body: formData });
    if (!res.ok) throw new Error('Chat file upload failed');
    const data = await res.json();
    return data.path ?? null;
}
</script>
<script>
// ── Send refinement message ────────────────────────────────────────────────
async function sendMessage() {
    const input   = document.getElementById('message');
    const message = input.value.trim();
    if (!message || isStreaming) return;

    if (!selectedWorkflowId) {
        alert('Please select an output type first.');
        return;
    }

    // Upload source file if needed for this workflow type
    if (uploadTypes[selectedWorkflowType]) {
        const fileInput = document.getElementById('workflowFile');
        if (fileInput.files.length && !uploadedFilePath) {
            try {
                appendSystemMessage('📎 Uploading your file…');
                uploadedFilePath = await uploadWorkflowFile();
            } catch (e) {
                appendSystemMessage('❁EFile upload failed: ' + e.message);
                return;
            }
        }
    }

    // Upload general chat attachment if staged
    if (document.getElementById('chatFileInput').files.length && !chatAttachmentPath) {
        try {
            chatAttachmentPath = await uploadChatAttachment();
        } catch (e) {
            appendSystemMessage('❁EAttachment upload failed: ' + e.message);
            return;
        }
    }

    appendUserMessage(message);
    input.value = '';
    isStreaming  = true;
    document.getElementById('sendBtn').disabled = true;

    // Clear chat attachment preview after sending
    removeChatAttachment();

    const typingEl = appendTypingIndicator();

    try {
        const response = await fetch('/workflow/refine', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: JSON.stringify({
                message:              message,
                workflow_id:          selectedWorkflowId,
                workflow_type:        selectedWorkflowType,
                uploaded_file_path:   uploadedFilePath,
                chat_attachment_path: chatAttachmentPath,
            }),
        });

        typingEl.remove();
        const botContentEl = appendBotMessageContainer();
        const reader  = response.body.getReader();
        const decoder = new TextDecoder();
        let   buffer  = '';
        let   fullText = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value);
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';

            for (const line of lines) {
                if (!line.startsWith('data: ')) continue;
                try {
                    const data = JSON.parse(line.substring(6));

                    if (data.chunk) {
                        fullText += data.chunk;
                        const displayText = fullText.replace(/\[READY_FOR_APPROVAL\][\s\S]*?\[\/READY_FOR_APPROVAL\]/g, '').trim();
                        botContentEl.textContent = displayText || '...';
                        scrollToBottom();
                    }

                    if (data.done) {
                        isStreaming = false;
                        document.getElementById('sendBtn').disabled = false;

                        if (data.is_ready && data.final_prompt) {
                            botContentEl.closest('.message').remove();
                            appendReadyMessage(data.final_prompt, data.explanation, data.workflow_id);
                        }
                    }
                } catch (e) { /* ignore */ }
            }
        }
    } catch (err) {
        typingEl.remove();
        appendSystemMessage('Error: ' + err.message);
        isStreaming = false;
        document.getElementById('sendBtn').disabled = false;
    }
}

// ── Approval card ──────────────────────────────────────────────────────────
function appendReadyMessage(finalPrompt, explanation, workflowId) {
    const chatbox = document.getElementById('chatbox');
    const wrapper = document.createElement('div');
    wrapper.className = 'message bot-msg';

    const card = document.createElement('div');
    card.className = 'approval-card';
    card.innerHTML = `
        <h4>✨ Prompt Ready for Approval</h4>
        <div class="approval-prompt">${escapeHtml(finalPrompt)}</div>
        <div class="approval-explanation">${escapeHtml(explanation || '')}</div>
        <div class="approval-buttons">
            <button class="approve-btn" onclick="approveWorkflow('${escapeHtml(finalPrompt)}', ${workflowId}, this)">
                🚀 Submit to ComfyUI
            </button>
            <button class="refine-btn" onclick="requestMoreRefinement(this)">
                ✏︁ERefine More
            </button>
        </div>
    `;
    wrapper.appendChild(card);
    chatbox.appendChild(wrapper);
    scrollToBottom();
}

// ── Submit to ComfyUI ──────────────────────────────────────────────────────
async function approveWorkflow(finalPrompt, workflowId, btn) {
    btn.disabled    = true;
    btn.textContent = '⏳ Submitting…';

    try {
        const response = await fetch('/workflow/approve', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: JSON.stringify({
                refined_prompt:     finalPrompt,
                workflow_id:        workflowId,
                uploaded_file_path: uploadedFilePath,
            }),
        });

        const data = await response.json();

        if (data.success) {
            btn.textContent = '✁ESubmitted!';
            appendSystemMessage('🎬 Submitted to ComfyUI! I\'ll notify you when the generation is complete…');
            showMinigame();
            startPolling(data.job_id);
        } else {
            btn.disabled    = false;
            btn.textContent = '🚀 Submit to ComfyUI';
            appendSystemMessage('❁ESubmission failed: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        btn.disabled    = false;
        btn.textContent = '🚀 Submit to ComfyUI';
        appendSystemMessage('❁EError: ' + err.message);
    }
}

function requestMoreRefinement(btn) {
    btn.closest('.message').remove();
    document.getElementById('message').value = 'Please refine the prompt further.';
    document.getElementById('message').focus();
}

// ── Polling ────────────────────────────────────────────────────────────────
function startPolling(jobId) {
    pendingJobId = jobId;
    pollInterval = setInterval(async () => {
        try {
            const res  = await fetch(`/workflow/status/${jobId}`);
            const data = await res.json();

            if (data.done) {
                clearInterval(pollInterval);
                pollInterval = null;
                hideMinigame();

                if (data.success) {
                    resultJobId = data.job_id;
                    showCompletionPopup();
                } else {
                    appendSystemMessage('❁EComfyUI job failed: ' + (data.error || 'Unknown error'));
                }
            }
        } catch (err) {
            console.error('Polling error:', err);
        }
    }, 4000);
}

function showCompletionPopup() {
    document.getElementById('completionPopup').classList.add('show');
}

function viewResult() {
    window.location.href = `/workflow/result/${resultJobId}`;
}

// ── DOM helpers ────────────────────────────────────────────────────────────
function appendUserMessage(text) {
    const chatbox = document.getElementById('chatbox');
    const div = document.createElement('div');
    div.className = 'message user-msg';
    div.innerHTML = `<div class="message-content">${escapeHtml(text)}</div>`;
    chatbox.appendChild(div);
    scrollToBottom();
}

function appendBotMessageContainer() {
    const chatbox = document.getElementById('chatbox');
    const div = document.createElement('div');
    div.className = 'message bot-msg';
    const content = document.createElement('div');
    content.className = 'message-content';
    content.textContent = '...';
    div.appendChild(content);
    chatbox.appendChild(div);
    scrollToBottom();
    return content;
}

function appendBotMessage(html) {
    const chatbox = document.getElementById('chatbox');
    const div = document.createElement('div');
    div.className = 'message bot-msg';
    div.innerHTML = `<div class="message-content">${html}</div>`;
    chatbox.appendChild(div);
    scrollToBottom();
}

function appendTypingIndicator() {
    const chatbox = document.getElementById('chatbox');
    const div = document.createElement('div');
    div.className = 'message bot-msg';
    div.innerHTML = `<div class="typing-indicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>`;
    chatbox.appendChild(div);
    scrollToBottom();
    return div;
}

function appendSystemMessage(text) {
    const chatbox = document.getElementById('chatbox');
    const div = document.createElement('div');
    div.className = 'message bot-msg';
    div.innerHTML = `<div class="message-content" style="background:#fff8e8;border-color:#f0d070;">${escapeHtml(text)}</div>`;
    chatbox.appendChild(div);
    scrollToBottom();
}

function scrollToBottom() {
    const chatbox = document.getElementById('chatbox');
    chatbox.scrollTop = chatbox.scrollHeight;
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function handleKeyPress(e) {
    if (e.key === 'Enter' && !isStreaming) sendMessage();
}
</script>
<script>
// ── Mini-game: Snake ───────────────────────────────────────────────────────
let gameLoop = null;
let gameBest = 0;

function showMinigame() {
    document.getElementById('minigameOverlay').classList.add('active');
    startSnake();
}

function hideMinigame() {
    document.getElementById('minigameOverlay').classList.remove('active');
    stopSnake();
}

let snake, dir, food, enemies, score, gameRunning;

function startSnake() {
    const canvas = document.getElementById('gameCanvas');
    const ctx    = canvas.getContext('2d');
    const W = canvas.width, H = canvas.height;
    const CELL = 20;
    const COLS = W / CELL, ROWS = H / CELL;

    snake       = [{ x: 10, y: 7 }, { x: 9, y: 7 }, { x: 8, y: 7 }];
    dir         = { x: 1, y: 0 };
    score       = 0;
    gameRunning = true;
    enemies     = [];

    function randCell() {
        return { x: Math.floor(Math.random() * COLS), y: Math.floor(Math.random() * ROWS) };
    }

    function spawnFood() {
        let f;
        do { f = randCell(); } while (snake.some(s => s.x === f.x && s.y === f.y));
        food = f;
    }

    function spawnEnemy() {
        if (enemies.length >= 4) return;
        let e;
        do { e = randCell(); } while (snake.some(s => s.x === e.x && s.y === e.y) || (food && food.x === e.x && food.y === e.y));
        enemies.push(e);
    }

    spawnFood();

    function draw() {
        ctx.fillStyle = '#0d1f10';
        ctx.fillRect(0, 0, W, H);
        ctx.fillStyle = '#1a3a1f';
        for (let x = 0; x < COLS; x++) {
            for (let y = 0; y < ROWS; y++) {
                ctx.fillRect(x * CELL + CELL/2 - 1, y * CELL + CELL/2 - 1, 2, 2);
            }
        }
        ctx.fillStyle = '#7ec87f';
        ctx.beginPath();
        ctx.arc(food.x * CELL + CELL/2, food.y * CELL + CELL/2, CELL/2 - 3, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#e57373';
        enemies.forEach(e => {
            ctx.beginPath();
            ctx.arc(e.x * CELL + CELL/2, e.y * CELL + CELL/2, CELL/2 - 3, 0, Math.PI * 2);
            ctx.fill();
        });
        snake.forEach((seg, i) => {
            const alpha = 1 - (i / snake.length) * 0.5;
            ctx.fillStyle = `rgba(61,107,58,${alpha})`;
            ctx.fillRect(seg.x * CELL + 1, seg.y * CELL + 1, CELL - 2, CELL - 2);
        });
        ctx.fillStyle = '#a8d5ba';
        ctx.fillRect(snake[0].x * CELL + 4, snake[0].y * CELL + 4, CELL - 8, CELL - 8);
    }

    function step() {
        if (!gameRunning) return;
        const head = { x: (snake[0].x + dir.x + COLS) % COLS, y: (snake[0].y + dir.y + ROWS) % ROWS };
        if (snake.some(s => s.x === head.x && s.y === head.y)) { gameOver(ctx, W, H); return; }
        if (enemies.some(e => e.x === head.x && e.y === head.y)) { gameOver(ctx, W, H); return; }
        snake.unshift(head);
        if (head.x === food.x && head.y === food.y) {
            score++;
            document.getElementById('gameScore').textContent = score;
            if (score > gameBest) { gameBest = score; document.getElementById('gameBest').textContent = gameBest; }
            spawnFood();
            if (score % 3 === 0) spawnEnemy();
        } else {
            snake.pop();
        }
        draw();
    }

    function gameOver(ctx, W, H) {
        gameRunning = false;
        clearInterval(gameLoop);
        ctx.fillStyle = 'rgba(0,0,0,0.6)';
        ctx.fillRect(0, 0, W, H);
        ctx.fillStyle = '#e57373';
        ctx.font = 'bold 28px Segoe UI';
        ctx.textAlign = 'center';
        ctx.fillText('Game Over', W/2, H/2 - 20);
        ctx.fillStyle = '#a8d5ba';
        ctx.font = '16px Segoe UI';
        ctx.fillText('Score: ' + score + '  |  Press Space to restart', W/2, H/2 + 16);
    }

    draw();
    clearInterval(gameLoop);
    gameLoop = setInterval(step, 130);

    document.onkeydown = (e) => {
        const map = {
            ArrowUp: {x:0,y:-1}, ArrowDown: {x:0,y:1}, ArrowLeft: {x:-1,y:0}, ArrowRight: {x:1,y:0},
            w: {x:0,y:-1}, s: {x:0,y:1}, a: {x:-1,y:0}, d: {x:1,y:0},
        };
        if (map[e.key]) {
            const nd = map[e.key];
            if (nd.x !== -dir.x || nd.y !== -dir.y) dir = nd;
            e.preventDefault();
        }
        if (e.key === ' ') {
            if (!gameRunning) startSnake();
            e.preventDefault();
        }
    };
}

function stopSnake() {
    clearInterval(gameLoop);
    gameLoop = null;
    document.onkeydown = null;
}
</script>
@endpush
