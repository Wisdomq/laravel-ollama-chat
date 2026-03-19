@extends('layouts.app')

@section('title', 'Chat · AI Studio')

@push('styles')
<style>
    .chat-shell { display: flex; height: calc(100vh - 52px); }
    .chat-sidebar { width: 260px; min-width: 260px; }
    .chat-sidebar .new-session-btn { width: 100%; margin-bottom: 16px; }
    .sessions-list { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 6px; padding: 4px 0; }
    .session-item {
        padding: 15px 19px;
        background: var(--green-800);
        cursor: pointer;
        border-radius: var(--radius-md);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 13px;
        display: flex;              
        justify-content: space-between; 
        align-items: center;
        border-left: 3px solid transparent;
        transition: all var(--transition);
        color: #c8e6c9;
        margin-bottom: 8px;
    }
    .session-item:hover { background: var(--green-600); border-left-color: var(--green-400); }
    .session-item.active { background: var(--green-700); border-left-color: var(--green-400); font-weight: 600; color: white; }
    .chat-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .chat-topbar {
        background: linear-gradient(135deg, var(--green-700), var(--green-800));
        padding: 12px 18px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }
    .chat-topbar-left h2 { font-size: 16px; }
    .chat-topbar-left .session-id { font-size: 11px; opacity: 0.8; margin-top: 2px; }
    .chat-topbar-actions { display: flex; gap: 6px; }
    .topbar-btn { padding: 6px 12px; background: rgba(255,255,255,0.15); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; font-size: 12px; font-weight: 600; transition: background var(--transition); }
    .topbar-btn:hover { background: rgba(255,255,255,0.25); }
    .topbar-btn.danger { background: rgba(139,90,90,0.6); }
    .topbar-btn.danger:hover { background: rgba(139,90,90,0.9); }
    .message-meta-row { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
    .confidence-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
    .confidence-high   { background: #d4edda; color: #155724; }
    .confidence-medium { background: #fff3cd; color: #856404; }
    .confidence-low    { background: #f8d7da; color: #721c24; }
    .thinking-text { display: block; font-size: 11px; color: var(--green-muted); font-style: italic; margin-top: 6px; padding-top: 6px; border-top: 1px solid var(--green-100); }
    .message-actions { display: flex; gap: 4px; margin-top: 4px; opacity: 0; transition: opacity var(--transition); }
    .bot-msg:hover .message-actions { opacity: 1; }
    .copy-btn { padding: 3px 8px; background: var(--green-400); color: white; border: none; border-radius: var(--radius-sm); cursor: pointer; font-size: 11px; }
    .copy-btn:hover { background: #6db86e; }
    .copy-btn.copied { background: #4a9d6f; }
    .stop-btn { padding: 11px 18px; background: #a09070; color: white; border: none; border-radius: var(--radius-md); cursor: pointer; font-weight: 700; display: none; font-size: 13px; }
    .stop-btn:hover { background: #8b7960; }
</style>
@endpush

@section('content')
<div class="chat-shell">

    {{-- Sidebar --}}
    <div class="sidebar chat-sidebar">
        <button class="btn btn-primary new-session-btn" onclick="initNewSession()">+ New Chat</button>
        <div class="sessions-list" id="sessionsList"></div>
        <div class="sidebar-footer">Mistral AI · open-mistral-7b</div>
    </div>

    {{-- Main --}}
    <div class="chat-main">
        <div class="chat-topbar">
            <div class="chat-topbar-left">
                <h2>Let's Chat!</h2>
                <div class="session-id">Session: <span id="currentSessionId">loading...</span></div>
            </div>
            <div class="chat-topbar-actions">
                <button class="topbar-btn" onclick="copySessionId()">&#x1F4CB; Copy ID</button>
                <button class="topbar-btn" onclick="clearSession()">&#x1F5D1; Clear</button>
                <button class="topbar-btn danger" onclick="deleteSession()">&#x274C; Delete</button>
            </div>
        </div>

        <div class="chat-area" id="chatbox"></div>

        <div class="input-area">
            <div class="file-preview" id="filePreview">
                <span id="filePreviewName"></span>
                <button class="remove-file" onclick="removeAttachment()" title="Remove">&#x2715;</button>
            </div>
            <div class="input-row">
                <input type="file" id="fileInput" style="display:none" accept="image/*,video/*,audio/*,.pdf,.txt" onchange="onFileChosen(this)">
                <button class="attach-btn" id="attachBtn" onclick="document.getElementById('fileInput').click()" title="Attach file">&#x1F4CE;</button>
                <input type="text" class="input-text" id="message" placeholder="Type your message..." onkeypress="handleKeyPress(event)" autocomplete="off">
                <button class="btn btn-primary" id="sendButton" onclick="sendMessage()">Send</button>
                <button class="stop-btn" id="stopButton" onclick="stopStream()">Stop</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let pendingAttachmentPath = null;

    function onFileChosen(input) {
        if (!input.files.length) return;
        document.getElementById('filePreviewName').textContent = '📎 ' + input.files[0].name;
        document.getElementById('filePreview').classList.add('visible');
        document.getElementById('attachBtn').classList.add('has-file');
        pendingAttachmentPath = null;
    }

    function removeAttachment() {
        document.getElementById('fileInput').value = '';
        document.getElementById('filePreview').classList.remove('visible');
        document.getElementById('attachBtn').classList.remove('has-file');
        pendingAttachmentPath = null;
    }

    async function uploadPendingFile() {
        const input = document.getElementById('fileInput');
        if (!input.files.length) return null;
        const fd = new FormData();
        fd.append('file', input.files[0]);
        fd.append('_token', '{{ csrf_token() }}');
        const res = await fetch('/chat/upload', { method: 'POST', body: fd });
        if (!res.ok) throw new Error('Upload failed: HTTP ' + res.status);
        const data = await res.json();
        if (!data.attachment_path) throw new Error(data.error || 'Upload failed');
        return data.attachment_path;
    }

    let currentSessionId = null;
    let streamAbortController = null;
    let isStreaming = false;

    async function initializeApp() {
        try {
            const res  = await fetch('/session/init', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
            const data = await res.json();
            currentSessionId = data.session_id;
            document.getElementById('currentSessionId').textContent = currentSessionId.substring(0, 8);
            await loadSessions();
            await loadSessionMessages();
        } catch (e) { showError('Failed to initialize: ' + e.message); }
    }

    async function initNewSession() {
        try {
            const res  = await fetch('/session/init', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
            const data = await res.json();
            currentSessionId = data.session_id;
            document.getElementById('currentSessionId').textContent = currentSessionId.substring(0, 8);
            document.getElementById('chatbox').innerHTML = '';
            await loadSessions();
        } catch (e) { showError('Failed to create session: ' + e.message); }
    }

    async function switchSession(sessionId) {
        try {
            const res  = await fetch('/session/switch', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ session_id: sessionId }) });
            const data = await res.json();
            currentSessionId = data.session_id;
            document.getElementById('currentSessionId').textContent = currentSessionId.substring(0, 8);
            await loadSessionMessages();
            await loadSessions();
        } catch (e) { showError('Failed to switch session: ' + e.message); }
    }

    async function loadSessions() {
        try {
            const res  = await fetch('/sessions');
            const data = await res.json();
            const list = document.getElementById('sessionsList');
            list.innerHTML = '';
            data.sessions.forEach(s => {
                const div = document.createElement('div');
                div.className = 'session-item' + (s.session_id === currentSessionId ? ' active' : '');
                div.onclick   = () => switchSession(s.session_id);
                div.textContent = (s.last_message ? s.last_message.substring(0, 32) : 'Empty chat') + '...';
                list.appendChild(div);
            });
        } catch (e) { console.error('loadSessions:', e); }
    }

    function createBotMessageElement(content, confidence, thinking) {
        const wrap = document.createElement('div');
        wrap.className = 'message bot-msg';
        const body = document.createElement('div');
        body.className = 'message-content';
        body.textContent = content;
        wrap.appendChild(body);

        if (confidence !== null && confidence !== undefined) {
            const meta = document.createElement('div');
            meta.className = 'message-meta-row';
            const badge = document.createElement('div');
            badge.className = 'confidence-badge ' + (confidence >= 0.8 ? 'confidence-high' : confidence >= 0.5 ? 'confidence-medium' : 'confidence-low');
            badge.innerHTML = (confidence >= 0.8 ? '🎯' : confidence >= 0.5 ? '⚠️' : '❓') + ' ' + (confidence * 100).toFixed(0) + '%';
            meta.appendChild(badge);
            wrap.appendChild(meta);
        }
        if (thinking) {
            const t = document.createElement('div');
            t.className = 'thinking-text';
            t.textContent = '💭 ' + thinking;
            wrap.appendChild(t);
        }
        const actions = document.createElement('div');
        actions.className = 'message-actions';
        const copy = document.createElement('button');
        copy.className = 'copy-btn';
        copy.textContent = 'Copy';
        copy.onclick = () => copyToClipboard(content, copy);
        actions.appendChild(copy);
        wrap.appendChild(actions);
        return wrap;
    }

    async function loadSessionMessages() {
        try {
            const res  = await fetch('/session');
            if (!res.ok) return;
            const data = await res.json();
            const box  = document.getElementById('chatbox');
            box.innerHTML = '';
            data.messages.forEach(msg => {
                const userDiv = document.createElement('div');
                userDiv.className = 'message user-msg';
                const contentDiv = document.createElement('div');
                contentDiv.className = 'message-content';
                contentDiv.textContent = msg.user_message;
                userDiv.appendChild(contentDiv);

                if (msg.meta && msg.meta.attachment) {
                    const att = msg.meta.attachment;
                    const isImage = /\.(jpg|jpeg|png|webp|gif)$/i.test(att);
                    if (isImage) {
                        const img = document.createElement('img');
                        img.src = '/storage/' + att;
                        img.style = 'max-width:120px;max-height:80px;border-radius:4px;margin-top:6px;display:block;';
                        contentDiv.appendChild(img);
                    } else {
                        const badge = document.createElement('span');
                        badge.style = 'display:inline-block;font-size:11px;background:#d4edda;color:#2d5a38;padding:2px 8px;border-radius:10px;margin-top:4px;';
                        badge.textContent = '📎 ' + att.split('/').pop();
                        contentDiv.appendChild(badge);
                    }
                }
                box.appendChild(userDiv);
                box.appendChild(createBotMessageElement(msg.bot_reply, null, null));
            });
            box.scrollTop = box.scrollHeight;
        } catch (e) { console.error('loadSessionMessages:', e); }
    }

    async function sendMessage() {
        const input   = document.getElementById('message');
        const message = input.value.trim();
        if (!message || isStreaming) return;

        if (document.getElementById('fileInput').files.length) {
            try {
                pendingAttachmentPath = await uploadPendingFile();
            } catch (e) { showError('File upload failed: ' + e.message); return; }
        }

        const box = document.getElementById('chatbox');
        const userDiv = document.createElement('div');
        userDiv.className = 'message user-msg';
        const userContent = document.createElement('div');
        userContent.className = 'message-content';
        userContent.textContent = message;
        userDiv.appendChild(userContent);
        box.appendChild(userDiv);
        input.value = '';
        box.scrollTop = box.scrollHeight;

        isStreaming = true;
        document.getElementById('sendButton').disabled = true;
        document.getElementById('stopButton').style.display = 'inline-flex';

        const typingDiv = document.createElement('div');
        typingDiv.className = 'message bot-msg';
        typingDiv.id = 'typingIndicator';
        typingDiv.innerHTML = '<div class="typing-indicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>';
        box.appendChild(typingDiv);
        box.scrollTop = box.scrollHeight;

        streamAbortController = new AbortController();

        try {
            const response = await fetch('/chat/stream', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ message, attachment_path: pendingAttachmentPath }),
                signal: streamAbortController.signal,
            });

            document.getElementById('typingIndicator')?.remove();

            if (!response.ok) { showError('Server error: HTTP ' + response.status); return; }

            const botDiv = document.createElement('div');
            botDiv.className = 'message bot-msg';
            const botContent = document.createElement('div');
            botContent.className = 'message-content';
            botDiv.appendChild(botContent);
            const actions = document.createElement('div');
            actions.className = 'message-actions';
            const copy = document.createElement('button');
            copy.className = 'copy-btn';
            copy.textContent = 'Copy';
            actions.appendChild(copy);
            botDiv.appendChild(actions);
            box.appendChild(botDiv);

            const reader  = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '', fullResponse = '';

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
                        if (data.chunk) { fullResponse += data.chunk; botContent.textContent = fullResponse; box.scrollTop = box.scrollHeight; }
                        if (data.error) showError(data.error);
                        if (data.done) {
                            isStreaming = false;
                            document.getElementById('sendButton').disabled = false;
                            document.getElementById('stopButton').style.display = 'none';
                            copy.onclick = () => copyToClipboard(fullResponse, copy
);
                            removeAttachment();
                        }
                    } catch (_) {}
                }
            }
        } catch (e) {
            if (e.name !== 'AbortError') showError('Failed to send: ' + e.message);
            isStreaming = false;
            document.getElementById('sendButton').disabled = false;
            document.getElementById('stopButton').style.display = 'none';
        }
    }

    function stopStream() {
        streamAbortController?.abort();
        isStreaming = false;
        document.getElementById('sendButton').disabled = false;
        document.getElementById('stopButton').style.display = 'none';
        document.getElementById('typingIndicator')?.remove();
    }

    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            btn.textContent = '✓ Copied';
            btn.classList.add('copied');
            setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
        });
    }

    function copySessionId() {
        navigator.clipboard.writeText(currentSessionId).then(() => alert('Session ID copied: ' + currentSessionId));
    }

    function clearSession() {
        if (!confirm('Clear this conversation?')) return;
        document.getElementById('chatbox').innerHTML = '';
    }

    async function deleteSession() {
        if (!confirm('Delete this session? This cannot be undone.')) return;
        try {
            await fetch('/session/delete', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ session_id: currentSessionId }) });
            await initNewSession();
            showError('✓ Session deleted');
        } catch (e) { showError('Failed to delete: ' + e.message); }
    }

    function showError(msg) {
        const box = document.getElementById('chatbox');
        const div = document.createElement('div');
        div.className = 'message system-msg';
        div.innerHTML = '<div class="message-content">' + msg + '</div>';
        box.appendChild(div);
        box.scrollTop = box.scrollHeight;
    }

    function handleKeyPress(e) { if (e.key === 'Enter' && !isStreaming) sendMessage(); }

    window.addEventListener('load', initializeApp);
</script>
@endpush
