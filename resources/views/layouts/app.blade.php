<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'AI Studio')</title>
    <style>
        /* ── Design tokens ───────────────────────────────────────────────── */
        :root {
            --green-900: #1e4d2b;
            --green-800: #2d5a38;
            --green-700: #3d6b3a;
            --green-600: #35694a;
            --green-400: #7ec87f;
            --green-200: #b8d4ba;
            --green-100: #dbe8e2;
            --green-50:  #f0f6f3;
            --green-25:  #f5f9f7;
            --green-muted: #5a7d5f;
            --red-soft:  #e57373;
            --yellow-soft: #f0d070;
            --bg: var(--green-25);
            --radius-sm: 4px;
            --radius-md: 6px;
            --radius-lg: 10px;
            --radius-xl: 12px;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 16px rgba(61,107,58,0.10);
            --shadow-lg: 0 8px 24px rgba(61,107,58,0.12);
            --font: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --transition: 0.2s ease;
        }

        /* ── Reset ───────────────────────────────────────────────────────── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font); background: var(--bg); color: #1a1a1a; }
        a { text-decoration: none; color: inherit; }

        /* ── Top nav ─────────────────────────────────────────────────────── */
        .app-nav {
            background: var(--green-900);
            padding: 0 24px;
            height: 52px;
            display: flex;
            align-items: center;
            gap: 0;
            position: sticky;
            top: 0;
            z-index: 200;
            box-shadow: 0 2px 8px rgba(0,0,0,0.18);
        }
        .app-nav .nav-brand {
            font-size: 16px;
            font-weight: 700;
            color: var(--green-400);
            margin-right: 28px;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .app-nav .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
            flex: 1;
        }
        .app-nav .nav-link {
            padding: 6px 14px;
            border-radius: var(--radius-md);
            font-size: 13px;
            color: #a8d5ba;
            transition: background var(--transition), color var(--transition);
            white-space: nowrap;
        }
        .app-nav .nav-link:hover { background: var(--green-800); color: white; }
        .app-nav .nav-link.active { background: var(--green-700); color: white; font-weight: 600; }

        /* ── Shared sidebar ──────────────────────────────────────────────── */
        .sidebar {
            width: 260px;
            min-width: 260px;
            background: var(--green-900);
            color: white;
            padding: 20px 16px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            overflow-y: auto;
        }
        .sidebar h3 { color: var(--green-400); font-size: 16px; }
        .sidebar-footer { margin-top: auto; font-size: 11px; color: var(--green-muted); padding-top: 12px; border-top: 1px solid var(--green-800); }

        /* ── Shared header strip ─────────────────────────────────────────── */
        .page-header {
            background: linear-gradient(135deg, var(--green-700) 0%, var(--green-800) 100%);
            padding: 14px 20px;
            color: white;
        }
        .page-header h2 { font-size: 17px; margin-bottom: 2px; }
        .page-header p  { font-size: 12px; opacity: 0.8; }

        /* ── Buttons ─────────────────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all var(--transition);
            white-space: nowrap;
        }
        .btn-primary   { background: var(--green-700); color: white; }
        .btn-primary:hover { background: var(--green-800); }
        .btn-secondary { background: white; color: var(--green-700); border: 2px solid var(--green-700); }
        .btn-secondary:hover { background: var(--green-50); }
        .btn-ghost     { background: var(--green-50); color: var(--green-700); border: 2px solid var(--green-100); }
        .btn-ghost:hover { background: var(--green-100); border-color: var(--green-400); }
        .btn-danger    { background: #8b5a5a; color: white; }
        .btn-danger:hover { background: #9e6a6a; }
        .btn:disabled  { background: var(--green-200); color: white; cursor: not-allowed; }

        /* ── Cards ───────────────────────────────────────────────────────── */
        .card {
            background: white;
            border: 1px solid var(--green-100);
            border-radius: var(--radius-lg);
            padding: 20px;
        }
        .card-bordered { border: 2px solid var(--green-100); }

        /* ── Chat messages ───────────────────────────────────────────────── */
        .chat-area { flex: 1; padding: 20px; overflow-y: auto; background: var(--green-25); }
        .message { margin: 12px 0; display: flex; animation: slideIn 0.25s ease-out; }
        @keyframes slideIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
        .message-content {
            padding: 11px 15px;
            border-radius: var(--radius-lg);
            max-width: 75%;
            word-wrap: break-word;
            line-height: 1.6;
            font-size: 14px;
        }
        .user-msg { justify-content: flex-end; }
        .user-msg .message-content { background: var(--green-700); color: white; }
        .bot-msg  { justify-content: flex-start; }
        .bot-msg  .message-content { background: var(--green-50); color: var(--green-800); border: 1px solid var(--green-100); }
        .system-msg .message-content { background: #fff8e8; border: 1px solid var(--yellow-soft); color: #6b5a00; }

        /* ── Typing indicator ────────────────────────────────────────────── */
        .typing-indicator { display: flex; align-items: center; gap: 4px; padding: 11px 15px; }
        .typing-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green-400); animation: typing 1.4s infinite; }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing { 0%,60%,100%{opacity:0.3;} 30%{opacity:1;} }

        /* ── Input area ──────────────────────────────────────────────────── */
        .input-area { padding: 14px 18px; border-top: 2px solid var(--green-100); background: var(--green-25); }
        .input-row  { display: flex; gap: 8px; align-items: center; }
        .input-text {
            flex: 1;
            padding: 11px 14px;
            border: 2px solid var(--green-100);
            border-radius: var(--radius-md);
            font-size: 14px;
            background: white;
            font-family: var(--font);
            transition: border-color var(--transition);
        }
        .input-text:focus { outline: none; border-color: var(--green-400); }
        .attach-btn {
            padding: 11px 13px;
            background: var(--green-50);
            color: var(--green-700);
            border: 2px solid var(--green-100);
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 16px;
            transition: all var(--transition);
            flex-shrink: 0;
        }
        .attach-btn:hover { background: var(--green-100); border-color: var(--green-400); }
        .attach-btn.has-file { background: #d4edda; border-color: var(--green-400); }

        /* ── File preview strip ──────────────────────────────────────────── */
        .file-preview {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 5px 10px;
            background: var(--green-50);
            border-radius: var(--radius-md);
            font-size: 12px;
            color: var(--green-800);
            border: 1px solid var(--green-100);
            margin-bottom: 6px;
        }
        .file-preview.visible { display: flex; }
        .file-preview .remove-file { cursor: pointer; color: var(--red-soft); font-weight: bold; background: none; border: none; font-size: 14px; padding: 0 2px; }

        /* ── Badge ───────────────────────────────────────────────────────── */
        .badge {
            display: inline-block;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .badge-green  { background: var(--green-50);  color: var(--green-800); border: 1px solid var(--green-100); }
        .badge-muted  { background: #f0f0f0; color: #666; }
        .badge-soon   { background: var(--green-muted); color: #c8e6c9; }

        /* ── Status dot ──────────────────────────────────────────────────── */
        .status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .status-dot.online  { background: var(--green-400); }
        .status-dot.offline { background: var(--red-soft); }

        /* ── Popup toast ─────────────────────────────────────────────────── */
        .popup {
            display: none;
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--green-900);
            color: white;
            padding: 16px 20px;
            border-radius: var(--radius-lg);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1000;
            max-width: 320px;
            animation: popIn 0.35s ease-out;
        }
        .popup.show { display: block; }
        @keyframes popIn { from{opacity:0;transform:translateY(16px);} to{opacity:1;transform:translateY(0);} }
        .popup h4 { margin-bottom: 5px; font-size: 14px; }
        .popup p  { font-size: 13px; opacity: 0.85; margin-bottom: 10px; }

        /* ── Lightbox ────────────────────────────────────────────────────── */
        .lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 1000; align-items: center; justify-content: center; }
        .lightbox.open { display: flex; }
        .lightbox img { max-width: 90vw; max-height: 90vh; border-radius: var(--radius-sm); }
        .lightbox-close { position: fixed; top: 20px; right: 24px; color: white; font-size: 30px; cursor: pointer; line-height: 1; }

        /* ── Responsive ──────────────────────────────────────────────────── */
        @media (max-width: 768px) {
            .sidebar { width: 100%; min-width: unset; }
            .message-content { max-width: 88%; }
        }
    </style>
    @stack('styles')
</head>
<body>

<nav class="app-nav">
    <a href="{{ route('landing') }}" class="nav-brand">🤖 AI Studio</a>
    <div class="nav-links">
        <a href="/chat" class="nav-link {{ request()->is('chat*') ? 'active' : '' }}">💬 Chat</a>
        <a href="{{ route('workflow.create') }}" class="nav-link {{ request()->is('workflow*') && !request()->is('workflow/generations*') ? 'active' : '' }}">🎨 Create</a>
        <a href="{{ route('workflow.generations') }}" class="nav-link {{ request()->is('workflow/generations*') ? 'active' : '' }}">🖼️ My Generations</a>
    </div>
</nav>

@yield('content')

@stack('scripts')
</body>
</html>
