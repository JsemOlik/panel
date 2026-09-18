<?php

namespace Pterodactyl\Http\Requests\Admin\Area;

use Illuminate\Validation\Validator;
use Pterodactyl\Models\Area;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class AreaServerFormRequest extends AdminFormRequest
{
    /**
     * Define rules for validation of this request.
     */
    public function rules(): array
    {
        return [
            'server_id' => $this->isMethod('POST')
                ? 'required|integer|exists:servers,id'
                : 'sometimes|integer|exists:servers,id',
            'role' => 'required|string|in:' . Area::ROLE_PROXY . ',' . Area::ROLE_MEMBER,
            'sort_order' => 'sometimes|nullable|integer|min:0',
        ];
    }

    /**
     * Ensure an area never ends up with more than one proxy server. The database has no
     * constraint for this (a partial unique index isn't portable across the databases this
     * panel supports), so it is enforced here instead.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('role') !== Area::ROLE_PROXY) {
                return;
            }

            /** @var \Pterodactyl\Models\Area $area */
            $area = $this->route('area');

            $query = DB::table('area_server')
                ->where('area_id', $area->id)
                ->where('role', Area::ROLE_PROXY);

            if ($pivot = $this->route('pivot')) {
                $query->where('id', '!=', $pivot);
            }

            if ($query->exists()) {
                $validator->errors()->add('role', 'This area already has a proxy server assigned. Remove it or change its role before assigning another.');
            }
        });
    }

    /**
     * Returns the pivot data to store/update for the area-server assignment.
     */
    public function serverData(): array
    {
        return [
            'role' => $this->input('role'),
            'sort_order' => (int) $this->input('sort_order', 0),
        ];
    }
}
