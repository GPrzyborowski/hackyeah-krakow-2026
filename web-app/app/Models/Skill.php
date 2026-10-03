<?php

namespace App\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property list<string>|null $synonyms
 */
#[Fillable(['name', 'slug', 'synonyms'])]
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    /**
     * Derive the slug from the name when none was given.
     */
    protected static function booted(): void
    {
        static::creating(function (Skill $skill): void {
            $skill->slug ??= Str::slug($skill->name);
        });
    }

    /**
     * Find a skill by name or synonym, creating it when it does not exist yet.
     */
    public static function findOrCreateByName(string $name): self
    {
        $name = trim($name);

        return self::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
    }

    /**
     * @return BelongsToMany<JobOffer, $this>
     */
    public function jobOffers(): BelongsToMany
    {
        return $this->belongsToMany(JobOffer::class)->withPivot('importance')->withTimestamps();
    }

    /**
     * Skills safe to suggest to other users: the curated dictionary (synonyms set, i.e. seeded) or skills used
     * by at least one offer. Free-text skills that candidates typed in themselves are never suggested to anyone.
     *
     * @param  Builder<Skill>  $query
     */
    public function scopeSuggestable(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNotNull('synonyms')->orWhereHas('jobOffers');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'synonyms' => 'array',
        ];
    }
}
