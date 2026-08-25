@php
    /** @var array<string, mixed> $summary */
    $metrics = $summary['metrics'] ?? [];
    $progress = $summary['progress'] ?? [];
@endphp

<div class="cp-summary cp-sticky-summary">
    <div class="cp-summary__main">
        <div class="cp-summary__heading">
            <div>
                <p class="cp-summary__eyebrow">Overview</p>
                <h2 class="cp-summary__title">{{ $summary['title'] ?? '—' }}</h2>
                @if (! empty($summary['subtitle']))
                    <p class="cp-summary__subtitle">{{ $summary['subtitle'] }}</p>
                @endif
            </div>
            @if (! empty($summary['status']['label']))
                <span class="cp-badge {{ $summary['status']['class'] ?? 'cp-badge--muted' }}">
                    {{ $summary['status']['label'] }}
                </span>
            @endif
        </div>

        @if ($progress !== [])
            <div class="cp-progress-rail" aria-label="Progress">
                @foreach ($progress as $step)
                    @php
                        $state = $step['status'] ?? 'pending';
                        $dotClass = match ($state) {
                            'completed' => 'cp-progress-rail__dot--completed',
                            'current' => 'cp-progress-rail__dot--current',
                            'failed' => 'cp-progress-rail__dot--failed',
                            default => 'cp-progress-rail__dot--pending',
                        };
                    @endphp
                    <div class="cp-progress-rail__step">
                        <span class="cp-progress-rail__dot {{ $dotClass }}"></span>
                        <span class="cp-progress-rail__label">{{ $step['label'] ?? '' }}</span>
                    </div>
                    @if (! $loop->last)
                        <span class="cp-progress-rail__line"></span>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="cp-summary__steps">
            <div>
                <span class="cp-summary__step-label">Current step</span>
                <strong>{{ $summary['current_step'] ?? '—' }}</strong>
            </div>
            <div>
                <span class="cp-summary__step-label">Next step</span>
                <strong>{{ $summary['next_step'] ?? '—' }}</strong>
            </div>
            <div>
                <span class="cp-summary__step-label">Created</span>
                <strong>{{ $summary['created_at'] ?? '—' }}</strong>
            </div>
            <div>
                <span class="cp-summary__step-label">Updated</span>
                <strong>{{ $summary['updated_at'] ?? '—' }}</strong>
            </div>
        </div>
    </div>

    @if ($metrics !== [])
        <div class="cp-summary__metrics">
            @foreach ($metrics as $metric)
                <div class="cp-summary__metric">
                    <span class="cp-summary__metric-label">{{ $metric['label'] ?? '' }}</span>
                    @if (! empty($metric['url']))
                        <a href="{{ $metric['url'] }}" class="cp-summary__metric-value cp-link">{{ $metric['value'] ?? '—' }}</a>
                    @else
                        <span class="cp-summary__metric-value">{{ $metric['value'] ?? '—' }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
