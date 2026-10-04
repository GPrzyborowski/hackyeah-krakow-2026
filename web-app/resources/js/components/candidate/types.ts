import type { JobShareSummary } from '@/components/job-sharing/types';

export type Option = { value: string; label: string };

/**
 * Private stage of the candidate – never shown to employers.
 */
export type CandidateStage = 'pregnant' | 'after_leave';

export type ReturnCalendarPhase = 'pregnancy' | 'leave' | 'return' | 'ready';

export type ReturnCalendar = {
    stage: CandidateStage | null;
    stage_label: string | null;
    phases: ReturnCalendarPhase[];
    pregnancy_week: number | null;
    due_date: string | null;
    leave_starts_on: string | null;
    available_from: string | null;
    current_phase: ReturnCalendarPhase;
};

export type MatchBreakdown = {
    score: number;
    matched_required: string[];
    missing_required: string[];
    matched_nice_to_have: string[];
    missing_nice_to_have: string[];
    start_date_compatible: boolean;
};

export type CandidateOffer = {
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
    flexible_hours: boolean;
    fixed_meeting_hours: boolean;
    childcare_subsidy: boolean;
    nursery_distance_km: number | null;
    published_at: string | null;
    is_parent_friendly: boolean;
    is_interested: boolean;
    is_saved: boolean;
    job_share?: JobShareSummary;
    match: MatchBreakdown;
    company: {
        id: number;
        name: string;
        city: string | null;
        verified: boolean;
        average_rating: number | null;
        reviews_count: number;
        first_review: { quote: string; author_label: string | null } | null;
    };
};

export type ProfileSkill = {
    id: number;
    name: string;
    source: 'ai' | 'manual';
    confirmed: boolean;
};

export type SuggestedPosition = { title: string; score: number };

export type OnboardingProfile = {
    anonymous_name: string;
    headline: string | null;
    years_of_experience: number | null;
    city: string | null;
    phone: string | null;
    photo_url: string | null;
    ai_summary: string | null;
    stage: CandidateStage | null;
    stage_label: string | null;
    available_from: string | null;
    leave_starts_on: string | null;
    due_date: string | null;
    work_modes: string[];
    employment_fractions: string[];
    wants_flexible_hours: boolean;
    open_to_job_sharing: boolean;
    preferred_day_part: 'morning' | 'afternoon' | 'any' | null;
    hidden_from_company_id: number | null;
    allow_direct_messages: boolean;
    job_alerts_enabled: boolean;
    show_availability_instead_of_gap: boolean;
    career_gap_note: string | null;
    onboarding_step: number;
    cv_original_name: string | null;
    cv_size: number | null;
    cv_status: 'uploaded' | 'parsing' | 'parsed' | 'failed' | null;
    cv_text: string | null;
    suggested_positions: SuggestedPosition[];
    is_published: boolean;
};

export type PreviewData = {
    headline: string | null;
    available_from: string | null;
};
