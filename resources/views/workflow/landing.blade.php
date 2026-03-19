@extends('layouts.app')

@section('title', 'AI Studio')

@push('styles')
<style>
    .landing-wrap {
        min-height: calc(100vh - 52px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    }
    .landing { text-align: center; max-width: 720px; width: 100%; }
    .landing .logo { font-size: 52px; margin-bottom: 14px; }
    .landing h1 { font-size: 30px; color: var(--green-900); margin-bottom: 8px; }
    .landing .subtitle { font-size: 15px; color: var(--green-muted); margin-bottom: 48px; }
    .cards { display: flex; gap: 24px; justify-content: center; flex-wrap: wrap; }
    .landing-card {
        background: white;
        border: 2px solid var(--green-100);
        border-radius: var(--radius-xl);
        padding: 36px 28px;
        width: 280px;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        transition: all 0.25s;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    .landing-card:hover {
        border-color: var(--green-700);
        box-shadow: var(--shadow-lg);
        transform: translateY(-4px);
    }
    .landing-card .card-icon { font-size: 46px; }
    .landing-card .card-title { font-size: 19px; font-weight: 700; color: var(--green-900); }
    .landing-card .card-desc { font-size: 13px; color: var(--green-muted); line-height: 1.6; text-align: center; }
    .landing-card .card-btn {
        margin-top: 8px;
        padding: 9px 26px;
        background: var(--green-700);
        color: white;
        border-radius: var(--radius-md);
        font-weight: 600;
        font-size: 13px;
        transition: background var(--transition);
    }
    .landing-card:hover .card-btn { background: var(--green-800); }
    @media (max-width: 600px) { .cards { flex-direction: column; align-items: center; } }
</style>
@endpush

@section('content')
<div class="landing-wrap">
    <div class="landing">
        <div class="logo">🤖</div>
        <h1>AI Studio</h1>
        <p class="subtitle">What would you like to do today?</p>

        <div class="cards">
            <a href="/chat" class="landing-card">
                <div class="card-icon">💬</div>
                <div class="card-title">Just Chat</div>
                <div class="card-desc">Have a conversation with the AI assistant. Ask questions, get help, or explore ideas.</div>
                <div class="card-btn">Start Chatting</div>
            </a>

            <a href="{{ route('workflow.create') }}" class="landing-card">
                <div class="card-icon">🎨</div>
                <div class="card-title">Create with AI</div>
                <div class="card-desc">Describe your vision and let the AI craft an optimised prompt, then generate images, video, or audio with ComfyUI.</div>
                <div class="card-btn">Create Workflow</div>
            </a>

            <a href="{{ route('workflow.generations') }}" class="landing-card">
                <div class="card-icon">🖼️</div>
                <div class="card-title">My Generations</div>
                <div class="card-desc">Browse all your previously generated images, videos, and audio files.</div>
                <div class="card-btn">View Gallery</div>
            </a>
        </div>
    </div>
</div>
@endsection
