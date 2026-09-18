@extends('layouts.admin')

@section('title')
    Manage User: {{ $user->username }}
@endsection

@section('content-header')
    <h1>{{ $user->name_first }} {{ $user->name_last}}<small>{{ $user->username }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.users') }}">Users</a></li>
        <li class="active">{{ $user->username }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <form action="{{ route('admin.users.view', $user->id) }}" method="post">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Identity</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="email" class="control-label">Email</label>
                        <div>
                            <input type="email" name="email" value="{{ $user->email }}" class="form-control form-autocomplete-stop">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="registered" class="control-label">Username</label>
                        <div>
                            <input type="text" name="username" value="{{ $user->username }}" class="form-control form-autocomplete-stop">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="registered" class="control-label">Client First Name</label>
                        <div>
                            <input type="text" name="name_first" value="{{ $user->name_first }}" class="form-control form-autocomplete-stop">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="registered" class="control-label">Client Last Name</label>
                        <div>
                            <input type="text" name="name_last" value="{{ $user->name_last }}" class="form-control form-autocomplete-stop">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Default Language</label>
                        <div>
                            <select name="language" class="form-control">
                                @foreach($languages as $key => $value)
                                    <option value="{{ $key }}" @if($user->language === $key) selected @endif>{{ $value }}</option>
                                @endforeach
                            </select>
                            <p class="text-muted"><small>The default language to use when rendering the Panel for this user.</small></p>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    {!! method_field('PATCH') !!}
                    <input type="submit" value="Update User" class="btn btn-primary btn-sm">
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Password</h3>
                </div>
                <div class="box-body">
                    <div class="alert alert-success" style="display:none;margin-bottom:10px;" id="gen_pass"></div>
                    <div class="form-group no-margin-bottom">
                        <label for="password" class="control-label">Password <span class="field-optional"></span></label>
                        <div>
                            <input type="password" id="password" name="password" class="form-control form-autocomplete-stop">
                            <p class="text-muted small">Leave blank to keep this user's password the same. User will not receive any notification if password is changed.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Permissions</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="root_admin" class="control-label">Administrator</label>
                        <div>
                            <select name="root_admin" class="form-control">
                                <option value="0">@lang('strings.no')</option>
                                <option value="1" {{ $user->root_admin ? 'selected="selected"' : '' }}>@lang('strings.yes')</option>
                            </select>
                            <p class="text-muted"><small>Setting this to 'Yes' gives a user full administrative access.</small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Connected Accounts</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                @if($oauthProviders->isEmpty())
                    <p class="text-muted" style="padding: 15px;">
                        No OAuth providers are configured.
                        <a href="{{ route('admin.authentication') }}">Add one</a> to allow signing in with an external account.
                    </p>
                @else
                    <table class="table table-hover">
                        <tbody>
                            <tr>
                                <th>Provider</th>
                                <th>Status</th>
                                <th>Account</th>
                                <th>Last Used</th>
                            </tr>
                            {{-- Iterates providers, not identities: an unlinked provider is a row worth
                                 seeing, since it is the reason a user cannot sign in with it. --}}
                            @foreach($oauthProviders as $provider)
                                @php($identity = $oauthIdentities->get($provider->id))
                                <tr>
                                    <td>
                                        @if($provider->getLogoUrl())
                                            <img src="{{ $provider->getLogoUrl() }}" alt="" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;" />
                                        @else
                                            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:8px;background:{{ $provider->getDisplayColor() }};"></span>
                                        @endif
                                        <a href="{{ route('admin.authentication.view', $provider->id) }}">{{ $provider->name }}</a>
                                        @unless($provider->enabled)
                                            {{-- Surfaced because a disabled provider still keeps its existing
                                                 links, which would otherwise read as working sign-in. --}}
                                            <span class="label label-default">Disabled</span>
                                        @endunless
                                    </td>
                                    <td>
                                        @if($identity)
                                            <span class="label label-success">Linked</span>
                                        @else
                                            <span class="label label-default">Not linked</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($identity)
                                            {{ $identity->email ?: '—' }}
                                            <br />
                                            <small class="text-muted">ID: {{ $identity->provider_user_id }}</small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($identity && $identity->last_used_at)
                                            <span title="{{ $identity->last_used_at->toDayDateTimeString() }}">{{ $identity->last_used_at->diffForHumans() }}</span>
                                        @elseif($identity)
                                            <span class="text-muted">Never</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
    <div class="col-xs-12">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">Delete User</h3>
            </div>
            <div class="box-body">
                <p class="no-margin">There must be no servers associated with this account in order for it to be deleted.</p>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.users.view', $user->id) }}" method="POST">
                    {!! csrf_field() !!}
                    {!! method_field('DELETE') !!}
                    <input id="delete" type="submit" class="btn btn-sm btn-danger pull-right" {{ $user->servers->count() < 1 ?: 'disabled' }} value="Delete User" />
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
