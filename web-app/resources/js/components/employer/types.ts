export type OfferStatus = 'draft' | 'published' | 'closed';

export type SelectOption = {
    value: string;
    label: string;
};

export type EmployerOffer = {
    id: number;
    title: string;
    category: string;
    category_label: string;
    city: string | null;
    work_mode: string;
    work_mode_label: string;
    employment_fraction: string;
    employment_fraction_label: string;
    salary_min: number | null;
    salary_max: number | null;
    start_date: string;
    description: string | null;
    flexible_hours: boolean;
    fixed_meeting_hours: boolean;
    childcare_subsidy: boolean;
    nursery_distance_km: number | null;
    is_job_share: boolean;
    workday_starts_at: string | null;
    workday_ends_at: string | null;
    hours_per_person: number | null;
    status: OfferStatus;
    published_at: string | null;
    required_skills: string[];
    nice_to_have_skills: string[];
};

export type OfferStatistics = {
    matched_count: number;
    to_review_count: number;
    invited_count: number;
    responded_count: number;
    accepted_count: number;
};

export type MatchDetails = {
    score: number;
    matched_required: string[];
    missing_required: string[];
    matched_nice_to_have: string[];
    missing_nice_to_have: string[];
    start_date_compatible: boolean;
};

export type AnonymousCandidate = {
    id: number;
    anonymous_name: string;
    initial: string;
    headline: string | null;
    years_of_experience: number | null;
    ai_summary: string | null;
    skills: { name: string; matched: boolean }[];
    available_from: string | null;
    employment_fractions: string[];
    work_modes: string[];
    match: MatchDetails | null;
    is_interested: boolean;
    accepts_direct_messages: boolean;
};

export type SwipeOffer = {
    id: number;
    title: string;
    city: string | null;
    start_date: string;
    salary_min: number | null;
    salary_max: number | null;
    employment_fraction_label: string;
    work_mode_label: string;
    flexible_hours: boolean;
    fixed_meeting_hours: boolean;
    is_job_share?: boolean;
    submitted_pairs_count?: number;
};

export type SavedCandidate = {
    id: number;
    anonymous_name: string;
    initial: string;
    available_from: string | null;
    score: number;
};
