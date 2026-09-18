@php($rule = $rule ?? null)
<div class="form-group">
    <label>Label</label>
    <input type="text" name="label" class="form-control" value="{{ old('label', $rule->label ?? '') }}" required>
</div>
<div class="form-group">
    <label>Match type</label>
    <select name="match_type" class="form-control">
        @foreach (['word' => 'Whole word (recommended)', 'substring' => 'Substring (anywhere in the line)', 'regex' => 'Regex (advanced)'] as $value => $label)
            <option value="{{ $value }}" @selected(old('match_type', $rule->match_type ?? 'word') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="form-group">
    <label>Phrase / pattern</label>
    <input type="text" name="phrase" class="form-control" value="{{ old('phrase', $rule->phrase ?? '') }}" required>
    <p class="text-muted small">For "regex", include your own delimiters and flags, e.g. <code>/can.?t keep up/i</code>. Regex patterns are bounded by a backtrack limit but a bad pattern can still be slow — keep it simple.</p>
</div>
<div class="form-group">
    <label>Severity</label>
    <select name="severity" class="form-control">
        @foreach (['info' => 'Info', 'warning' => 'Warning', 'critical' => 'Critical'] as $value => $label)
            <option value="{{ $value }}" @selected(old('severity', $rule->severity ?? 'warning') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="checkbox">
    <label>
        <input type="checkbox" name="case_sensitive" value="1" @checked(old('case_sensitive', $rule->case_sensitive ?? false))>
        Case sensitive
    </label>
</div>
<div class="checkbox">
    <label>
        <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $rule->enabled ?? true))>
        Enabled
    </label>
</div>
<div class="form-group">
    <label>Notes</label>
    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $rule->notes ?? '') }}</textarea>
</div>
