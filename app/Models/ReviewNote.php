<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewNote extends Model
{
    protected $fillable = ['item_id', 'item_version_id', 'reviewer_id', 'action', 'note'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ItemVersion::class, 'item_version_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
