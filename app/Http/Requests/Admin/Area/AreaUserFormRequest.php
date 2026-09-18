<?php

namespace Pterodactyl\Http\Requests\Admin\Area;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class AreaUserFormRequest extends AdminFormRequest
{
    /**
     * Define rules for validation of this request.
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'grant_subuser_access' => 'sometimes|boolean',
        ];
    }
}
