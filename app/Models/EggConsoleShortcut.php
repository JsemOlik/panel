<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A button shown below a server's console that sends a predefined command. The command may contain
 * {{key}} placeholders, which the user fills in through the arguments before it is sent.
 *
 * @property int $id
 * @property int $egg_id
 * @property string $name
 * @property string|null $description
 * @property string $command
 * @property array<int, array{key: string, label: string, description: ?string, placeholder: ?string, default: ?string, required: bool}> $arguments
 * @property int $sort_order
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property Egg $egg
 */
class EggConsoleShortcut extends Model
{
    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'egg_console_shortcut';

    protected bool $immutableDates = true;

    protected $table = 'egg_console_shortcuts';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'egg_id' => 'integer',
        'arguments' => 'array',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'sort_order' => 0,
    ];

    public static array $validationRules = [
        'egg_id' => 'exists:eggs,id',
        'name' => 'required|string|between:1,191',
        'description' => 'nullable|string',
        'command' => 'required|string|max:2000',
        'arguments' => 'nullable|array',
        'sort_order' => 'integer|min:0',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\Egg, $this>
     */
    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }
}
