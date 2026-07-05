<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_DELISTED = 'delisted';

    protected $fillable = [
        'user_id', 'type', 'slug', 'name', 'summary', 'description',
        'is_paid', 'purchase_url', 'status', 'downloads',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ItemVersion::class);
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(ItemScreenshot::class)->orderBy('position');
    }

    public function reviewNotes(): HasMany
    {
        return $this->hasMany(ReviewNote::class)->latest();
    }

    public function latestApprovedVersion(): ?ItemVersion
    {
        return $this->versions()
            ->where('status', ItemVersion::STATUS_APPROVED)
            ->get()
            ->sortByDesc(fn (ItemVersion $version) => $version->version, SORT_NATURAL)
            ->first();
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
