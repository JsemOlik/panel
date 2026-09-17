@extends('layouts.admin')

@section('title')
    Authentication
@endsection

@section('content-header')
    <h1>Authentication<small>Configure how users sign in to the Panel.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Authentication</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Password Login</h3>
                </div>
                <form action="{{ route('admin.authentication') }}" method="POST">
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">Status</label>
                                <div>
                                    <select class="form-control" name="password_login">
                                        <option value="1">Enabled</option>
                                        <option value="0" @if(!$passwordLogin) selected @endif>Disabled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <p class="text-muted small" style="margin-top: 25px;">
                                    When disabled, the username and password fields are hidden from the login page and password login and reset requests are rejected, so users can only sign in with the enabled OAuth providers. If a provider stops working, run <code>php artisan p:auth:password-login --enable</code> on the server to turn password login back on.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        {!! csrf_field() !!}
                        {!! method_field('PATCH') !!}
                        <button type="submit" class="btn btn-sm btn-primary pull-right">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">OAuth Providers</h3>
                    <div class="box-tools">
                        <a href="{{ route('admin.authentication.new') }}" class="btn btn-sm btn-primary">Create New</a>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <tbody>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Authorization URL</th>
                                <th class="text-center">Linked Accounts</th>
                                <th class="text-center">Registration</th>
                                <th class="text-center">Status</th>
                            </tr>
                            @forelse ($providers as $provider)
                                <tr>
                                    <td class="middle"><code>{{ $provider->id }}</code></td>
                                    <td class="middle">
                                        <span style="display: inline-block; width: 10px; height: 10px; margin-right: 6px; border-radius: 50%; background-color: {{ $provider->getDisplayColor() }};"></span>
                                        <a href="{{ route('admin.authentication.view', $provider->id) }}">{{ $provider->name }}</a>
                                    </td>
                                    <td class="middle"><code>{{ $provider->authorize_url }}</code></td>
                                    <td class="middle text-center">{{ $provider->identities_count }}</td>
                                    <td class="middle text-center">
                                        @if($provider->allow_registration)
                                            <span class="label label-warning">Allowed</span>
                                        @else
                                            <span class="label label-default">Linking only</span>
                                        @endif
                                    </td>
                                    <td class="middle text-center">
                                        @if($provider->enabled)
                                            <span class="label label-success">Enabled</span>
                                        @else
                                            <span class="label label-default">Disabled</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No OAuth providers have been configured yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
