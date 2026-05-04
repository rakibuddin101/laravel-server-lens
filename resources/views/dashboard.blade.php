<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $snapshot['title'] ?? 'Server Monitor' }} — Server Lens</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Phosphor Icons (MIT) --}}
    <script src="https://unpkg.com/@phosphor-icons/web@2.1.1/src/index.js" defer></script>

    <link rel="stylesheet" href="{{ asset('vendor/server-lens/css/server-lens.css') }}">

    <style>
        html, body { margin: 0; padding: 0; background: #0f172a; transition: background .2s; }
    </style>
</head>
<body>

@include('server-lens::widget', ['snapshot' => $snapshot, 'standalone' => true])

{{-- ApexCharts --}}
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
</body>
</html>
