@extends('layouts.app')

@section('title', 'Result – AI Studio')

@push('styles')
<style>
    .result-page { max-width: 960px; margin: 0 auto; padding: 28px 20px 48px; }
    .result-page .page-header { border-radius: var(--radius-lg); margin-bottom: 24px; }
    .prompt-card { margin-bottom: 24px; }
    .prompt-card h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.6px; color: var(--green-muted); margin-bottom: 10px; }
    .prompt-text { font-style: italic; color: var(--green-800); line-height: 1.7; border-left: 4px solid var(--green-400); padding-left: 14px; font-size: 14px; }
    .prompt-meta { margin-top: 10px; font-size: 12px; color: var(--green-muted); display: flex; gap: 16px; flex-wrap: wrap; }
    .results-title { font-size: 16px; color: var(--green-900); margin-bottom: 14px; font-weight: 700; }
    .results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; }
    .result-item { background: white; border: 1px solid var(--green-100); border-radius: var(--radius-lg); overflow: hidden; transition: box-shadow var(--transition); }
    .result-item:hover { box-shadow: var(--shadow-md); }
    .result-item img { width: 100%; height: auto; display: block; cursor: pointer; transition: opacity var(--transition); }
    .result-item img:hover { opacity: 0.92; }
    .result-item video, .result-item audio { width: 100%; display: block; }
    .result-footer { padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--green-50); gap: 8px; }
    .result-filename { font-size: 12px; color: var(--green-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
    .no-results { padding: 40px; text-align: center; color: var(--green-muted); }
    .page-actions { margin-top: 28px; display: flex; gap: 10px; flex-wrap: wrap; }
</style>
@endpush

@section('content')
<div class="result-page">

    <div class="card page-header" style="background:linear-gradient(135deg,var(--green-700),var(--green-800));color:white;display:flex;align-items:center;gap:16px;padding:16px 20px;">
        <div style="flex:1;">
            <div style="font-size:18px;font-weight:700;">✅ Generation Complete</div>
            <div style="font-size:12px;opacity:0.8;margin-top:3px;">
                {{ $job->workflow->name ?? 'Workflow' }} · Completed {{ $job->updated_at->diffForHumans() }}
            </div>
        </div>
    </div>

    <div class="card prompt-card">
        <h3>Prompt Used</h3>
        <div class="prompt-text">{{ $job->refined_prompt }}</div>
        <div class="prompt-meta">
            <span>Type: {{ ucfirst($job->result_type ?? 'unknown') }}</span>
            <span>Job #{{ $job->id }}</span>
            <span>Submitted: {{ $job->created_at->format('M j, Y H:i') }}</span>
        </div>
    </div>

    <div class="results-title">Generated Output</div>

    @if($job->result_paths && count($job->result_paths) > 0)
        <div class="results-grid">
            @foreach($job->result_paths as $index => $file)
                <div class="result-item">
                    @if(($file['media_type'] ?? '') === 'image')
                        <img src="{{ $file['url'] }}" alt="Generated image" onclick="openLightbox('{{ $file['url'] }}')" loading="lazy">
                    @elseif(($file['media_type'] ?? '') === 'video')
                        <video controls preload="metadata">
                            <source src="{{ $file['url'] }}" type="video/mp4">
                        </video>
                    @elseif(($file['media_type'] ?? '') === 'gif')
                        <img src="{{ $file['url'] }}" alt="Generated GIF" loading="lazy">
                    @elseif(($file['media_type'] ?? '') === 'audio')
                        <div style="padding:20px;text-align:center;">
                            <div style="font-size:32px;margin-bottom:10px;">🎵</div>
                            <audio controls style="width:100%;"><source src="{{ $file['url'] }}"></audio>
                        </div>
                    @else
                        <div style="padding:28px;text-align:center;color:var(--green-muted);">📄 {{ $file['filename'] ?? 'Output file' }}</div>
                    @endif

                    <div class="result-footer">
                        <span class="result-filename">{{ $file['filename'] ?? 'output' }}</span>
                        <button class="btn btn-primary" style="padding:5px 12px;font-size:12px;"
                            onclick="downloadFile('{{ $file['url'] }}', '{{ $file['filename'] ?? 'output_'.$index }}')">
                            ⬇ Download
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card no-results">
            <p>🎨 No output files were found for this job.</p>
            <p style="margin-top:6px;font-size:13px;">The job completed but ComfyUI did not produce output files. Check your workflow template.</p>
        </div>
    @endif

    <div class="page-actions">
        <form action="{{ route('workflow.reset') }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-primary">🎨 Create Another</button>
        </form>
        <a href="{{ route('workflow.generations') }}" class="btn btn-secondary">🖼️ My Generations</a>
        <a href="{{ route('landing') }}" class="btn btn-ghost">🏠 Home</a>
    </div>
</div>

<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()">✕</span>
    <img id="lightboxImg" src="" alt="Full size">
</div>
@endsection

@push('scripts')
<script>
function openLightbox(url) {
    document.getElementById('lightboxImg').src = url;
    document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

async function downloadFile(url, filename) {
    const btn = event.currentTarget;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = '⏳';
    try {
        const res = await fetch(url);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const blob = await res.blob();
        const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: filename });
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
        btn.textContent = '✅';
        setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 2000);
    } catch (err) {
        btn.disabled = false; btn.innerHTML = orig;
        alert('Download failed: ' + err.message);
    }
}
</script>
@endpush
