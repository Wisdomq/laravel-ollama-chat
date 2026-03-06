<!DOCTYPE html>
<html>
<head>
    <title>AI Chatbot</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f9f7; }
        .container { display: flex; height: 100vh; }
        .sidebar { width: 280px; background: #1e4d2b; color: white; padding: 20px; overflow-y: auto; border-right: 2px solid #3d6b3a; display: flex; flex-direction: column; }
        .sidebar h3 { color: #a8d5ba; margin-bottom: 20px; font-size: 20px; }
        .sessions-list { flex: 1; overflow-y: auto; }
        .session-item { padding: 12px; margin: 8px 0; background: #2d5a38; cursor: pointer; border-radius: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-left: 3px solid transparent; transition: all 0.3s; }
        .session-item:hover { background: #35694a; border-left-color: #7ec87f; }
        .session-item.active { background: #3d6b3a; border-left-color: #7ec87f; font-weight: bold; }
        .session-controls { margin-top: 10px; display: flex; gap: 5px; }
        .session-delete-btn { padding: 4px 8px; background: #5a3a3a; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; }
        .session-delete-btn:hover { background: #6d4747; }
        .main { flex: 1; display: flex; flex-direction: column; }
        .header { background: linear-gradient(135deg, #3d6b3a 0%, #2d5a38 100%); padding: 20px; color: white; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .header h2 { margin-bottom: 5px; }
        .session-id { font-size: 12px; opacity: 0.85; }
        .header-controls { margin-top: 10px; display: flex; gap: 10px; }
        .header-btn { padding: 8px 12px; background: white; color: #3d6b3a; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 12px; transition: all 0.3s; }
        .header-btn:hover { background: #f0f6f3; }
        .header-btn.danger { background: #8b5a5a; color: white; }
        .header-btn.danger:hover { background: #9e6a6a; }
        .chat-area { flex: 1; padding: 20px; overflow-y: auto; background: #f9fbfa; }
        .message { margin: 15px 0; line-height: 1.6; display: flex; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .message-content { padding: 12px 16px; border-radius: 8px; max-width: 70%; word-wrap: break-word; position: relative; }
        .user-msg { justify-content: flex-end; }
        .user-msg .message-content { background: #3d6b3a; color: white; }
        .bot-msg { justify-content: flex-start; }
        .bot-msg .message-content { background: #f0f6f3; color: #2d5a38; border: 1px solid #dbe8e2; }
        .message-meta { display: flex; align-items: center; gap: 10px; margin-top: 8px; font-size: 12px; }
        .confidence-badge { display: inline-flex; align-items: center; gap: 4px; background: #e8f4ed; color: #2d5a38; padding: 4px 8px; border-radius: 12px; font-weight: bold; }
        .confidence-high { background: #d4edda; color: #155724; }
        .confidence-medium { background: #fff3cd; color: #856404; }
        .confidence-low { background: #f8d7da; color: #721c24; }
        .thinking-text { display: block; font-size: 11px; color: #5a7d5f; font-style: italic; margin-top: 6px; padding-top: 6px; border-top: 1px solid #dbe8e2; }
        .typing-indicator { display: flex; align-items: center; gap: 4px; padding: 12px 16px; }
        .typing-dot { width: 8px; height: 8px; border-radius: 50%; background: #7ec87f; animation: typing 1.4s infinite; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing { 0%, 60%, 100% { opacity: 0.3; } 30% { opacity: 1; } }
        .message-actions { display: flex; gap: 5px; margin-top: 5px; opacity: 0; transition: opacity 0.3s; }
        .bot-msg:hover .message-actions { opacity: 1; }
        .copy-btn { padding: 4px 8px; background: #7ec87f; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px; }
        .copy-btn:hover { background: #6db86e; }
        .copy-btn.copied { background: #4a9d6f; }
        .input-area { display: flex; gap: 10px; padding: 20px; border-top: 2px solid #dbe8e2; background: #f9fbfa; }
        input[type="text"] { flex: 1; padding: 12px; border: 2px solid #dbe8e2; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; background: white; }
        input[type="text"]:focus { outline: none; border-color: #7ec87f; box-shadow: 0 0 0 2px rgba(126, 200, 127, 0.1); }
        .send-btn { padding: 12px 24px; background: #3d6b3a; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: all 0.3s; }
        .send-btn:hover { background: #2d5a38; transform: scale(1.02); }
        .send-btn:disabled { background: #b8d4ba; cursor: not-allowed; transform: scale(1); }
        .stop-btn { padding: 12px 24px; background: #a09070; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; display: none; }
        .stop-btn:hover { background: #8b7960; }
        .error-msg { background: #e8f4ed; color: #2d5a38; padding: 12px 16px; border-radius: 6px; border-left: 4px solid #7ec87f; }
        .new-session-btn { width: 100%; padding: 12px; background: #3d6b3a; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; margin-bottom: 20px; transition: all 0.3s; }
        .new-session-btn:hover { background: #2d5a38; }
        .sidebar-footer { margin-top: auto; padding-top: 10px; border-top: 1px solid #333; font-size: 12px; color: #999; }
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 3px solid #dc3545; }
            .message-content { max-width: 85%; }
            .header-controls { flex-wrap: wrap; }
        }
        @media (max-width: 480px) {
            .message-content { max-width: 95%; }
            input[type="text"] { font-size: 16px; } /* Prevent zoom on iOS */
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div>
                <h3>💬 AI Chat</h3>
                <button class="new-session-btn" onclick="initNewSession()">+ New Chat</button>
                <div class="sessions-list" id="sessionsList"></div>
            </div>
            <div class="sidebar-footer">
                Mistral AI<br>
                open-mistral-7b
            </div>
        </div>
        <div class="main">
            <div class="header">
                <h2>Conversation</h2>
                <div class="session-id">Session: <span id="currentSessionId">loading...</span></div>
                <div class="header-controls">
                    <button class="header-btn" onclick="copySessionId()">📋 Copy ID</button>
                    <button class="header-btn" onclick="clearSession()">🗑️ Clear Chat</button>
                    <button class="header-btn danger" onclick="deleteSession()">❌ Delete</button>
                </div>
            </div>
            <div class="chat-area" id="chatbox"></div>
            <div class="input-area">
                <input type="text" id="message" placeholder="Type your message..." onkeypress="handleKeyPress(event)" autocomplete="off">
                <button class="send-btn" id="sendButton" onclick="sendMessage()">Send</button>
                <button class="stop-btn" id="stopButton" onclick="stopStream()">Stop</button>
            </div>
        </div>
    </div>

    <script>
        async function uploadAttachment(event) {
            const fileInput = document.getElementById('fileInput');
            if (!fileInput.files.length) return;
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('_token', '{{ csrf_token() }}');
            try {
                const response = await fetch('/chat/upload', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.attachment_path) {
                    alert('File uploaded successfully!');
                    fileInput.value = '';
                } else {
                    alert(result.error || 'Upload failed');
                }
            } catch (err) {
                alert('Upload error');
            }
        }
        let currentSessionId = null;
        let streamAbortController = null;
        let isStreaming = false;

        // Initialize session on page load
        async function initializeApp() {
            try {
                const response = await fetch('/session/init', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const data = await response.json();
                currentSessionId = data.session_id;
                document.getElementById('currentSessionId').textContent = currentSessionId.substring(0, 8);
                await loadSessions();
                await loadSessionMessages();
            } catch (error) {
                showError('Failed to initialize app: ' + error.message);
            }
        }

        async function initNewSession() {
            try {
                const response = await fetch('/session/init', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const data = await response.json();
                currentSessionId = data.session_id;
                document.getElementById('currentSessionId').textContent = currentSessionId.substring(0, 8);
                document.getElementById('chatbox').innerHTML = '';
                await loadSessions();
            } catch (error) {
                showError('Failed to create new session: ' + error.message);
            }
        }

        async function switchSession(sessionId) {
            try {
                const response = await fetch('/session/switch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ session_id: sessionId })
                });
                const data = await response.json();
                currentSessionId = data.session_id;
                document.getElementById('currentSessionId').textContent = currentSessionId.substring(0, 8);
                await loadSessionMessages();
                await loadSessions();
            } catch (error) {
                showError('Failed to switch session: ' + error.message);
            }
        }

        async function loadSessions() {
            try {
                const response = await fetch('/sessions');
                const data = await response.json();
                const listEl = document.getElementById('sessionsList');
                listEl.innerHTML = '';

                data.sessions.forEach(session => {
                    const div = document.createElement('div');
                    div.className = 'session-item' + (session.session_id === currentSessionId ? ' active' : '');
                    div.onclick = () => switchSession(session.session_id);
                    const preview = session.last_message ? session.last_message.substring(0, 30) : 'Empty chat';
                    div.textContent = preview + '...';
                    listEl.appendChild(div);
                });
            } catch (error) {
                console.error('Failed to load sessions:', error);
            }
        }

        function createBotMessageElement(content, confidence = null, thinking = null) {
            const botDiv = document.createElement('div');
            botDiv.className = 'message bot-msg';
            
            const botContentDiv = document.createElement('div');
            botContentDiv.className = 'message-content';
            botContentDiv.textContent = content;
            botDiv.appendChild(botContentDiv);
            
            // Add metadata if available
            if (confidence !== null || thinking !== null) {
                const metaDiv = document.createElement('div');
                metaDiv.className = 'message-meta';
                
                if (confidence !== null) {
                    const confBadge = document.createElement('div');
                    confBadge.className = 'confidence-badge';
                    if (confidence >= 0.8) {
                        confBadge.classList.add('confidence-high');
                        confBadge.innerHTML = '🎯 High Confidence (' + (confidence * 100).toFixed(0) + '%)';
                    } else if (confidence >= 0.5) {
                        confBadge.classList.add('confidence-medium');
                        confBadge.innerHTML = '⚠️ Medium Confidence (' + (confidence * 100).toFixed(0) + '%)';
                    } else {
                        confBadge.classList.add('confidence-low');
                        confBadge.innerHTML = '❓ Low Confidence (' + (confidence * 100).toFixed(0) + '%)';
                    }
                    metaDiv.appendChild(confBadge);
                }
                
                botDiv.appendChild(metaDiv);
            }
            
            if (thinking) {
                const thinkingDiv = document.createElement('div');
                thinkingDiv.className = 'thinking-text';
                thinkingDiv.textContent = '💭 ' + thinking;
                botDiv.appendChild(thinkingDiv);
            }
            
            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'message-actions';
            const copyBtn = document.createElement('button');
            copyBtn.className = 'copy-btn';
            copyBtn.textContent = 'Copy';
            copyBtn.onclick = () => copyToClipboard(content, copyBtn);
            actionsDiv.appendChild(copyBtn);
            botDiv.appendChild(actionsDiv);
            
            return botDiv;
        }

        async function loadSessionMessages() {
            try {
                const response = await fetch('/session');
                if (!response.ok) return;
                const data = await response.json();
                const chatbox = document.getElementById('chatbox');
                chatbox.innerHTML = '';

                data.messages.forEach(msg => {
                    const userDiv = document.createElement('div');
                    userDiv.className = 'message user-msg';
                    const contentDiv = document.createElement('div');
                    contentDiv.className = 'message-content';
                    contentDiv.textContent = msg.user_message;
                    userDiv.appendChild(contentDiv);

                    // Minimalistic attachment display
                    if (msg.meta && msg.meta.attachment) {
                        const attachDiv = document.createElement('div');
                        attachDiv.className = 'message-meta';
                        const link = document.createElement('a');
                        link.href = '/storage/' + msg.meta.attachment;
                        link.target = '_blank';
                        link.textContent = '[Attachment]';
                        link.style = 'font-size:12px;color:#2d5a38;text-decoration:underline;margin-left:8px;';
                        attachDiv.appendChild(link);
                        userDiv.appendChild(attachDiv);
                    }
                    chatbox.appendChild(userDiv);

                    const botDiv = createBotMessageElement(msg.bot_reply, null, null);
                    chatbox.appendChild(botDiv);
                });

                chatbox.scrollTop = chatbox.scrollHeight;
            } catch (error) {
                console.error('Failed to load messages:', error);
            }
        }

        async function sendMessage() {
            const messageInput = document.getElementById('message');
            const message = messageInput.value.trim();
            if (!message || isStreaming) return;

            const chatbox = document.getElementById('chatbox');
            const userDiv = document.createElement('div');
            userDiv.className = 'message user-msg';
            const userContentDiv = document.createElement('div');
            userContentDiv.className = 'message-content';
            userContentDiv.textContent = message;
            userDiv.appendChild(userContentDiv);
            chatbox.appendChild(userDiv);

            messageInput.value = '';
            chatbox.scrollTop = chatbox.scrollHeight;

            try {
                isStreaming = true;
                document.getElementById('sendButton').disabled = true;
                document.getElementById('stopButton').style.display = 'inline-block';

                // Create typing indicator
                const typingDiv = document.createElement('div');
                typingDiv.className = 'message bot-msg';
                typingDiv.id = 'typingIndicator';
                const typingContent = document.createElement('div');
                typingContent.className = 'typing-indicator';
                for (let i = 0; i < 3; i++) {
                    const dot = document.createElement('div');
                    dot.className = 'typing-dot';
                    typingContent.appendChild(dot);
                }
                typingDiv.appendChild(typingContent);
                chatbox.appendChild(typingDiv);
                chatbox.scrollTop = chatbox.scrollHeight;

                // Create abort controller for this stream
                streamAbortController = new AbortController();

                const response = await fetch('/chat/stream', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ message }),
                    signal: streamAbortController.signal
                });

                // Remove typing indicator
                const typingEl = document.getElementById('typingIndicator');
                if (typingEl) typingEl.remove();

                if (!response.ok) {
                    showError('Server error: HTTP ' + response.status);
                    isStreaming = false;
                    document.getElementById('sendButton').disabled = false;
                    document.getElementById('stopButton').style.display = 'none';
                    return;
                }

                // Create bot message div for streaming
                const botDiv = document.createElement('div');
                botDiv.className = 'message bot-msg';
                const botContentDiv = document.createElement('div');
                botContentDiv.className = 'message-content';
                botDiv.appendChild(botContentDiv);
                
                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'message-actions';
                const copyBtn = document.createElement('button');
                copyBtn.className = 'copy-btn';
                copyBtn.textContent = 'Copy';
                actionsDiv.appendChild(copyBtn);
                botDiv.appendChild(actionsDiv);
                
                chatbox.appendChild(botDiv);

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let fullResponse = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value);
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || '';

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const jsonStr = line.substring(6);
                            try {
                                const data = JSON.parse(jsonStr);

                                if (data.chunk) {
                                    fullResponse += data.chunk;
                                    botContentDiv.textContent = fullResponse;
                                    chatbox.scrollTop = chatbox.scrollHeight;
                                }

                                if (data.error) {
                                    showError(data.error);
                                }
                                if (data.done) {
                                    isStreaming = false;
                                    document.getElementById('sendButton').disabled = false;
                                    document.getElementById('stopButton').style.display = 'none';
                                }
                            } catch (err) {
                                // Ignore JSON parse errors
                            }
                        }
                    }
                }
            } catch (error) {
                showError('Failed to send message: ' + error.message);
                isStreaming = false;
                document.getElementById('sendButton').disabled = false;
                document.getElementById('stopButton').style.display = 'none';
            }
        }

        function stopStream() {
            if (streamAbortController) {
                streamAbortController.abort();
                isStreaming = false;
                document.getElementById('sendButton').disabled = false;
                document.getElementById('stopButton').style.display = 'none';
                const typingEl = document.getElementById('typingIndicator');
                if (typingEl) typingEl.remove();
            }
        }

        function copyToClipboard(text, button) {
            navigator.clipboard.writeText(text).then(() => {
                const originalText = button.textContent;
                button.textContent = '✓ Copied';
                button.classList.add('copied');
                setTimeout(() => {
                    button.textContent = originalText;
                    button.classList.remove('copied');
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                showError('Failed to copy to clipboard');
            });
        }

        function copySessionId() {
            copyToClipboard(currentSessionId, { textContent: '📋 Copy ID', classList: { add: () => {}, remove: () => {} } });
            setTimeout(() => alert('Session ID copied: ' + currentSessionId), 100);
        }

        async function clearSession() {
            if (!confirm('Are you sure you want to clear this conversation? This cannot be undone.')) return;
            
            document.getElementById('chatbox').innerHTML = '';
            showError('Conversation cleared');
        }

        async function deleteSession() {
            if (!confirm('Are you sure you want to delete this session? This cannot be undone.')) return;
            
            try {
                // Delete current session from database
                await fetch('/session/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ session_id: currentSessionId })
                });

                // Create a new session
                await initNewSession();
                showError('✓ Session deleted');
            } catch (error) {
                showError('Failed to delete session: ' + error.message);
            }
        }

        function showError(message) {
            const chatbox = document.getElementById('chatbox');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'message error-msg';
            errorDiv.textContent = message;
            chatbox.appendChild(errorDiv);
            chatbox.scrollTop = chatbox.scrollHeight;
        }

        function handleKeyPress(event) {
            if (event.key === 'Enter' && !isStreaming) {
                sendMessage();
            }
        }

        // Initialize on load
        window.addEventListener('load', initializeApp);
        window.sendMessage = sendMessage;
        window.handleKeyPress = handleKeyPress;
    </script>
</body>
</html>