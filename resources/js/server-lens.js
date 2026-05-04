/**
 * Laravel Server Lens — dashboard JS
 * Requires: ApexCharts (loaded separately)
 */
(function (global) {
    'use strict';

    function ServerLens(rootId, options) {
        this.root         = document.getElementById(rootId);
        this.pollUrl      = options.pollUrl;
        this.pollInterval = (options.pollSeconds || 5) * 1000;
        this.histPoints   = options.historyPoints || 12;
        this.initial      = options.initial || {};

        this.resourceChart  = null;
        this.activityChart  = null;
        this.isPolling      = false;
        this.timer          = null;
        this.paused         = false;
        this.theme          = 'dark';

        this.state = {
            labels : this._seedLabels(this.histPoints, this.initial.generated_at, this.pollInterval),
            cpu    : this._seedSeries(this.initial.metrics?.cpu?.percent, this.histPoints),
            memory : this._seedSeries(this.initial.metrics?.memory?.percent, this.histPoints),
            disk   : this._seedSeries(this.initial.metrics?.disk?.percent, this.histPoints),
        };

        if (!this.root) return;

        this._initCharts(this.initial);
        this._applySnapshot(this.initial, false);
        this._startPolling();
        this._bindControls();
        this._restoreState();
    }

    // ── Controls ──────────────────────────────────────────────────────────────

    ServerLens.prototype._bindControls = function () {
        var self = this;

        var btnPoll = this.root.querySelector('[data-sl="toggle-poll"]');
        if (btnPoll) btnPoll.addEventListener('click', function () { self._togglePoll(btnPoll); });

        var btnTheme = this.root.querySelector('[data-sl="toggle-theme"]');
        if (btnTheme) btnTheme.addEventListener('click', function () { self._toggleTheme(btnTheme); });
    };

    ServerLens.prototype._togglePoll = function (btn) {
        var dot = this.root.querySelector('.sl-live-dot');

        if (this.paused) {
            this.paused = false;
            this._startPolling();
            try { localStorage.removeItem('sl-paused'); } catch (_) {}
            if (dot) dot.classList.remove('is-paused');
            if (btn) { btn.querySelector('i').className = 'ph ph-pause'; btn.title = 'Pause monitoring'; }
        } else {
            this.paused = true;
            if (this.timer) { clearInterval(this.timer); this.timer = null; }
            try { localStorage.setItem('sl-paused', '1'); } catch (_) {}
            if (dot) dot.classList.add('is-paused');
            if (btn) { btn.querySelector('i').className = 'ph ph-play'; btn.title = 'Resume monitoring'; }
        }
    };

    ServerLens.prototype._restoreState = function () {
        var wasPaused = false;
        try { wasPaused = localStorage.getItem('sl-paused') === '1'; } catch (_) {}

        if (wasPaused) {
            var btn = this.root.querySelector('[data-sl="toggle-poll"]');
            this._togglePoll(btn);
        }
    };

    ServerLens.prototype._toggleTheme = function (btn) {
        var isLight = this.root.classList.toggle('sl-light');
        this.theme = isLight ? 'light' : 'dark';

        document.documentElement.style.background = isLight ? '#f1f5f9' : '#0f172a';
        document.body.style.background            = isLight ? '#f1f5f9' : '#0f172a';

        if (btn) {
            btn.querySelector('i').className = isLight ? 'ph ph-moon' : 'ph ph-sun';
            btn.title = isLight ? 'Switch to dark mode' : 'Switch to light mode';
        }

        var chartOpts = {
            theme: { mode: this.theme },
            grid:  { borderColor: isLight ? 'rgba(0,0,0,.08)' : 'rgba(255,255,255,.06)' },
            tooltip: { theme: this.theme },
        };
        if (this.resourceChart) this.resourceChart.updateOptions(chartOpts, false, false);
        if (this.activityChart) this.activityChart.updateOptions(chartOpts, false, false);
    };

    // ── Polling ───────────────────────────────────────────────────────────────

    ServerLens.prototype._startPolling = function () {
        var self = this;

        if (self.timer) return;

        self.timer = setInterval(function () {
            if (!document.hidden) self._poll();
        }, self.pollInterval);
    };

    ServerLens.prototype._poll = async function () {
        var self = this;

        if (self.isPolling) return;
        self.isPolling = true;

        try {
            var response = await fetch(self.pollUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });

            if (!response.ok) return;

            var snapshot = await response.json();
            self._applySnapshot(snapshot, true);
        } catch (_) {
            self._setTimestamp('Connection delayed…');
        } finally {
            self.isPolling = false;
        }
    };

    // ── Apply snapshot ────────────────────────────────────────────────────────

    ServerLens.prototype._applySnapshot = function (snapshot, animate) {
        if (!snapshot) return;

        this._updateHeader(snapshot);
        this._updateMetricCards(snapshot.metrics || {});
        this._updateHealthList(snapshot.health_checks || []);
        this._updateRequestFeed(snapshot.activity || {});

        if (animate) {
            var dot = this.root.querySelector('.sl-live-dot');
            if (dot) {
                dot.classList.add('is-flash');
                setTimeout(function () { dot.classList.remove('is-flash'); }, 500);
            }
            this._pushResourceSample(snapshot);
            this.resourceChart.updateOptions({ xaxis: { categories: this.state.labels } }, false, false);
            this.resourceChart.updateSeries([
                { name: 'CPU',    data: this.state.cpu    },
                { name: 'Memory', data: this.state.memory },
                { name: 'Disk',   data: this.state.disk   },
            ], true);

            this.activityChart.updateOptions({
                xaxis: { categories: snapshot.activity?.chart?.labels || [] },
            }, false, false);
            this.activityChart.updateSeries([
                { name: 'Success', data: snapshot.activity?.chart?.successful_series || [] },
                { name: 'Errors',  data: snapshot.activity?.chart?.error_series       || [] },
            ], true);
        }
    };

    // ── Header ────────────────────────────────────────────────────────────────

    ServerLens.prototype._updateHeader = function (snapshot) {
        this._setTimestamp('Updated ' + (snapshot.generated_at_label || '—'));

        var note = this.root.querySelector('[data-sl="req-hr"]');
        if (note) note.textContent = this._fmt(snapshot.activity?.summary?.requests_last_hour || 0) + ' req/hr';
    };

    // ── Metric cards ──────────────────────────────────────────────────────────

    ServerLens.prototype._updateMetricCards = function (metrics) {
        var root = this.root;

        Object.keys(metrics).forEach(function (key) {
            var m    = metrics[key];
            var card = root.querySelector('[data-sl-metric="' + key + '"]');
            if (!card) return;

            var q = function (role) { return card.querySelector('[data-sl-role="' + role + '"]'); };

            var val     = q('value');
            var meta    = q('meta');
            var detail  = q('detail');
            var fill    = q('fill');
            var pill    = q('pill');
            var icon    = q('icon');

            if (val)  val.textContent  = m.value || '—';
            if (meta) meta.textContent = m.meta  || '';
            if (detail) detail.textContent = m.detail || '';

            var sc = ServerLens._statusClass(m.status);

            if (fill) {
                fill.style.width = ServerLens._clamp(m.percent || 0) + '%';
                fill.className   = 'sl-fill ' + sc;
            }
            if (pill) {
                pill.className   = 'sl-pill ' + sc;
                pill.textContent = ServerLens._cap(m.status || 'healthy');
            }
            if (icon) {
                icon.className  = 'sl-mini-icon ' + sc;
                icon.innerHTML  = '<i class="' + ServerLens._esc(m.icon || 'sl-icon-activity') + '"></i>';
            }

            card.classList.remove('is-healthy', 'is-warning', 'is-critical', 'is-inactive');
            card.classList.add(sc);
        });
    };

    // ── Health list ───────────────────────────────────────────────────────────

    ServerLens.prototype._updateHealthList = function (checks) {
        var container = this.root.querySelector('[data-sl="health-list"]');
        if (!container) return;

        if (!Array.isArray(checks) || checks.length === 0) {
            container.innerHTML = '<div class="sl-empty"><i class="sl-icon-heart-off"></i><span>No health checks</span></div>';
            return;
        }

        container.innerHTML = checks.map(function (check) {
            var sc = ServerLens._statusClass(check.status);
            return '<div class="sl-health-row">' +
                '<span class="sl-health-icon ' + sc + '">' +
                    '<i class="' + ServerLens._esc(check.icon || 'sl-icon-activity') + '"></i>' +
                '</span>' +
                '<div class="sl-flex-fill">' +
                    '<div class="sl-flex sl-items-center sl-justify-between sl-gap-2">' +
                        '<span class="sl-health-name">' + ServerLens._esc(check.label || '') + '</span>' +
                    '</div>' +
                    '<div class="sl-health-detail">' + ServerLens._esc(check.detail || '') + '</div>' +
                '</div>' +
                '<div class="sl-health-row-right">' +
                    '<span class="sl-pill ' + sc + '">' + ServerLens._cap(check.status || 'healthy') + '</span>' +
                    (check.latency_ms != null ? '<span class="sl-chip" style="font-size:.67rem;">' + Math.round(check.latency_ms) + ' ms</span>' : '') +
                '</div>' +
            '</div>';
        }).join('');
    };

    // ── Request feed ──────────────────────────────────────────────────────────

    ServerLens.prototype._updateRequestFeed = function (activity) {
        var container = this.root.querySelector('[data-sl="feed"]');
        var note      = this.root.querySelector('[data-sl="feed-note"]');
        var volume    = this.root.querySelector('[data-sl="feed-volume"]');

        if (note)   note.textContent   = activity.summary?.latest_seen_label || 'No recent requests';
        if (volume) volume.textContent = this._fmt(activity.summary?.requests_last_5m || 0) + ' requests in last 5 min';

        if (!container) return;

        if (!Array.isArray(activity.recent) || activity.recent.length === 0) {
            container.innerHTML = '<div class="sl-empty"><i class="sl-icon-activity"></i><span>No request telemetry yet</span></div>';
            return;
        }

        container.innerHTML = activity.recent.map(function (req) {
            var ms  = req.response_ms != null ? Number(req.response_ms) : null;
            var rtHtml = ms !== null
                ? '<span class="sl-rt ' + ServerLens._rtClass(ms) + '">' + ms + 'ms</span>'
                : '';

            return '<div class="sl-feed-row">' +
                '<div class="sl-feed-badges">' +
                    '<span class="sl-method ' + ServerLens._methodClass(req.method) + '">' + ServerLens._esc(req.method || 'GET') + '</span>' +
                    '<span class="sl-code ' + ServerLens._codeClass(req.status_code) + '">' + ServerLens._esc(String(req.status_code || 200)) + '</span>' +
                '</div>' +
                '<div class="sl-flex-fill">' +
                    '<div class="sl-feed-path">' + ServerLens._esc(req.path || '/') + '</div>' +
                    '<div class="sl-feed-meta">' +
                        ServerLens._cap(req.classification || 'human') +
                        (req.is_ajax ? ' · AJAX' : '') +
                    '</div>' +
                '</div>' +
                rtHtml +
                '<span class="sl-feed-time">' + ServerLens._esc(req.time_ago || 'just now') + '</span>' +
            '</div>';
        }).join('');
    };

    // ── Charts ────────────────────────────────────────────────────────────────

    ServerLens.prototype._initCharts = function (snapshot) {
        var resourceEl  = this.root.querySelector('[data-sl="resource-chart"]');
        var activityEl  = this.root.querySelector('[data-sl="activity-chart"]');

        if (!window.ApexCharts || !resourceEl || !activityEl) return;

        this.resourceChart = new ApexCharts(resourceEl, {
            chart: {
                type: 'area', fontFamily: 'inherit', height: 220,
                parentHeightOffset: 0, toolbar: { show: false },
                animations: { enabled: true, dynamicAnimation: { speed: 350 } },
                background: 'transparent',
            },
            theme: { mode: 'dark' },
            series: [
                { name: 'CPU',    data: this.state.cpu    },
                { name: 'Memory', data: this.state.memory },
                { name: 'Disk',   data: this.state.disk   },
            ],
            colors: ['#3b82f6', '#8b5cf6', '#10b981'],
            stroke: { curve: 'smooth', width: [2.5, 2.5, 2] },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: .4, opacityFrom: .22, opacityTo: .02, stops: [0, 90] },
            },
            dataLabels: { enabled: false },
            grid: {
                borderColor: 'rgba(255,255,255,.06)',
                strokeDashArray: 4,
                padding: { top: -10, right: 4, bottom: 0, left: 0 },
            },
            xaxis: {
                categories: this.state.labels,
                labels: { show: false },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                min: 0, max: 100, tickAmount: 4,
                labels: {
                    formatter: function (v) { return Math.round(v) + '%'; },
                    style: { fontSize: '11px', colors: '#64748b' },
                },
            },
            legend: { show: false },
            tooltip: {
                theme: 'dark', shared: true,
                y: { formatter: function (v) { return Number(v || 0).toFixed(1) + '%'; } },
            },
        });
        this.resourceChart.render();

        this.activityChart = new ApexCharts(activityEl, {
            chart: {
                type: 'bar', fontFamily: 'inherit', height: 220,
                parentHeightOffset: 0, toolbar: { show: false },
                animations: { enabled: true, dynamicAnimation: { speed: 350 } },
                stacked: true, background: 'transparent',
            },
            theme: { mode: 'dark' },
            series: [
                { name: 'Success', data: snapshot.activity?.chart?.successful_series || [] },
                { name: 'Errors',  data: snapshot.activity?.chart?.error_series       || [] },
            ],
            colors: ['#10b981', '#ef4444'],
            plotOptions: {
                bar: { columnWidth: '55%', borderRadius: 3, borderRadiusApplication: 'end' },
            },
            dataLabels: { enabled: false },
            grid: {
                borderColor: 'rgba(255,255,255,.06)',
                strokeDashArray: 4,
                padding: { top: -8, right: 4, bottom: 0, left: 0 },
            },
            xaxis: {
                categories: snapshot.activity?.chart?.labels || [],
                labels: { style: { fontSize: '11px', colors: '#64748b' } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: { labels: { style: { fontSize: '11px', colors: '#64748b' } } },
            legend: { show: false },
            tooltip: { theme: 'dark' },
        });
        this.activityChart.render();
    };

    // ── State helpers ─────────────────────────────────────────────────────────

    ServerLens.prototype._pushResourceSample = function (snapshot) {
        var stamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

        this.state.labels.push(stamp);
        this.state.cpu.push(this._num(snapshot.metrics?.cpu?.percent));
        this.state.memory.push(this._num(snapshot.metrics?.memory?.percent));
        this.state.disk.push(this._num(snapshot.metrics?.disk?.percent));

        while (this.state.labels.length  > this.histPoints) this.state.labels.shift();
        while (this.state.cpu.length     > this.histPoints) this.state.cpu.shift();
        while (this.state.memory.length  > this.histPoints) this.state.memory.shift();
        while (this.state.disk.length    > this.histPoints) this.state.disk.shift();
    };

    ServerLens.prototype._seedSeries = function (value, count) {
        var v = this._num(value);
        return Array.from({ length: count }, function () { return v; });
    };

    ServerLens.prototype._seedLabels = function (count, generatedAt, interval) {
        var base = generatedAt ? new Date(generatedAt) : new Date();
        return Array.from({ length: count }, function (_, i) {
            var d = new Date(base.getTime() - (count - i - 1) * interval);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        });
    };

    // ── Misc helpers ──────────────────────────────────────────────────────────

    ServerLens.prototype._setTimestamp = function (text) {
        var el = this.root.querySelector('[data-sl="timestamp"]');
        if (el) el.textContent = text;
    };

    ServerLens.prototype._num = function (value) {
        var n = Number(value);
        return Number.isFinite(n) ? n : null;
    };

    ServerLens.prototype._fmt = function (n) {
        return Number(n || 0).toLocaleString();
    };

    // ── Static helpers ────────────────────────────────────────────────────────

    ServerLens._statusClass = function (status) {
        var m = { critical: 'is-critical', warning: 'is-warning', inactive: 'is-inactive' };
        return m[status] || 'is-healthy';
    };

    ServerLens._clamp = function (v) {
        return Math.max(0, Math.min(100, Number(v || 0)));
    };

    ServerLens._cap = function (s) {
        s = String(s || '');
        return s ? s[0].toUpperCase() + s.slice(1) : '';
    };

    ServerLens._esc = function (v) {
        if (v == null) return '';
        var d = document.createElement('div');
        d.textContent = String(v);
        return d.innerHTML;
    };

    ServerLens._methodClass = function (m) {
        var map = { POST: 'sl-method-post', PUT: 'sl-method-put', PATCH: 'sl-method-put', DELETE: 'sl-method-delete' };
        return map[m] || 'sl-method-get';
    };

    ServerLens._codeClass = function (c) {
        var n = Number(c || 0);
        if (n >= 500) return 'sl-code-5xx';
        if (n >= 400) return 'sl-code-4xx';
        return 'sl-code-2xx';
    };

    ServerLens._rtClass = function (ms) {
        if (ms < 200) return 'sl-rt-fast';
        if (ms < 500) return 'sl-rt-mid';
        return 'sl-rt-slow';
    };

    global.ServerLens = ServerLens;

}(window));
