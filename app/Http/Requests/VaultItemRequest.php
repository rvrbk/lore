<?php

namespace App\Http\Requests;

use App\Models\VaultItem;
use Illuminate\Foundation\Http\FormRequest;

class VaultItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof VaultItem ? $this->user()->can('update', $item) : true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'vault_username' => ['nullable', 'string', 'max:1000'],
            // On edit, an empty password means "keep the one I have".
            'password' => [$this->route('item') instanceof VaultItem ? 'nullable' : 'required', 'string', 'max:4096'],
            'vault_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * The item's columns. The form fields for the username and notes have their own names (see bootstrap/app.php) so that a
     * failed form is never flashed back into the session, where they would sit in plain text.
     *
     * @return array{name: string, url: ?string, username: ?string, password?: string, notes: ?string}
     */
    public function itemData(): array
    {
        $data = $this->validated();
        $item = ['name' => $data['name'], 'url' => $data['url'] ?? null, 'username' => $data['vault_username'] ?? null, 'notes' => $data['vault_notes'] ?? null];

        return ($data['password'] ?? '') === '' ? $item : $item + ['password' => $data['password']];
    }
}
