@extends('layouts.admin')

@section('title')
    Areas &rarr; New
@endsection

@section('content-header')
    <h1>New Area<small>Create a new area to group servers together.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.areas') }}">Areas</a></li>
        <li class="active">New</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.areas.new') }}" method="POST">
    <div class="row">
        <div class="col-sm-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Basic Details</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pName" class="form-label">Name</label>
                        <input type="text" name="name" id="pName" class="form-control" value="{{ old('name') }}" />
                        <p class="text-muted small">A short, descriptive name for this area, e.g. <code>Lobby Cluster A</code>.</p>
                    </div>
                    <div class="form-group">
                        <label for="pDescription" class="form-label">Description</label>
                        <textarea name="description" id="pDescription" rows="4" class="form-control">{{ old('description') }}</textarea>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-success pull-right">Create Area</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
