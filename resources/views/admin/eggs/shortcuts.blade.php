@extends('layouts.admin')

@section('title')
    Egg &rarr; {{ $egg->name }} &rarr; Console Shortcuts
@endsection

@section('content-header')
    <h1>{{ $egg->name }}<small>Managing console shortcuts for this Egg.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nests') }}">Nests</a></li>
        <li><a href="{{ route('admin.nests.view', $egg->nest->id) }}">{{ $egg->nest->name }}</a></li>
        <li><a href="{{ route('admin.nests.egg.view', $egg->id) }}">{{ $egg->name }}</a></li>
        <li class="active">Console Shortcuts</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nests.egg.view', $egg->id) }}">Configuration</a></li>
                <li><a href="{{ route('admin.nests.egg.variables', $egg->id) }}">Variables</a></li>
                <li><a href="{{ route('admin.nests.egg.scripts', $egg->id) }}">Install Script</a></li>
                <li class="active"><a href="{{ route('admin.nests.egg.shortcuts', $egg->id) }}">Console Shortcuts</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12">
        <div class="box no-border">
            <div class="box-body">
                <p class="text-muted pull-left" style="margin: 5px 0 0;">Buttons shown below the console of every server using this Egg. Users need the console permission to use them.</p>
                <a href="#" class="btn btn-sm btn-success pull-right" data-toggle="modal" data-target="#newShortcutModal">Create New Shortcut</a>
            </div>
        </div>
    </div>
</div>
<div class="row">
    @forelse($egg->consoleShortcuts as $shortcut)
        <div class="col-sm-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $shortcut->name }}</h3>
                </div>
                <form action="{{ route('admin.nests.egg.shortcuts.edit', ['egg' => $egg->id, 'shortcut' => $shortcut->id]) }}" method="POST">
                    <div class="box-body">
                        @include('admin.eggs.partials.shortcut-fields', ['shortcut' => $shortcut])
                    </div>
                    <div class="box-footer">
                        {!! csrf_field() !!}
                        <button class="btn btn-sm btn-primary pull-right" name="_method" value="PATCH" type="submit">Save</button>
                        <button class="btn btn-sm btn-danger pull-left muted muted-hover" data-action="delete" name="_method" value="DELETE" type="submit"><i class="fa fa-trash-o"></i></button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="col-xs-12">
            <div class="box">
                <div class="box-body text-center text-muted">This Egg has no console shortcuts yet.</div>
            </div>
        </div>
    @endforelse
</div>
<div class="modal fade" id="newShortcutModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Create New Console Shortcut</h4>
            </div>
            <form action="{{ route('admin.nests.egg.shortcuts', $egg->id) }}" method="POST">
                <div class="modal-body">
                    @include('admin.eggs.partials.shortcut-fields', ['shortcut' => null])
                </div>
                <div class="modal-footer">
                    {!! csrf_field() !!}
                    <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create Shortcut</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $('[data-action="delete"]').on('mouseenter', function () {
            $(this).find('i').html(' Delete Shortcut');
        }).on('mouseleave', function () {
            $(this).find('i').html('');
        });

        $(document).on('click', '[data-action="add-argument"]', function () {
            var container = $(this).closest('[data-shortcut-arguments]');
            var template = container.find('template[data-argument-template]').html();

            container.find('[data-argument-list]').append(template.replace(/__INDEX__/g, 'new' + Date.now()));
        });

        $(document).on('click', '[data-action="remove-argument"]', function () {
            $(this).closest('[data-argument]').remove();
        });

        @if($errors->any() && old('command') !== null && !request()->old('_method'))
            $('#newShortcutModal').modal('show');
        @endif
    </script>
@endsection
