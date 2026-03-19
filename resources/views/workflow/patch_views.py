import shutil, os

base = '/home/wizmboya/Projects/chatbotapp/resources/views'

# 1. Replace chat.blade.php with the new version
shutil.copy(base + '/chat_new.blade.php', base + '/chat.blade.php')
os.remove(base + '/chat_new.blade.php')
print("chat.blade.php replaced")

# 2. Read create.blade.php and wrap it in the layout
with open(base + '/workflow/create.blade.php', 'r') as f:
    create = f.read()

# Strip the outer <!DOCTYPE...> shell — keep only what's inside <body>
import re

# Extract everything between <body> and </body>
body_match = re.search(r'<body>(.*)</body>', create, re.DOTALL)
body_content = body_match.group(1).strip() if body_match else create

# Extract <style> blocks from <head>
style_matches = re.findall(r'<style>(.*?)</style>', create, re.DOTALL)
styles = '\n'.join(style_matches)

# Extract <script> blocks
script_matches = re.findall(r'<script>(.*?)</script>', create, re.DOTALL)
scripts = '\n'.join(script_matches)

# Remove inline <style> and <script> tags from body_content so we don't duplicate
body_clean = re.sub(r'<script>.*?</script>', '', body_content, flags=re.DOTALL)

new_create = """@extends('layouts.app')

@section('title', 'Create Workflow – AI Studio')

@push('styles')
<style>
""" + styles + """
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
""" + body_clean + """
</div>
@endsection

@push('scripts')
""" + '\n'.join(f'<script>{s}</script>' for s in script_matches) + """
@endpush
"""

with open(base + '/workflow/create.blade.php', 'w') as f:
    f.write(new_create)

print("create.blade.php wrapped in layout")
print("Done")
