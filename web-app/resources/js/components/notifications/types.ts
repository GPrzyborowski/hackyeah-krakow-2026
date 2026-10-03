export type AppNotification = {
    id: string;
    kind: string | null;
    title: string;
    body: string | null;
    url: string | null;
    read: boolean;
    created_at: string | null;
    created_at_diff: string | null;
};

export type NotificationsSummary = {
    unread_count: number;
    latest: AppNotification[];
};
