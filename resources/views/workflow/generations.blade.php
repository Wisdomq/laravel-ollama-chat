@extends('layouts.app')

@section('title', 'My Generations – AI Studio')

@push('styles')
<style>
    .gen-page { max-width: 1100px; margin: 0 auto; padding: 28px 20px 48px; }
    .gen-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .gen-header h1 { font-size: 22px; color: var(--green-900); font-weight: 700; }
    .gen-header p  { font-size: 13px; color: var(--green-muted); margin-top: 2px; }
    .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; }
    .filter-btn { padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; cursor: pointer; border: 2px solid var(--green-100); background: white; color: var(--green-muted); transition: all var(--transition); }
    .filter-btn:hover, .filter-btn.active { background: var(--green-700); color: white; border-color: var(--green-700); }
    .gen-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
    .gen-card {
        background: white;
        border: 1px solid var(--green-100);
        border-radius: var(--radius-lg);
        overflow: hidden;
        transition: box-shadow var(--transition), transform var(--transition);
        display: flex;
        flex-direction: column;
    }
    .gen-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
    .gen-thumb {
        width: 100%;
        aspect-ratio: 16/9;
        object-fit: cover;
        display: block;
        background: var(--green-50);
        cursor: pointer;
    }
    .gen-thumb-placeholder {
        width: 100%;
        aspect-ratio: 16/9;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--green-50);
        font-size: 36px;
        color: var(--green-200);
    }
    .gen-body { padding: 12px 14px; flex: 1; display: flex; flex-direction: column; gap: 6px; }
    .gen-prompt { font-size: 13px; color: var(--green-800); line-height: 1.5; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; flex: 1; }
    .gen-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .gen-meta time { font-size: 11px; color: var(--green-muted); }
    .gen-footer { padding: 10px 14px; border-top: 1px solid var(--green-50); display: flex; gap: 8px; }
    .gen-footer a, .gen-footer button { flex: 1; text-align: center; }
    .empty-state { grid-column: 1/-1; padding: 60px 20px; text-align: center; color: var(--green-muted); }
    .empty-state .empty-icon { font-size: 52px; margin-bottom: 14px; }
    .empty-state h3 { font-size: 18px; color: var(--green-900); margin-bottom: 8px; }
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .status-completed { background: #d4edda; color: #155724; }
    .status-processing { background: #fff3cd; color: #856404; }
    .status-failed     { background: #f8d7da; color: #721c24; }
    .pagination { margin-top: 32px; display: flex; justify-content: center; gap: 6px; }
    .pagination a, .pagination span {
        padding: 7px 13px;
        border-radius: var(--radius-md);
        font-size: 13px;
        border: 1px solid var(--green-100);
        background: white;
        color: var(--green-800);
        transition: all var(--transition);
    }
    .pagination a:hover { background: var(--green-50); border-color: var(--green-400); }
    .pagination .active-page { background: var(--green-700); color: white; border-color: var(--green-700); }
</style>
@endpush

@section('content')
<div class="gen-page">

    <div class="gen-header">
        <div>
            <h1>🖼️ My Generations</h1>
            <p>{{ $jobs->total() }} generation{{ $jobs->total() !== 1 ? 's' : '' }} across all sessions</p>
        </div>
        <div class="filter-bar">
            <a href="{{ route('workflow.generations') }}" class="filter-btn {{ !request('type') ? 'active' : '' }}">All</a>
            @foreach($typeLabels as $key => $label)
                <a href="{{ route('workflow.generations', ['type' => $key]) }}" class="filter-btn {{ request('type') === $key ? 'active' : '' }}">
                    {{ $typeIcons[$key] ?? '' }} {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="gen-grid">
        @forelse($jobs as $job)
            @php
                $firstFile  = $job->result_paths[0] ?? null;
                $mediaType  = $firstFile['media_type'] ?? null;
                $thumbUrl   = $firstFile['url'] ?? null;
                $statusClass = match($job->status) {
                    'completed'  => 'status-completed',
                    'processing' => 'status-processing',
                    default      => 'status-failed',
                };
                $typeIcon = $typeIcons[$job->result_type] ?? '🎨';
            @endphp
            <div class="gen-card" data-type="{{ $job->result_type }}">

                @if($thumbUrl && in_array($mediaType, ['image','gif']))
                    <img class="gen-thumb" src="{{ $thumbUrl }}" alt="Generated image" loading="lazy" onclick="openLightbox('{{ $thumbUrl }}')">
                @elseif($thumbUrl && $mediaType === 'video')
                    <video class="gen-thumb" preload="none" style="cursor:default;">
                        <source src="{{ $thumbUrl }}" type="video/mp4">
                    </video>
                @else
                    <div class="gen-thumb-placeholder">{{ $typeIcon }}</div>
                @endif

                <div class="gen-body">
                    <div class="gen-prompt" title="{{ $job->refined_prompt }}">{{ $job->refined_prompt }}</div>
                    <div class="gen-meta">
                        <span class="status-pill {{ $statusClass }}">{{ $job->status }}</span>
                        <span class="badge badge-green">{{ $typeLabels[$job->result_type] ?? $job->result_type }}</span>
                        <time>{{ $job->created_at->diffForHumans() }}</time>
                    </div>
                </div>

                <div class="gen-footer">
                    @if($job->status === 'completed')
                        <a href="{{ route('workflow.result', $job->id) }}" class="btn btn-primary" style="font-size:12px;padding:6px 10px;">View</a>
                    @else
                        <span class="btn btn-ghost" style="font-size:12px;padding:6px 10px;cursor:default;">{{ ucfirst($job->status) }}</span>
                    @endif
                    <span style="font-size:11px;color:var(--green-muted);align-self:center;padding-left:4px;">#{{ $job->id }}</span>
                </div>
            </div>
        @empty
            <div class="empty-state">
                <div class="empty-icon">🎨</div>
                <h3>No generations yet</h3>
                <p style="margin-bottom:20px;">Create your first workflow to see results here.</p>
                <a href="{{ route('workflow.create') }}" class="btn btn-primary">Start Creating</a>
            </div>
        @endforelse
    </div>

    @if($jobs->hasPages())
        <div class="pagination">
            @if($jobs->onFirstPage())
                <span>‹</span>
            @else
                <a href="{{ $jobs->previousPageUrl() }}">‹</a>
            @endif

            @foreach($jobs->getUrlRange(1, $jobs->lastPage()) as $page => $url)
                @if($page == $jobs->currentPage())
                    <span class="active-page">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            @if($jobs->hasMorePages())
                <a href="{{ $jobs->nextPageUrl() }}">›</a>
            @else
                <span>›</span>
            @endif
        </div>
    @endif
</div>

<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close">✕</span>
    <img id="lightboxImg" src="" alt="">
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
</script>
@endpush
