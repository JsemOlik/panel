@extends('layouts.admin')

@php
    $value = fn (string $field, $default = null) => old($field, $provider ? $provider->{$field} : $default);
    $bool = fn (string $field, bool $default) => (bool) old($field, $provider ? $provider->{$field} : $default);
@endphp

@section('title')
    {{ $provider ? $provider->name : 'New OAuth Provider' }}
@endsection

@section('content-header')
    <h1>{{ $provider ? $provider->name : 'New OAuth Provider' }}<small>{{ $provider ? 'Edit the OAuth provider.' : 'Add an OAuth 2 provider users can sign in with.' }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.authentication') }}">Authentication</a></li>
        <li class="active">{{ $provider ? $provider->name : 'New' }}</li>
    </ol>
@endsection

@section('content')
    <form action="{{ $provider ? route('admin.authentication.view', $provider->id) : route('admin.authentication.new') }}" method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">Login Button</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label for="pName" class="control-label">Title</label>
                            <input type="text" id="pName" name="name" class="form-control" required value="{{ $value('name') }}" placeholder="4CAMPS SSO">
                            <p class="text-muted small">Shown on the login page as "Pokračovat přes …" and in the users' linked accounts.</p>
                        </div>
                        <div class="row">
                            <div class="form-group col-xs-6">
                                <label for="pColor" class="control-label">Color</label>
                                <input type="color" id="pColor" name="color" class="form-control" value="{{ $value('color', '#2563eb') }}" style="padding: 2px 4px; @if($bool('use_primary_color', false)) opacity: .5; pointer-events: none; @endif">
                                <input type="hidden" name="use_primary_color" value="0">
                                <div class="checkbox checkbox-primary no-margin-bottom">
                                    <input type="checkbox" id="pUsePrimaryColor" name="use_primary_color" value="1" @if($bool('use_primary_color', false)) checked @endif>
                                    <label for="pUsePrimaryColor">Use the primary color</label>
                                </div>
                            </div>
                            <div class="form-group col-xs-6">
                                <label for="pSortOrder" class="control-label">Order</label>
                                <input type="number" id="pSortOrder" name="sort_order" min="0" class="form-control" value="{{ $value('sort_order', 0) }}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="pLogo" class="control-label">Logo</label>
                            <input type="file" id="pLogo" name="logo_file" accept=".svg,.png,.jpg,.jpeg,.webp">
                            <p class="text-muted small">An SVG, PNG, JPG or WebP image up to 512 KB, displayed at 20×20 pixels. Prefer a white or transparent logo, it is shown on the button color.</p>
                            @if($provider && $provider->logo)
                                <div class="checkbox checkbox-primary no-margin-bottom">
                                    <input type="checkbox" id="pRemoveLogo" name="remove_logo" value="1">
                                    <label for="pRemoveLogo">Remove the current logo</label>
                                </div>
                            @endif
                        </div>
                        <label class="control-label">Preview</label>
                        <div style="background: #050a17; padding: 16px; border-radius: 6px;">
                            <div id="buttonPreview" style="display: flex; align-items: center; justify-content: center; gap: 8px; height: 40px; border-radius: 8px; color: #fff; font-size: 14px; font-weight: 500; background-color: {{ $bool('use_primary_color', false) ? \Pterodactyl\Models\OAuthProvider::DEFAULT_PRIMARY_COLOR : $value('color', '#2563eb') }};">
                                <img id="logoPreview" alt="" style="width: 20px; height: 20px; object-fit: contain; @if(!($provider && $provider->logo)) display: none; @endif" src="{{ $provider && $provider->logo ? route('admin.authentication.logo', $provider->id) : '' }}">
                                <span>Pokračovat přes <span id="namePreview">{{ $value('name', '…') }}</span></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">Status &amp; Accounts</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label">Status</label>
                            <select name="enabled" class="form-control">
                                <option value="0">Disabled</option>
                                <option value="1" @if($bool('enabled', false)) selected @endif>Enabled</option>
                            </select>
                            <p class="text-muted small">Disabled providers are hidden from the login page and can't be used to sign in.</p>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Link by Email</label>
                            <select name="link_by_email" class="form-control">
                                <option value="0">Disabled</option>
                                <option value="1" @if($bool('link_by_email', false)) selected @endif>Enabled</option>
                            </select>
                            <p class="text-muted small">Links an OAuth account to the existing user with the same email address the first time it's used. Only enable this if the provider verifies email addresses. When disabled, users link the provider from their account settings.</p>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Account Creation</label>
                            <select name="allow_registration" class="form-control">
                                <option value="0">Disabled</option>
                                <option value="1" @if($bool('allow_registration', false)) selected @endif>Enabled</option>
                            </select>
                            <p class="text-muted small">Creates a new, non-admin Panel user when someone signs in with an account that isn't linked to anyone.</p>
                        </div>
                        <div class="form-group no-margin-bottom">
                            <label for="pAllowedDomains" class="control-label">Allowed Email Domains <span class="field-optional"></span></label>
                            <input type="text" id="pAllowedDomains" name="allowed_domains" class="form-control" value="{{ $value('allowed_domains') }}" placeholder="4camps.cz, example.com">
                            <p class="text-muted small">Limits linking by email and account creation to these domains. Leave empty to allow any domain.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">OAuth 2 Configuration</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label">Callback URL</label>
                            @if($provider)
                                <input type="text" class="form-control" readonly value="{{ $provider->getCallbackUrl() }}" onclick="this.select()">
                                <p class="text-muted small">Register this redirect URI with the provider.</p>
                            @else
                                <p class="text-muted small no-margin">The callback URL to register with the provider is shown after the provider is created.</p>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="pClientId" class="control-label">Client ID</label>
                            <input type="text" id="pClientId" name="client_id" class="form-control" required value="{{ $value('client_id') }}">
                        </div>
                        <div class="form-group">
                            <label for="pClientSecret" class="control-label">Client Secret</label>
                            <input type="password" id="pClientSecret" name="client_secret" class="form-control" autocomplete="new-password" @if(!$provider) required @endif>
                            @if($provider)
                                <p class="text-muted small">Leave empty to keep the current secret.</p>
                            @endif
                        </div>
                        <div class="form-group">
                            <label for="pAuthorizeUrl" class="control-label">Authorization URL</label>
                            <input type="url" id="pAuthorizeUrl" name="authorize_url" class="form-control" required value="{{ $value('authorize_url') }}" placeholder="https://sso.example.com/oauth/authorize">
                        </div>
                        <div class="form-group">
                            <label for="pTokenUrl" class="control-label">Token URL</label>
                            <input type="url" id="pTokenUrl" name="token_url" class="form-control" required value="{{ $value('token_url') }}" placeholder="https://sso.example.com/oauth/token">
                        </div>
                        <div class="form-group">
                            <label for="pUserinfoUrl" class="control-label">User Info URL</label>
                            <input type="url" id="pUserinfoUrl" name="userinfo_url" class="form-control" required value="{{ $value('userinfo_url') }}" placeholder="https://sso.example.com/api/user">
                            <p class="text-muted small">Requested with the access token to get the signed-in user's details as JSON.</p>
                        </div>
                        <div class="form-group">
                            <label for="pScopes" class="control-label">Scopes <span class="field-optional"></span></label>
                            <input type="text" id="pScopes" name="scopes" class="form-control" value="{{ $value('scopes', 'openid profile email') }}">
                            <p class="text-muted small">Separated by spaces.</p>
                        </div>
                        <div class="row">
                            <div class="form-group col-xs-6">
                                <label class="control-label">Client Authentication</label>
                                <select name="token_auth_method" class="form-control">
                                    <option value="client_secret_post">In the request body</option>
                                    <option value="client_secret_basic" @if($value('token_auth_method') === 'client_secret_basic') selected @endif>HTTP Basic</option>
                                </select>
                            </div>
                            <div class="form-group col-xs-6">
                                <label class="control-label">PKCE</label>
                                <select name="use_pkce" class="form-control">
                                    <option value="1">Enabled</option>
                                    <option value="0" @if(!$bool('use_pkce', true)) selected @endif>Disabled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">User Info Fields</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted small">The fields of the user info response to read the user's details from. Use dots for nested fields, e.g. <code>data.email</code>.</p>
                        <div class="row">
                            <div class="form-group col-xs-6">
                                <label for="pIdentifierField" class="control-label">User ID</label>
                                <input type="text" id="pIdentifierField" name="identifier_field" class="form-control" required value="{{ $value('identifier_field', 'sub') }}">
                            </div>
                            <div class="form-group col-xs-6">
                                <label for="pEmailField" class="control-label">Email</label>
                                <input type="text" id="pEmailField" name="email_field" class="form-control" required value="{{ $value('email_field', 'email') }}">
                            </div>
                            <div class="form-group col-xs-4 no-margin-bottom">
                                <label for="pUsernameField" class="control-label">Username <span class="field-optional"></span></label>
                                <input type="text" id="pUsernameField" name="username_field" class="form-control" value="{{ $value('username_field', 'preferred_username') }}">
                            </div>
                            <div class="form-group col-xs-4 no-margin-bottom">
                                <label for="pFirstNameField" class="control-label">First Name <span class="field-optional"></span></label>
                                <input type="text" id="pFirstNameField" name="first_name_field" class="form-control" value="{{ $value('first_name_field', 'given_name') }}">
                            </div>
                            <div class="form-group col-xs-4 no-margin-bottom">
                                <label for="pLastNameField" class="control-label">Last Name <span class="field-optional"></span></label>
                                <input type="text" id="pLastNameField" name="last_name_field" class="form-control" value="{{ $value('last_name_field', 'family_name') }}">
                            </div>
                        </div>
                        <p class="text-muted small" style="margin-top: 10px;">If the response has an <code>email_verified</code> field set to false, the email is not used for linking or account creation.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-primary">
                    <div class="box-footer">
                        {!! csrf_field() !!}
                        @if($provider)
                            {!! method_field('PATCH') !!}
                            <button type="button" class="btn btn-sm btn-danger pull-left muted muted-hover" data-action="delete"><i class="fa fa-trash-o"></i></button>
                        @endif
                        <button type="submit" class="btn btn-sm btn-primary pull-right">{{ $provider ? 'Save' : 'Create Provider' }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    @if($provider)
        <form id="deleteProviderForm" action="{{ route('admin.authentication.delete', $provider->id) }}" method="POST">
            {!! csrf_field() !!}
            {!! method_field('DELETE') !!}
        </form>
    @endif
@endsection

@section('footer-scripts')
    @parent
    <script>
        $('#pName').on('input', function () {
            $('#namePreview').text($(this).val() || '…');
        });
        function updateButtonColor() {
            const usePrimary = $('#pUsePrimaryColor').is(':checked');

            $('#pColor').css({ opacity: usePrimary ? 0.5 : 1, 'pointer-events': usePrimary ? 'none' : '' });
            $('#buttonPreview').css('background-color', usePrimary ? '{{ \Pterodactyl\Models\OAuthProvider::DEFAULT_PRIMARY_COLOR }}' : $('#pColor').val());
        }

        $('#pColor').on('input', updateButtonColor);
        $('#pUsePrimaryColor').on('change', updateButtonColor);
        $('#pLogo').on('change', function () {
            const file = this.files[0];
            if (file) {
                $('#logoPreview').attr('src', URL.createObjectURL(file)).show();
            }
        });
        $('[data-action="delete"]').on('click', function () {
            swal({
                type: 'error',
                title: 'Delete OAuth provider?',
                text: 'Users will no longer be able to sign in with it, and all accounts linked to it will be unlinked.',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#d9534f',
                closeOnConfirm: false,
            }, function () {
                $('#deleteProviderForm').submit();
            });
        });
    </script>
@endsection
