<?php

namespace Pterodactyl\Http\Requests\Admin\Egg;

use Illuminate\Validation\Validator;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class EggConsoleShortcutFormRequest extends AdminFormRequest
{
    /**
     * Define rules for validation of this request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:1|max:191',
            'description' => 'sometimes|nullable|string',
            'command' => 'required|string|max:2000',
            'sort_order' => 'sometimes|nullable|integer|min:0',
            'arguments' => 'sometimes|array',
            'arguments.*.key' => 'required|string|regex:/^[A-Za-z0-9_]{1,64}$/|distinct',
            'arguments.*.label' => 'required|string|max:191',
            'arguments.*.description' => 'nullable|string|max:191',
            'arguments.*.placeholder' => 'nullable|string|max:191',
            'arguments.*.default' => 'nullable|string|max:191',
            'arguments.*.required' => 'sometimes|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'arguments.*.key' => 'argument key',
            'arguments.*.label' => 'argument label',
            'arguments.*.description' => 'argument description',
            'arguments.*.placeholder' => 'argument placeholder',
            'arguments.*.default' => 'argument default value',
        ];
    }

    /**
     * Ensure every {{placeholder}} used in the command has a matching argument.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            preg_match_all('/{{\s*([A-Za-z0-9_]+)\s*}}/', (string) $this->input('command'), $matches);

            $keys = collect($this->input('arguments', []))->pluck('key')->filter()->all();
            foreach (array_unique($matches[1]) as $placeholder) {
                if (!in_array($placeholder, $keys, true)) {
                    $validator->errors()->add('command', "The command uses {{{$placeholder}}} but no argument with that key is defined.");
                }
            }
        });
    }

    /**
     * Returns the data to store for the shortcut.
     */
    public function shortcutData(): array
    {
        return [
            'name' => $this->input('name'),
            'description' => $this->input('description'),
            'command' => $this->input('command'),
            'sort_order' => (int) $this->input('sort_order', 0),
            'arguments' => collect($this->input('arguments', []))
                ->values()
                ->map(fn (array $argument) => [
                    'key' => $argument['key'],
                    'label' => $argument['label'],
                    'description' => $argument['description'] ?? null,
                    'placeholder' => $argument['placeholder'] ?? null,
                    'default' => $argument['default'] ?? null,
                    'required' => (bool) ($argument['required'] ?? false),
                ])
                ->all(),
        ];
    }
}
