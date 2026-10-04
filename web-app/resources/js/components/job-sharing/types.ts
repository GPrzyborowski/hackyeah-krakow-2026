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
    | 'cancelled'
    | 'hired'
    | 'declined';

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
    hired: 'Zatrudnione',
    declined: 'Odrzucone przez członkinię',
};

export type JoinLink = {
    url: string;
    token: string;
    expires_at: string;
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
        is_waiting_for_partner: boolean;
    } | null;
    can_create_join_link: boolean;
    join_link: JoinLink | null;
};
