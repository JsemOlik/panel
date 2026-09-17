<?php

namespace Pterodactyl\Http\Requests\Admin;

use Pterodactyl\Models\OAuthProvider;

class OAuthProviderFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $rules = collect(OAuthProvider::$validationRules)->except(['uuid', 'logo'])->all();

        // The saved secret is kept when the field is left empty while editing.
        if ($this->route()->parameter('provider')) {
            $rules['client_secret'] = 'nullable|string';
        }

        return array_merge($rules, [
            'enabled' => 'required|boolean',
            'use_primary_color' => 'required|boolean',
            'use_pkce' => 'required|boolean',
            'link_by_email' => 'required|boolean',
            'allow_registration' => 'required|boolean',
            'sort_order' => 'required|integer|min:0',
            'logo_file' => 'nullable|file|max:512|extensions:svg,png,jpg,jpeg,webp',
            'remove_logo' => 'sometimes|boolean',
        ]);
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'Client ID',
            'client_secret' => 'Client Secret',
            'authorize_url' => 'Authorization URL',
            'token_url' => 'Token URL',
            'userinfo_url' => 'User Info URL',
            'identifier_field' => 'User ID Field',
            'email_field' => 'Email Field',
            'logo_file' => 'Logo',
        ];
    }

    /**
     * The validated attributes to save on the provider.
     */
    public function providerData(): array
    {
        $data = collect($this->validated())->except(['logo_file', 'remove_logo'])->all();

        if (empty($data['client_secret'])) {
            unset($data['client_secret']);
        }

        return $data;
    }
}
