@extends('layouts.admin')

@section('title')
    Keyword Alerts &rarr; Rules
@endsection

@section('content-header')
    <h1>Keyword Alert Rules<small>Terms/phrases scanned against every server's captured console and chat.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.keyword-alerts') }}">Keyword Alerts</a></li>
        <li class="active">Rules</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.keyword-alerts') }}">Alert Inbox</a></li>
                <li class="active"><a href="{{ route('admin.keyword-alerts.rules') }}">Rules</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12">
        <div class="box no-border">
            <div class="box-body">
                <p class="text-muted pull-left" style="margin: 5px 0 0;">
                    Rules are global — they apply to every server. Word/substring rules also match a small set
                    of leetspeak substitutions (@ 4 3 1 ! 0 5 $ 7). This does not catch spaced-out letters,
                    homoglyphs, or slang not in the phrase — use a regex rule for those, and see the tooltip
                    below for the safety limits on regex rules.
                </p>
                <a href="#" class="btn btn-sm btn-success pull-right" data-toggle="modal" data-target="#newRuleModal">Create New Rule</a>
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
                    <th>Label</th>
                    <th>Match type</th>
                    <th>Phrase / pattern</th>
                    <th>Severity</th>
                    <th>Enabled</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($rules as $rule)
                    <tr>
                        <td>{{ $rule->label }}</td>
                        <td><code>{{ $rule->match_type }}</code></td>
                        <td><code>{{ $rule->phrase }}</code></td>
                        <td>
                            <span class="label label-{{ $rule->severity === 'critical' ? 'danger' : ($rule->severity === 'warning' ? 'warning' : 'default') }}">
                                {{ strtoupper($rule->severity) }}
                            </span>
                        </td>
                        <td>{!! $rule->enabled ? '<i class="fa fa-check text-green"></i>' : '<i class="fa fa-close text-red"></i>' !!}</td>
                        <td class="text-right">
                            <a href="#" data-toggle="modal" data-target="#editRuleModal{{ $rule->id }}">Edit</a>
                        </td>
                    </tr>
                    <div class="modal fade" id="editRuleModal{{ $rule->id }}" tabindex="-1" role="dialog">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <form action="{{ route('admin.keyword-alerts.rules.edit', $rule->id) }}" method="POST">
                                    <div class="modal-header">
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                        <h4 class="modal-title">Edit "{{ $rule->label }}"</h4>
                                    </div>
                                    <div class="modal-body">
                                        @include('admin.keyword-alerts.rules.partials.fields', ['rule' => $rule])
                                    </div>
                                    <div class="modal-footer">
                                        {!! csrf_field() !!}
                                        <input type="hidden" name="_method" value="PATCH" />
                                        <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </div>
                                </form>
                                <form action="{{ route('admin.keyword-alerts.rules.edit', $rule->id) }}" method="POST" style="display:inline;">
                                    {!! csrf_field() !!}
                                    <input type="hidden" name="_method" value="DELETE" />
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">No keyword alert rules configured yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal fade" id="newRuleModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.keyword-alerts.rules') }}" method="POST">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Create New Rule</h4>
                </div>
                <div class="modal-body">
                    @include('admin.keyword-alerts.rules.partials.fields', ['rule' => null])
                </div>
                <div class="modal-footer">
                    {!! csrf_field() !!}
                    <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
