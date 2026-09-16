<div class="well well-sm" data-argument style="margin-bottom: 10px;">
    <div class="row">
        <div class="form-group col-sm-4">
            <label class="control-label">Key <span class="field-required"></span></label>
            <input type="text" name="arguments[{{ $index }}][key]" class="form-control input-sm" style="font-family: monospace;" value="{{ $argument['key'] ?? '' }}" placeholder="player" />
        </div>
        <div class="form-group col-sm-8">
            <label class="control-label">Title <span class="field-required"></span></label>
            <input type="text" name="arguments[{{ $index }}][label]" class="form-control input-sm" value="{{ $argument['label'] ?? '' }}" placeholder="Player name" />
        </div>
        <div class="form-group col-sm-12">
            <label class="control-label">Description</label>
            <input type="text" name="arguments[{{ $index }}][description]" class="form-control input-sm" value="{{ $argument['description'] ?? '' }}" />
        </div>
        <div class="form-group col-sm-4">
            <label class="control-label">Placeholder</label>
            <input type="text" name="arguments[{{ $index }}][placeholder]" class="form-control input-sm" value="{{ $argument['placeholder'] ?? '' }}" />
        </div>
        <div class="form-group col-sm-4">
            <label class="control-label">Default Value</label>
            <input type="text" name="arguments[{{ $index }}][default]" class="form-control input-sm" value="{{ $argument['default'] ?? '' }}" />
        </div>
        <div class="form-group col-sm-4">
            <label class="control-label">&nbsp;</label>
            <div class="checkbox" style="margin-top: 5px;">
                <input type="hidden" name="arguments[{{ $index }}][required]" value="0" />
                <label><input type="checkbox" name="arguments[{{ $index }}][required]" value="1" {{ !empty($argument['required']) ? 'checked' : '' }} /> Required</label>
            </div>
        </div>
    </div>
    <button type="button" class="btn btn-xs btn-danger" data-action="remove-argument"><i class="fa fa-trash-o"></i> Remove Argument</button>
</div>
