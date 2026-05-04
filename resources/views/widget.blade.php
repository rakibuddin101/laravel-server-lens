@php
    $standalone = $standalone ?? false;
    $s          = $snapshot   ?? [];
    $metrics    = $s['metrics']       ?? [];
    $checks     = $s['health_checks'] ?? [];
    $activity   = $s['activity']      ?? [];
    $header     = $s['header']        ?? [];
    $footer     = $s['footer']        ?? [];

    $sCls = fn($status) => match($status ?? '') {
        'critical' => 'is-critical',
        'warning'  => 'is-warning',
        'inactive' => 'is-inactive',
        default    => 'is-healthy',
    };

    $healthOk    = count(array_filter($checks, fn($c) => ($c['status'] ?? '') === 'healthy'));
    $healthTotal = count($checks);
    $overallBadge = $healthOk === $healthTotal ? 'is-healthy' : ($healthOk < $healthTotal / 2 ? 'is-critical' : 'is-warning');
@endphp

<div class="sl-root" id="sl-root">
    <div class="sl-page">

        {{-- ── Topbar ──────────────────────────────────────────────────────── --}}
        <div class="sl-topbar">
            <div class="sl-topbar-brand">
                <div class="sl-topbar-icon">
                    <i class="ph ph-monitor-play"></i>
                </div>
                <div>
                    <div class="sl-topbar-title">{{ $s['title'] ?? 'Server Monitor' }}</div>
                    <div class="sl-topbar-sub">Real-time infrastructure health &amp; request telemetry</div>
                </div>
            </div>

            <div class="sl-topbar-meta">
                <span class="sl-live-dot" title="Live polling active"></span>
                <span class="sl-timestamp" data-sl="timestamp">
                    Updated {{ $s['generated_at_label'] ?? now()->format('h:i:s A') }}
                </span>
                <button class="sl-btn-icon" data-sl="toggle-poll" title="Pause monitoring">
                    <i class="ph ph-pause"></i>
                </button>
                <button class="sl-btn-icon" data-sl="toggle-theme" title="Switch to light mode">
                    <i class="ph ph-sun"></i>
                </button>
                <span class="sl-pill {{ $overallBadge }}" style="font-size:.72rem;">
                    <i class="ph ph-{{ match($s['overall_status'] ?? 'healthy') {
                        'critical' => 'warning-octagon',
                        'warning'  => 'warning',
                        'inactive' => 'pause-circle',
                        default    => 'check-circle',
                    } }}" style="margin-right:4px;"></i>
                    {{ $s['overall_status_label'] ?? 'Healthy' }}
                </span>
            </div>
        </div>

        {{-- ── Metric cards ─────────────────────────────────────────────────── --}}
        <div class="sl-section">
            <div class="sl-grid sl-grid-4">
                @foreach($metrics as $metric)
                    @php $sc = $sCls($metric['status'] ?? 'inactive'); @endphp
                    <div class="sl-stat {{ $sc }}" data-sl-metric="{{ $metric['key'] ?? '' }}">
                        <div class="sl-stat-hd">
                            <span class="sl-stat-label">{{ $metric['label'] ?? '' }}</span>
                            <div class="sl-mini-icon {{ $sc }}" data-sl-role="icon">
                                <i class="ph ph-{{ match($metric['key'] ?? '') {
                                    'cpu'      => 'cpu',
                                    'memory'   => 'memory',
                                    'disk'     => 'hard-drive',
                                    'response' => 'activity',
                                    default    => 'circle',
                                } }}"></i>
                            </div>
                        </div>

                        <div class="sl-stat-value" data-sl-role="value">{{ $metric['value'] ?? 'N/A' }}</div>

                        <div class="sl-track">
                            <div class="sl-fill {{ $sc }}"
                                 data-sl-role="fill"
                                 style="width:{{ max(0, min(100, (float)($metric['percent'] ?? 0))) }}%"></div>
                        </div>

                        <div class="sl-stat-foot">
                            <div>
                                <div class="sl-stat-meta"   data-sl-role="meta">{{ $metric['meta']   ?? '' }}</div>
                                <div class="sl-stat-detail" data-sl-role="detail">{{ $metric['detail'] ?? '' }}</div>
                            </div>
                            <span class="sl-pill {{ $sc }}" data-sl-role="pill">
                                {{ ucfirst($metric['status'] ?? 'inactive') }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ── Charts row ───────────────────────────────────────────────────── --}}
        <div class="sl-section">
            <div class="sl-grid sl-grid-72">

                {{-- Resource Utilization --}}
                <div class="sl-panel">
                    <div class="sl-panel-hd">
                        <div>
                            <div class="sl-panel-title">Resource Utilization</div>
                            <div class="sl-panel-sub">
                                CPU · Memory · Disk — sampled every {{ $s['poll_seconds'] ?? 5 }}s
                            </div>
                        </div>
                        <div class="sl-legend">
                            <div class="sl-legend-item">
                                <span class="sl-legend-dot" style="background:#3b82f6;"></span> CPU
                            </div>
                            <div class="sl-legend-item">
                                <span class="sl-legend-dot" style="background:#8b5cf6;"></span> Mem
                            </div>
                            <div class="sl-legend-item">
                                <span class="sl-legend-dot" style="background:#10b981;"></span> Disk
                            </div>
                        </div>
                    </div>
                    <div class="sl-panel-body" style="padding:12px 14px 8px;">
                        <div data-sl="resource-chart" style="min-height:220px; flex:1;"></div>
                    </div>
                    <div class="sl-panel-foot">
                        <i class="ph ph-cpu"></i>&nbsp;{{ $footer['cpu_cores'] ?? 1 }} cores
                        &nbsp;·&nbsp;
                        <i class="ph ph-clock"></i>&nbsp;Uptime {{ $footer['uptime_human'] ?? 'unavailable' }}
                        &nbsp;·&nbsp;
                        <i class="ph ph-chart-line"></i>&nbsp;Load {{ $footer['load_average_label'] ?? '—' }}
                    </div>
                </div>

                {{-- Request Throughput --}}
                <div class="sl-panel">
                    <div class="sl-panel-hd">
                        <div>
                            <div class="sl-panel-title">Request Throughput</div>
                            <div class="sl-panel-sub">
                                Success vs errors over last {{ count($activity['chart']['labels'] ?? []) }} min
                            </div>
                        </div>
                        <span class="sl-chip" style="font-weight:700;" data-sl="req-hr">
                            {{ number_format((int)($activity['summary']['requests_last_hour'] ?? 0)) }} req/hr
                        </span>
                    </div>
                    <div class="sl-panel-body" style="padding:12px 14px 8px;">
                        <div data-sl="activity-chart" style="min-height:220px; flex:1;"></div>
                    </div>
                    <div class="sl-panel-foot">
                        <span style="color:#10b981; font-weight:600;">■</span>&nbsp;Success
                        &nbsp;·&nbsp;
                        <span style="color:#ef4444; font-weight:600;">■</span>&nbsp;Errors
                        &nbsp;·&nbsp;
                        {{ $activity['summary']['error_rate_label'] ?? '0% errors' }}
                    </div>
                </div>

            </div>
        </div>

        {{-- ── Health + Feed row ────────────────────────────────────────────── --}}
        <div class="sl-section">
            <div class="sl-grid sl-grid-72" style="grid-template-columns: 5fr 7fr; grid-template-rows: 420px;">

                {{-- Service Health --}}
                <div class="sl-panel">
                    <div class="sl-panel-hd">
                        <div>
                            <div class="sl-panel-title">Service Health</div>
                            <div class="sl-panel-sub">
                                <i class="ph ph-chart-bar"></i>&nbsp;{{ $footer['load_average_label'] ?? '—' }} load avg
                            </div>
                        </div>
                        <span class="sl-pill {{ $overallBadge }}" style="font-size:.68rem;">
                            {{ $healthOk }}/{{ $healthTotal }} OK
                        </span>
                    </div>
                    <div class="sl-panel-body">
                        <div class="sl-health-list" data-sl="health-list">
                            @forelse($checks as $check)
                                @php $sc = $sCls($check['status'] ?? 'inactive'); @endphp
                                <div class="sl-health-row">
                                    <span class="sl-health-icon {{ $sc }}">
                                        <i class="ph ph-{{ match($check['label'] ?? '') {
                                            'Database'    => 'database',
                                            'Cache'       => 'stack',
                                            'Queue'       => 'list-bullets',
                                            'Redis'       => 'circles-four',
                                            'Storage'     => 'hard-drive',
                                            'Application' => 'globe',
                                            default       => 'activity',
                                        } }}"></i>
                                    </span>
                                    <div class="sl-flex-fill">
                                        <div class="sl-flex sl-items-center sl-justify-between sl-gap-2">
                                            <span class="sl-health-name">{{ $check['label'] ?? '' }}</span>
                                        </div>
                                        <div class="sl-health-detail">{{ $check['detail'] ?? '' }}</div>
                                    </div>
                                    <div class="sl-health-row-right">
                                        <span class="sl-pill {{ $sc }}">{{ ucfirst($check['status'] ?? 'inactive') }}</span>
                                        @if(!empty($check['latency_ms']))
                                            <span class="sl-chip" style="font-size:.67rem;">{{ round((float)$check['latency_ms']) }} ms</span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="sl-empty">
                                    <i class="ph ph-heartbeat"></i>
                                    <span>No health checks configured</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Live Request Feed --}}
                <div class="sl-panel">
                    <div class="sl-panel-hd">
                        <div>
                            <div class="sl-panel-title">Live Request Feed</div>
                            <div class="sl-panel-sub" data-sl="feed-volume">
                                {{ number_format((int)($activity['summary']['requests_last_5m'] ?? 0)) }} requests in last 5 min
                            </div>
                        </div>
                        <span class="sl-chip" data-sl="feed-note" style="font-size:.72rem; text-align:right; flex-shrink:0;">
                            {{ $activity['summary']['latest_seen_label'] ?? 'No recent requests' }}
                        </span>
                    </div>
                    <div class="sl-panel-body">
                        <div class="sl-feed" data-sl="feed">
                            @forelse($activity['recent'] ?? [] as $req)
                                @php
                                    $code = (int)($req['status_code'] ?? 200);
                                    $ms   = $req['response_ms'] ?? null;
                                    $meth = $req['method'] ?? 'GET';
                                    $mCls = match($meth) {
                                        'POST'           => 'sl-method-post',
                                        'PUT', 'PATCH'   => 'sl-method-put',
                                        'DELETE'         => 'sl-method-delete',
                                        default          => 'sl-method-get',
                                    };
                                    $cCls = $code >= 500 ? 'sl-code-5xx' : ($code >= 400 ? 'sl-code-4xx' : 'sl-code-2xx');
                                    $rCls = $ms === null ? '' : ($ms < 200 ? 'sl-rt-fast' : ($ms < 500 ? 'sl-rt-mid' : 'sl-rt-slow'));
                                @endphp
                                <div class="sl-feed-row">
                                    <div class="sl-feed-badges">
                                        <span class="sl-method {{ $mCls }}">{{ $meth }}</span>
                                        <span class="sl-code {{ $cCls }}">{{ $code }}</span>
                                    </div>
                                    <div class="sl-flex-fill">
                                        <div class="sl-feed-path">{{ $req['path'] ?? '/' }}</div>
                                        <div class="sl-feed-meta">
                                            {{ ucfirst($req['classification'] ?? 'human') }}
                                            @if(!empty($req['is_ajax'])) &nbsp;·&nbsp; AJAX @endif
                                        </div>
                                    </div>
                                    @if($ms !== null)
                                        <span class="sl-rt {{ $rCls }}">{{ $ms }}ms</span>
                                    @endif
                                    <span class="sl-feed-time">{{ $req['time_ago'] ?? 'just now' }}</span>
                                </div>
                            @empty
                                <div class="sl-empty">
                                    <i class="ph ph-activity"></i>
                                    <span>No request telemetry yet</span>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ── Footer chips ─────────────────────────────────────────────────── --}}
        <div class="sl-card">
            <div class="sl-card-foot">
                <span class="sl-chip"><i class="ph ph-desktop"></i>{{ $header['os'] ?? php_uname('s') }}</span>
                <span class="sl-chip-sep"></span>
                <span class="sl-chip"><i class="ph ph-code"></i>PHP {{ $header['php_version'] ?? PHP_VERSION }}</span>
                <span class="sl-chip-sep"></span>
                <span class="sl-chip"><i class="ph ph-database"></i>{{ $header['database'] ?? config('database.default') }}</span>
                <span class="sl-chip-sep"></span>
                <span class="sl-chip"><i class="ph ph-stack"></i>Cache: {{ $header['cache'] ?? config('cache.default') }}</span>
                <span class="sl-chip-sep"></span>
                <span class="sl-chip"><i class="ph ph-list-bullets"></i>Queue: {{ $header['queue'] ?? config('queue.default') }}</span>
                <span class="sl-chip-sep"></span>
                <span class="sl-chip"><i class="ph ph-server"></i>{{ $header['hostname'] ?? php_uname('n') }}</span>
                <span style="margin-left:auto;" class="sl-chip">
                    Source: {{ $footer['request_source'] ?? 'unavailable' }}
                </span>
            </div>
        </div>

    </div>{{-- /sl-page --}}
</div>{{-- /sl-root --}}

{{-- Init script (only included when used as standalone widget inside existing app) --}}
@if(!$standalone)
    @once
        @push('head')
            <link rel="stylesheet" href="{{ asset('vendor/server-lens/css/server-lens.css') }}">
        @endpush
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>
            <script src="{{ asset('vendor/server-lens/js/server-lens.js') }}"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    new ServerLens('sl-root', {
                        pollUrl      : '{{ route('server-lens.poll') }}',
                        pollSeconds  : {{ (int) ($snapshot['poll_seconds'] ?? 5) }},
                        historyPoints: {{ (int) ($snapshot['resource_chart']['history_points'] ?? 12) }},
                        initial      : @json($snapshot),
                    });
                });
            </script>
        @endpush
    @endonce
@endif
