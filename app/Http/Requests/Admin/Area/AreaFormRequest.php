<?php

namespace Pterodactyl\Http\Requests\Admin\Area;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class AreaFormRequest extends AdminFormRequest
{
    /**
     * Define rules for validation of this request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|between:1,191',
            'description' => 'sometimes|nullable|string',
        ];
    }

    /**
     * Returns the data to store for the area.
     */
    public function areaData(): array
    {
        return [
            'name' => $this->input('name'),
            'description' => $this->input('description'),
        ];
    }
}
