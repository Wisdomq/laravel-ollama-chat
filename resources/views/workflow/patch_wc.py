import re

path = '/home/wizmboya/Projects/chatbotapp/app/Http/Controllers/WorkflowController.php'
with open(path, 'r') as f:
    content = f.read()

# 1. Read chat_attachment_path after uploaded_file_path
old = "        $uploadedFilePath = $request->input('uploaded_file_path');\n\n        if (! $sessionId) {"
new = "        $uploadedFilePath = $request->input('uploaded_file_path');\n        $chatAttachmentPath = $request->input('chat_attachment_path');\n\n        if (! $sessionId) {"
content = content.replace(old, new, 1)

# 2. Pass chatAttachmentPath into the closure
old = "            function () use ($agent, $userMessage, $sessionId, $workflowId, $workflowType, $isFirstTurn, $uploadedFilePath) {"
new = "            function () use ($agent, $userMessage, $sessionId, $workflowId, $workflowType, $isFirstTurn, $uploadedFilePath, $chatAttachmentPath) {"
content = content.replace(old, new, 1)

# 3. Build the actual prompt with attachment context injected
old = "                    foreach ($agent->stream($userMessage, provider: 'ollama', model: 'mistral:7b') as $event) {"
new = """                    // Append chat attachment context to the prompt if provided
                    $promptWithContext = $userMessage;
                    if ($chatAttachmentPath) {
                        $fileUrl = asset('storage/' . $chatAttachmentPath);
                        $promptWithContext .= "\\n\\n[Reference file attached: {$fileUrl}]";
                    }

                    foreach ($agent->stream($promptWithContext, provider: 'ollama', model: 'mistral:7b') as $event) {"""
content = content.replace(old, new, 1)

with open(path, 'w') as f:
    f.write(content)

print("WorkflowController patched OK")
