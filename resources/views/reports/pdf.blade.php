<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; color: #1a202c; font-size: 11px; }
        h1 { font-size: 20px; margin-bottom: 2px; }
        h2 { font-size: 14px; margin-top: 18px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        .meta { color: #64748b; font-size: 10px; }
        .disclaimer { background: #f1f5f9; border-left: 3px solid #64748b; padding: 6px 8px; font-size: 9px; color: #334155; margin-top: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { text-align: left; background: #f1f5f9; padding: 4px 6px; border: 1px solid #cbd5e1; font-size: 10px; }
        td { padding: 4px 6px; border: 1px solid #e2e8f0; }
        .prio-high { color: #b91c1c; font-weight: bold; }
        .prio-medium { color: #b45309; }
        .prio-low { color: #1d4ed8; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <h1>{{ $snapshot['website']['label'] ?: $snapshot['website']['host'] }} &mdash; AdPilot AI Marketing Report</h1>
    <p class="meta">
        Website: {{ $snapshot['website']['url'] }} &middot;
        Analysis: {{ $snapshot['job']['uuid'] }} &middot;
        Crawled pages: {{ $snapshot['job']['pages'] }} ({{ $snapshot['job']['errors'] }} error(s)) &middot;
        Generated: {{ $snapshot['generated_at'] }}
    </p>

    <div class="disclaimer">{{ $snapshot['disclaimer'] }}</div>

    <h2>Module scores (heuristic)</h2>
    <table>
        <thead>
            <tr><th>Module</th><th>Score</th><th>Methodology</th><th>Version</th></tr>
        </thead>
        <tbody>
            @foreach ($snapshot['scores'] as $score)
                <tr>
                    <td>{{ str_replace('_', ' ', $score['module']) }}</td>
                    <td>{{ $score['score'] === null ? 'n/a' : $score['score'].'/100' }}</td>
                    <td>{{ $score['methodology'] }}</td>
                    <td>{{ $score['version'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (count($snapshot['campaign_criteria']))
        <h2>Campaign readiness criteria</h2>
        <table>
            <thead><tr><th>Criterion</th><th>Score</th><th>Weight</th></tr></thead>
            <tbody>
                @foreach ($snapshot['campaign_criteria'] as $criterion)
                    <tr>
                        <td>{{ $criterion['label'] }}</td>
                        <td>{{ $criterion['score'] === null ? 'unknown' : $criterion['score'] }}</td>
                        <td>{{ $criterion['weight'] === null ? '-' : $criterion['weight'].'%' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (count($snapshot['technical_checks']))
        <h2>Technical audit checks</h2>
        <table>
            <thead><tr><th>Check</th><th>Status</th><th>Value</th></tr></thead>
            <tbody>
                @foreach ($snapshot['technical_checks'] as $check)
                    <tr>
                        <td>{{ $check['label'] }}</td>
                        <td class="prio-{{ $check['status'] === 'fail' ? 'high' : ($check['status'] === 'warn' ? 'medium' : 'low') }}">{{ $check['status'] }}</td>
                        <td>{{ $check['value'] ?? 'n/a' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (count($snapshot['recommendations']))
        <h2>Prioritised action plan (AI Marketing Advisor)</h2>
        <table>
            <thead><tr><th>#</th><th>Priority</th><th>Recommendation</th><th>Action</th><th>Source modules</th></tr></thead>
            <tbody>
                @foreach ($snapshot['recommendations'] as $item)
                    <tr>
                        <td>{{ $item['position'] }}</td>
                        <td class="prio-{{ $item['priority'] }}">{{ $item['priority'] }}</td>
                        <td>{{ $item['title'] }}</td>
                        <td>{{ $item['action'] ?? '-' }}</td>
                        <td class="muted">{{ $item['modules'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (count($snapshot['findings']))
        <h2>Findings and insights ({{ count($snapshot['findings']) }})</h2>
        <table>
            <thead><tr><th>Module</th><th>Priority</th><th>Finding</th><th>Recommendation</th></tr></thead>
            <tbody>
                @foreach (array_slice($snapshot['findings'], 0, 30) as $item)
                    <tr>
                        <td>{{ str_replace('_', ' ', $item['module']) }}</td>
                        <td class="prio-{{ $item['priority'] }}">{{ $item['priority'] }}</td>
                        <td>{{ $item['title'] }}</td>
                        <td>{{ $item['recommendation'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if (count($snapshot['findings']) > 30)
            <p class="muted">... {{ count($snapshot['findings']) - 30 }} more finding(s) omitted in print layout; see the CSV report for the full set.</p>
        @endif
    @endif

    <div class="disclaimer">
        Generated by AdPilot AI (report version {{ $snapshot['version'] }}). Scores are heuristic, evidence-based evaluations from crawl data only &mdash; they are not validated ML predictions, and this report never contains real advertising metrics (spend, CPC, CPA, conversion rate, revenue, ROAS).
    </div>
</body>
</html>
