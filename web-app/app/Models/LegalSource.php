<?php

namespace App\Models;

use Database\Factories\LegalSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $act
 * @property string $article
 * @property string $title
 * @property string $content
 * @property list<string>|null $keywords
 */
#[Fillable(['act', 'article', 'title', 'content', 'keywords'])]
class LegalSource extends Model
{
    /** @use HasFactory<LegalSourceFactory> */
    use HasFactory;

    /**
     * Citation label, e.g. "Kodeks pracy, art. 22¹".
     */
    public function label(): string
    {
        return $this->act.', art. '.$this->article;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'keywords' => 'array',
        ];
    }
}
