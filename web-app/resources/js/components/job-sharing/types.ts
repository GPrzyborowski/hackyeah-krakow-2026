export type JobShareSummary = {
    is_job_share: boolean;
    workday_starts_at: string | null;
    workday_ends_at: string | null;
    hours_per_person: number | null;
};

export type PairStatus =
    | 'forming'
    | 'formed'
    | 'submitted'
    | 'invited'
    | 'rejected'
    | 'cancelled';

export type ScheduleBlock = {
    candidate_profile_id: number;
    starts_at: string;
    ends_at: string;
};

export type ScheduleBarBlock = {
    key: number | string;
    label: string;
    starts_at: string;
    ends_at: string;
    tone: 'peach' | 'yellow';
};

export const pairStatusLabels: Record<PairStatus, string> = {
    forming: 'Czeka na odpowiedź partnerki',
    formed: 'Ustalacie podział dnia',
    submitted: 'Wysłane do pracodawcy',
    invited: 'Pracodawca zaprosił Waszą parę',
    rejected: 'Pracodawca odrzucił parę',
    cancelled: 'Para rozwiązana',
};

export type OfferJobSharing = {
    workday_starts_at: string | null;
    workday_ends_at: string | null;
    hours_per_person: number | null;
    is_open_to_job_sharing: boolean;
    pair: {
        id: number;
        status: PairStatus;
        partner_name: string | null;
        awaiting_my_answer: boolean;
    } | null;
};
