<?php

namespace App\Models;

use Database\Factories\InstallationSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationSetting extends Model
{
    /** @use HasFactory<InstallationSettingFactory> */
    use HasFactory;

    public const PRIMARY_KEY = 'primary';

    /** @var list<string> */
    protected $fillable = [
        'key',
        'company_name',
        'default_locale',
        'storage_disk',
        'completed_at',
        'completed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
