export type ReviewStatus = 'pending' | 'approved' | 'rejected';

export type ReviewRatings = {
    rating_return: number;
    rating_flexibility: number;
    rating_no_pregnancy_questions: number;
};

export type CandidateReview = ReviewRatings & {
    id: number;
    company: { id: number; name: string };
    quote: string | null;
    author_label: string | null;
    status: ReviewStatus;
};

export const reviewStatusLabels: Record<ReviewStatus, string> = {
    pending: 'Czeka na moderację',
    approved: 'Opublikowana',
    rejected: 'Odrzucona',
};

export const ratingFields: Array<{ key: keyof ReviewRatings; label: string }> =
    [
        { key: 'rating_return', label: 'Powrót po urlopie' },
        { key: 'rating_flexibility', label: 'Elastyczne godziny' },
        {
            key: 'rating_no_pregnancy_questions',
            label: 'Rozmowy bez pytań o ciążę',
        },
    ];

/**
 * Mean of the three category ratings.
 */
export function overallRating(ratings: ReviewRatings): number {
    return (
        (ratings.rating_return +
            ratings.rating_flexibility +
            ratings.rating_no_pregnancy_questions) /
        3
    );
}
