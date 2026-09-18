@extends('layouts.admin')

@section('title')
    Areas
@endsection

@section('content-header')
    <h1>Areas<small>Groups of servers (game servers plus an optional proxy) managed together.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Areas</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Area List</h3>
                <div class="box-tools">
                    <a href="{{ route('admin.areas.new') }}"><button type="button" class="btn btn-sm btn-primary">Create New</button></a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th class="text-center">Servers</th>
                            <th class="text-center">Staff</th>
                        </tr>
                        @forelse ($areas as $area)
                            <tr>
                                <td><a href="{{ route('admin.areas.view', $area->id) }}">{{ $area->name }}</a></td>
                                <td>{{ str_limit($area->description ?? '', 75) }}</td>
                                <td class="text-center">{{ $area->servers_count }}</td>
                                <td class="text-center">{{ $area->staff_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No areas have been created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($areas->hasPages())
                <div class="box-footer with-border">
                    <div class="col-md-12 text-center">{!! $areas->render() !!}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
