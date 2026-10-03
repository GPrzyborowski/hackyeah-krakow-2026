<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\CompanyReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property int $rating_return
 * @property int $rating_flexibility
 * @property int $rating_no_pregnancy_questions
 * @property string|null $quote
 * @property string|null $author_label
 * @property ReviewStatus $status
 */
#[Fillable([
    'company_id', 'user_id', 'rating_return', 'rating_flexibility', 'rating_no_pregnancy_questions',
    'quote', 'author_label', 'status',
])]
class CompanyReview extends Model
{
    /** @use HasFactory<CompanyReviewFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function overallRating(): float
    {
        return ($this->rating_return + $this->rating_flexibility + $this->rating_no_pregnancy_questions) / 3;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
        ];
    }
}
