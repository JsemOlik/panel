@extends('layouts.admin')

@section('title')
    Areas &rarr; View &rarr; {{ $area->name }}
@endsection

@section('content-header')
    <h1>{{ $area->name }}<small>{{ str_limit($area->description ?? 'Managing this area.', 75) }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.areas') }}">Areas</a></li>
        <li class="active">{{ $area->name }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-sm-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Area Details</h3>
            </div>
            <form action="{{ route('admin.areas.view', $area->id) }}" method="POST">
                <div class="box-body">
                    <div class="form-group">
                        <label for="pName" class="form-label">Name</label>
                        <input type="text" id="pName" name="name" class="form-control" value="{{ old('name', $area->name) }}" />
                    </div>
                    <div class="form-group">
                        <label for="pDescription" class="form-label">Description</label>
                        <textarea id="pDescription" name="description" class="form-control" rows="4">{{ old('description', $area->description) }}</textarea>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    {!! method_field('PATCH') !!}
                    <button class="btn btn-sm btn-primary pull-right">Save</button>
                </div>
            </form>
        </div>
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">Delete Area</h3>
            </div>
            <div class="box-body">
                <p class="text-muted small">Deleting an area only removes the grouping — its member servers, their data, and any subuser access granted to staff are left untouched.</p>
            </div>
            <form action="{{ route('admin.areas.view', $area->id) }}" method="POST">
                <div class="box-footer">
                    {!! csrf_field() !!}
                    {!! method_field('DELETE') !!}
                    <button class="btn btn-sm btn-danger pull-right">Delete Area</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Servers</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tr>
                        <th>Server</th>
                        <th>Node</th>
                        <th>Role</th>
                        <th class="text-center">Order</th>
                        <th></th>
                    </tr>
                    @forelse($area->servers as $server)
                        <tr>
                            <td><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></td>
                            <td>{{ $server->node->name }}</td>
                            <td>
                                <form action="{{ route('admin.areas.view.servers', ['area' => $area->id, 'pivot' => $server->pivot->id]) }}" method="POST">
                                    {!! csrf_field() !!}
                                    {!! method_field('PATCH') !!}
                                    <select name="role" onchange="this.form.submit()" class="form-control input-sm">
                                        <option value="member" {{ $server->pivot->role !== 'member' ?: 'selected' }}>Member</option>
                                        <option value="proxy" {{ $server->pivot->role !== 'proxy' ?: 'selected' }}>Proxy</option>
                                    </select>
                                    <input type="hidden" name="sort_order" value="{{ $server->pivot->sort_order }}" />
                                </form>
                            </td>
                            <td class="text-center">{{ $server->pivot->sort_order }}</td>
                            <td class="text-right">
                                <form action="{{ route('admin.areas.view.servers', ['area' => $area->id, 'pivot' => $server->pivot->id]) }}" method="POST" onsubmit="return confirm('Remove this server from the area?');">
                                    {!! csrf_field() !!}
                                    {!! method_field('DELETE') !!}
                                    <button class="btn btn-xs btn-danger"><i class="fa fa-trash-o"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">No servers have been added to this area yet.</td>
                        </tr>
                    @endforelse
                </table>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.areas.servers', $area->id) }}" method="POST" class="form-inline">
                    {!! csrf_field() !!}
                    <div class="form-group">
                        <select name="server_id" class="form-control" style="width: 220px;">
                            @foreach($servers as $server)
                                <option value="{{ $server->id }}">{{ $server->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="role" class="form-control">
                            <option value="member">Member</option>
                            <option value="proxy">Proxy</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="number" name="sort_order" class="form-control" placeholder="Order" value="0" style="width: 80px;" min="0" />
                    </div>
                    <button type="submit" class="btn btn-sm btn-success">Add Server</button>
                </form>
            </div>
        </div>
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Staff</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th></th>
                    </tr>
                    @forelse($area->staff as $staffMember)
                        <tr>
                            <td><a href="{{ route('admin.users.view', $staffMember->id) }}">{{ $staffMember->username }}</a></td>
                            <td>{{ $staffMember->email }}</td>
                            <td class="text-right">
                                <form action="{{ route('admin.areas.view.staff', ['area' => $area->id, 'user' => $staffMember->id]) }}" method="POST" onsubmit="return confirm('Remove this staff member from the area?');">
                                    {!! csrf_field() !!}
                                    {!! method_field('DELETE') !!}
                                    <button class="btn btn-xs btn-danger"><i class="fa fa-trash-o"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">No staff have been assigned to this area yet.</td>
                        </tr>
                    @endforelse
                </table>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.areas.staff', $area->id) }}" method="POST" class="form-inline">
                    {!! csrf_field() !!}
                    <div class="form-group">
                        <select name="user_id" class="form-control" style="width: 220px;">
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->username }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="grant_subuser_access" value="1" /> Also grant console/start/stop/restart access to this area's member servers
                        </label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-success">Assign Staff</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
