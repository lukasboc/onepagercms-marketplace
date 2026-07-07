<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionCheck extends Model
{
    const CHECK_MANIFEST = 'manifest';

    const CHECK_HOOKS = 'hooks';

    const CHECK_UNINSTALL = 'uninstall';

    const CHECK_MALWARE = 'malware';

    const CHECK_FUNCTIONALITY = 'functionality';

    const CHECKS = [
        self::CHECK_MANIFEST,
        self::CHECK_HOOKS,
        self::CHECK_UNINSTALL,
        self::CHECK_MALWARE,
        self::CHECK_FUNCTIONALITY,
    ];

    const STATUS_PASSED = 'passed';

    const STATUS_WARNING = 'warning';

    const STATUS_FAILED = 'failed';

    const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'item_version_id', 'runner_id', 'check', 'status', 'findings',
    ];

    protected $casts = [
        'findings' => 'array',
    ];

    public function itemVersion(): BelongsTo
    {
        return $this->belongsTo(ItemVersion::class);
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'runner_id');
    }
}
