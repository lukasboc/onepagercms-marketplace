<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemVersion extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'item_id', 'version', 'zip_path', 'changelog',
        'requires_opcms', 'requires_php', 'status',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
