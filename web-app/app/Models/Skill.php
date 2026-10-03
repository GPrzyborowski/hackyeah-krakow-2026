<?php

namespace App\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
     * Find a skill by name or synonym, creating it when it does not exist yet.
     */
    public static function findOrCreateByName(string $name): self
    {
        $name = trim($name);

        return self::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
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
