{{-- Form fields for creating or editing a console shortcut. Expects $shortcut (nullable). --}}
@php
    $arguments = $shortcut ? ($shortcut->arguments ?? []) : old('arguments', []);
@endphp
<div class="form-group">
    <label class="control-label">Name <span class="field-required"></span></label>
    <input type="text" name="name" class="form-control" value="{{ $shortcut ? $shortcut->name : old('name') }}" placeholder="Give item" />
    <p class="text-muted small">The text shown on the button.</p>
</div>
<div class="form-group">
    <label class="control-label">Description</label>
    <input type="text" name="description" class="form-control" value="{{ $shortcut ? $shortcut->description : old('description') }}" />
    <p class="text-muted small">Shown in the dialog when the shortcut has arguments, and as a tooltip otherwise.</p>
</div>
<div class="row">
    <div class="form-group col-md-9">
        <label class="control-label">Command <span class="field-required"></span></label>
        <input type="text" name="command" class="form-control" style="font-family: monospace;" value="{{ $shortcut ? $shortcut->command : old('command') }}" placeholder="give @{{player}} @{{item}} @{{amount}}" />
    </div>
    <div class="form-group col-md-3">
        <label class="control-label">Order</label>
        <input type="number" min="0" name="sort_order" class="form-control" value="{{ $shortcut ? $shortcut->sort_order : old('sort_order', 0) }}" />
    </div>
    <div class="col-xs-12">
        <p class="text-muted small">Sent to the server console when the button is clicked. Use <code>@{{key}}</code> to insert the value of an argument below. Shortcuts without arguments send the command straight away.</p>
    </div>
</div>
<div class="form-group" data-shortcut-arguments>
    <label class="control-label">Arguments</label>
    <div data-argument-list>
        @foreach($arguments as $index => $argument)
            @include('admin.eggs.partials.shortcut-argument', ['index' => $index, 'argument' => $argument])
        @endforeach
    </div>
    <template data-argument-template>
        @include('admin.eggs.partials.shortcut-argument', ['index' => '__INDEX__', 'argument' => []])
    </template>
    <button type="button" class="btn btn-xs btn-default" data-action="add-argument"><i class="fa fa-plus"></i> Add Argument</button>
</div>
