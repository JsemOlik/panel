<?php

namespace Pterodactyl\Http\Requests\Admin\Alerts;

use Illuminate\Validation\Validator;
use Pterodactyl\Models\KeywordAlertRule;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class KeywordAlertRuleFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'label' => 'required|string|min:1|max:191',
            'phrase' => 'required|string|max:' . (int) config('keyword_alerts.regex.max_pattern_length', 500),
            'match_type' => 'required|string|in:substring,word,regex',
            'severity' => 'required|string|in:info,warning,critical',
            'case_sensitive' => 'sometimes|boolean',
            'enabled' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string|max:2000',
        ];
    }

    /**
     * A regex rule must compile before it is saved. This is the rule-creation-time half of the
     * ReDoS mitigation documented in KeywordAlertMatchingService — the runtime backtrack-limit
     * guard is the other half, and this doesn't replace it (a pattern can compile fine and still
     * be pathological against the right input), but it does catch plain syntax errors and gives
     * the admin immediate feedback instead of a silently-skipped rule discovered later via logs.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('match_type') !== KeywordAlertRule::MATCH_REGEX) {
                return;
            }

            $pattern = (string) $this->input('phrase');
            $result = @preg_match($pattern, '');

            if ($result === false || preg_last_error() !== PREG_NO_ERROR) {
                $validator->errors()->add('phrase', 'This does not compile as a valid PCRE pattern: ' . preg_last_error_msg());
            }
        });
    }

    /**
     * Returns the data to store/update for the rule.
     */
    public function ruleData(): array
    {
        return [
            'label' => $this->input('label'),
            'phrase' => $this->input('phrase'),
            'match_type' => $this->input('match_type'),
            'severity' => $this->input('severity'),
            'case_sensitive' => (bool) $this->input('case_sensitive', false),
            'enabled' => (bool) $this->input('enabled', true),
            'notes' => $this->input('notes'),
        ];
    }
}
