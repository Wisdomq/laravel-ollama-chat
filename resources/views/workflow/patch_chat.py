path_ctrl = '/home/wizmboya/Projects/chatbotapp/app/Http/Controllers/ChatController.php'
path_view = '/home/wizmboya/Projects/chatbotapp/resources/views/chat.blade.php'

# ── Patch ChatController ──────────────────────────────────────────────────
with open(path_ctrl, 'r') as f:
    ctrl = f.read()

# In chatStream: read attachment_path from JSON body and inject into streamPrompt
old = """        $userMessage = $request->input('message');
        \\Log::info("[ChatController STREAM] Session: {$sessionId} | Message: {$userMessage}");

        $intent       = $this->classifyIntent($userMessage);
        $skillUsed    = null;
        $newTool      = null;
        $source       = 'llm';
        $streamPrompt = $userMessage;"""

new = """        $userMessage    = $request->input('message');
        $attachmentPath = $request->input('attachment_path');
        \\Log::info("[ChatController STREAM] Session: {$sessionId} | Message: {$userMessage}");

        $intent       = $this->classifyIntent($userMessage);
        $skillUsed    = null;
        $newTool      = null;
        $source       = 'llm';

        // Build base prompt — append attachment context if a file was uploaded
        $attachmentContext = '';
        if ($attachmentPath) {
            $fileUrl           = asset('storage/' . $attachmentPath);
            $attachmentContext = "\\n\\n[The user has attached a file for context: {$fileUrl}]";
        }
        $streamPrompt = $userMessage . $attachmentContext;"""

ctrl = ctrl.replace(old, new, 1)

# Also store attachment in meta for chatStream
old = """                    Chat::create([
                        'session_id'   => $sessionId,
                        'user_message' => $userMessage,
                        'bot_reply'    => trim($fullResponse),
                        'meta'         => array_filter([
                            'intent'     => $intent,
                            'source'     => $source,
                            'skill_used' => $skillUsed,
                            'new_tool'   => $newTool,
                        ]),
                    ]);"""

new = """                    Chat::create([
                        'session_id'   => $sessionId,
                        'user_message' => $userMessage,
                        'bot_reply'    => trim($fullResponse),
                        'meta'         => array_filter([
                            'intent'     => $intent,
                            'source'     => $source,
                            'skill_used' => $skillUsed,
                            'new_tool'   => $newTool,
                            'attachment' => $attachmentPath,
                        ]),
                    ]);"""

ctrl = ctrl.replace(old, new, 1)

with open(path_ctrl, 'w') as f:
    f.write(ctrl)

print("ChatController patched OK")

# ── Patch chat.blade.php — remove [Attachment] link from history ──────────
with open(path_view, 'r') as f:
    view = f.read()

old = """                    // Minimalistic attachment display
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
                    }"""

new = """                    // Show attachment as inline thumbnail or filename badge
                    if (msg.meta && msg.meta.attachment) {
                        const att = msg.meta.attachment;
                        const isImage = /\\.(jpg|jpeg|png|webp|gif)$/i.test(att);
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
                    }"""

# Handle both possible whitespace variants from the file
if old in view:
    view = view.replace(old, new, 1)
    print("chat.blade.php attachment block patched OK")
else:
    # Try stripping extra spaces
    import re
    view = re.sub(
        r'// Minimalistic attachment display\s+if \(msg\.meta && msg\.meta\.attachment\) \{.*?userDiv\.appendChild\(attachDiv\);\s+\}',
        new.strip(),
        view,
        count=1,
        flags=re.DOTALL
    )
    print("chat.blade.php attachment block patched via regex")

with open(path_view, 'w') as f:
    f.write(view)

print("All patches done")
