@extends('layouts.admin')

@section('title')
    Keyword Alerts
@endsection

@section('content-header')
    <h1>Keyword Alerts<small>Triggered matches against configured keyword alert rules.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Keyword Alerts</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li class="active"><a href="{{ route('admin.keyword-alerts') }}">Alert Inbox</a></li>
                <li><a href="{{ route('admin.keyword-alerts.rules') }}">Rules</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12">
        <div class="box no-border">
            <div class="box-body">
                <p class="text-muted pull-left" style="margin: 5px 0 0;">
                    This is flagged children's chat/console output. Access is restricted to root admins.
                    Repeated matches of the same rule on the same server within a short window are folded
                    into one row (see "Occurrences") rather than creating a new alert each time.
                </p>
                <a href="{{ route('admin.keyword-alerts') }}?status=open" class="btn btn-sm btn-default pull-right">Open only</a>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>When</th>
                    <th>Rule</th>
                    <th>Server</th>
                    <th>Player</th>
                    <th>Matched</th>
                    <th>Occurrences</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($alerts as $alert)
                    <tr>
                        <td>{{ $alert->last_seen_at->diffForHumans() }}</td>
                        <td>{{ $alert->rule->label ?? 'Deleted rule' }}</td>
                        <td>{{ $alert->server->name ?? '#' . $alert->server_id }}</td>
                        <td>{{ $alert->player ?? '—' }}</td>
                        <td><code>{{ $alert->matched_text }}</code> — <span class="text-muted">{{ \Illuminate\Support\Str::limit($alert->line, 80) }}</span></td>
                        <td>{{ $alert->occurrence_count }}</td>
                        <td>
                            <span class="label label-{{ $alert->severity === 'critical' ? 'danger' : ($alert->severity === 'warning' ? 'warning' : 'default') }}">
                                {{ strtoupper($alert->severity) }}
                            </span>
                        </td>
                        <td>{{ ucfirst($alert->status) }}</td>
                        <td class="text-right">
                            @if ($alert->status === 'open')
                                <form action="{{ route('admin.keyword-alerts.resolve', $alert->id) }}" method="POST" style="display:inline;">
                                    {!! csrf_field() !!}
                                    <button type="submit" class="btn btn-xs btn-default">Mark resolved</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted">No keyword alerts triggered yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
            <div class="box-footer">
                {{ $alerts->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
