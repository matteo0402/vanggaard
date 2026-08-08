<?php

namespace App\Models;

use App\VocabularyName;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'name'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<CollectionItem, $this> */
    public function collectionItems(): BelongsToMany
    {
        return $this->belongsToMany(CollectionItem::class)
            ->withPivotValue('user_id', $this->user_id)
            ->withTimestamps();
    }

    /** @return Attribute<string, string> */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): array => [
                'name' => VocabularyName::display($value),
                'normalized_name' => VocabularyName::normalize($value),
            ],
        );
    }
}
