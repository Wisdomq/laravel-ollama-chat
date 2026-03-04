<!DOCTYPE html>
<html>
<head>
    <title>Local LLM Chatbot</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { display: flex; height: 100vh; }
        .sidebar { width: 250px; background: #2c3e50; color: white; padding: 20px; overflow-y: auto; }
        .sessions-list { margin-top: 20px; }
        .session-item { padding: 10px; margin: 5px 0; background: #34495e; cursor: pointer; border-radius: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .session-item:hover { background: #3d5a7a; }
        .session-item.active { background: #3498db; }
        .main { flex: 1; display: flex; flex-direction: column; }
        .header { background: white; padding: 15px 20px; border-bottom: 1px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .chat-area { flex: 1; padding: 20px; overflow-y: auto; background: white; }
        .message { margin: 15px 0; line-height: 1.5; }
        .user-msg { background: #e3f2fd; padding: 10px 15px; border-radius: 8px; margin-left: 20%; }
        .bot-msg { background: #f5f5f5; padding: 10px 15px; border-radius: 8px; margin-right: 20%; border-left: 4px solid #2196f3; }
        .input-area { display: flex; gap: 10px; padding: 20px; border-top: 1px solid #ddd; background: white; }
        input[type="text"] { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        button { padding: 12px 20px; background: #2196f3; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #1976d2; }
        .session-id { font-size: 12px; color: #666; margin-bottom: 10px; }
        .new-session-btn { width: 100%; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h3>Ollama Chatbot</h3>
            <button class="new-session-btn" onclick="initNewSession()">+ New Session</button>
            <div class="sessions-list" id="sessionsList"></div>
        </div>
        <div class="main">
            <div class="header">
                <div class="session-id">Session: <span id="currentSessionId">loading...</span></div>
                <h2>Chat</h2>
            </div>
            <div class="chat-area" id="chatbox"></div>
            <div class="input-area">
                <input type="text" id="message" placeholder="Type message..." onkeypress="handleKeyPress(event)">
                <button onclick="sendMessage()">Send</button>
                <button id="stopButton" style="display:none; background:#ff6b6b;" onclick="stopStream()">Stop</button>
            </div>
        </div>
    </div>

    <script>
        let currentSessionId = null;
        let streamAbortController = null;

        // Initialize session on page load
        async function initializeApp() {
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
        }

        async function initNewSession() {
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
        }

        async function switchSession(sessionId) {
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
        }

        async function loadSessions() {
            const response = await fetch('/sessions');
            const data = await response.json();
            const listEl = document.getElementById('sessionsList');
            listEl.innerHTML = '';

            data.sessions.forEach(session => {
                const div = document.createElement('div');
                div.className = 'session-item' + (session.session_id === currentSessionId ? ' active' : '');
                div.onclick = () => switchSession(session.session_id);
                const preview = session.last_message ? session.last_message.substring(0, 30) : 'Empty session';
                div.textContent = preview + '...';
                listEl.appendChild(div);
            });
        }

        async function loadSessionMessages() {
            const response = await fetch('/session');
            if (!response.ok) return;
            const data = await response.json();
            const chatbox = document.getElementById('chatbox');
            chatbox.innerHTML = '';

            data.messages.forEach(msg => {
                const userDiv = document.createElement('div');
                userDiv.className = 'message user-msg';
                userDiv.innerHTML = '<strong>You:</strong> ' + escapeHtml(msg.user_message);
                chatbox.appendChild(userDiv);

                const botDiv = document.createElement('div');
                botDiv.className = 'message bot-msg';
                botDiv.innerHTML = '<strong>Bot:</strong> ' + escapeHtml(msg.bot_reply);
                chatbox.appendChild(botDiv);
            });

            chatbox.scrollTop = chatbox.scrollHeight;
        }

        async function sendMessage() {
            const messageInput = document.getElementById('message');
            const message = messageInput.value.trim();

            if (!message) return;

            // Add user message to chat
            const chatbox = document.getElementById('chatbox');
            const userDiv = document.createElement('div');
            userDiv.className = 'message user-msg';
            userDiv.innerHTML = '<strong>You:</strong> ' + escapeHtml(message);
            chatbox.appendChild(userDiv);

            messageInput.value = '';
            chatbox.scrollTop = chatbox.scrollHeight;

            try {
                // Create bot message div for streaming
                const botDiv = document.createElement('div');
                botDiv.className = 'message bot-msg';
                const responseSpan = document.createElement('span');
                botDiv.innerHTML = '<strong>Bot:</strong> ';
                botDiv.appendChild(responseSpan);
                chatbox.appendChild(botDiv);

                // Create abort controller for this stream
                streamAbortController = new AbortController();
                document.getElementById('stopButton').style.display = 'inline-block';

                const response = await fetch('/chat/stream', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ message }),
                    signal: streamAbortController.signal
                });

                if (!response.ok) {
                    responseSpan.textContent = 'Error: HTTP ' + response.status;
                    document.getElementById('stopButton').style.display = 'none';
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value);
                    const lines = buffer.split('\n');

                    // Keep the last incomplete line in the buffer
                    buffer = lines.pop() || '';

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const jsonStr = line.substring(6);
                            try {
                                const data = JSON.parse(jsonStr);

                                if (data.chunk) {
                                    responseSpan.textContent += data.chunk;
                                    chatbox.scrollTop = chatbox.scrollHeight;
                                }

                                if (data.done) {
                                    document.getElementById('stopButton').style.display = 'none';
                                    await loadSessions();
                                }

                                if (data.error) {
                                    responseSpan.textContent = 'Error: ' + data.error;
                                    document.getElementById('stopButton').style.display = 'none';
                                }
                            } catch (e) {
                                console.error('JSON parse error:', e, jsonStr);
                            }
                        }
                    }
                }
                document.getElementById('stopButton').style.display = 'none';
            } catch (error) {
                if (error.name !== 'AbortError') {
                    const chatbox = document.getElementById('chatbox');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'message bot-msg';
                    errorDiv.innerHTML = '<strong>Error:</strong> ' + escapeHtml(error.message);
                    chatbox.appendChild(errorDiv);
                    console.error('Chat error:', error);
                }
                document.getElementById('stopButton').style.display = 'none';
            }
        }

        function stopStream() {
            if (streamAbortController) {
                streamAbortController.abort();
                document.getElementById('stopButton').style.display = 'none';
            }
        }

        function handleKeyPress(event) {
            if (event.key === 'Enter') {
                sendMessage();
            }
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Initialize on load
        window.addEventListener('load', initializeApp);
    </script>
</body>
</html>