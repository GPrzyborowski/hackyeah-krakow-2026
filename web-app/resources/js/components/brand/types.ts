import type { JobShareSummary } from '@/components/job-sharing/types';

export type RatingCategories = {
    return: number | null;
    flexibility: number | null;
    no_pregnancy_questions: number | null;
};

export type RatingSummary = {
    overall: number | null;
    count: number;
    categories: RatingCategories;
};

export type FeaturedQuote = {
    quote: string;
    author_label: string | null;
};

export type PublicCompanySummary = {
    id: number;
    name: string;
    city: string | null;
    rating: RatingSummary;
    featured_quote: FeaturedQuote | null;
};

export type PublicArticleSummary = {
    id: number;
    title: string;
    slug: string;
    category: string;
    category_label: string;
    reading_minutes: number;
};

export type PublicOffer = {
    id: number;
    title: string;
    city: string | null;
    work_mode?: string;
    work_mode_label: string;
    employment_fraction_label: string;
    salary_min: number | null;
    salary_max: number | null;
    start_date: string;
    flexible_hours: boolean;
    fixed_meeting_hours?: boolean;
    childcare_subsidy?: boolean;
    nursery_distance_km?: number | null;
    is_parent_friendly?: boolean;
    job_share?: JobShareSummary;
    company?: {
        id: number;
        name: string;
        rating: number | null;
        featured_quote: FeaturedQuote | null;
    };
};

export const ratingCategoryLabels: Record<keyof RatingCategories, string> = {
    return: 'Powrót po urlopie',
    flexibility: 'Elastyczne godziny',
    no_pregnancy_questions: 'Rozmowy bez pytań o ciążę',
};
