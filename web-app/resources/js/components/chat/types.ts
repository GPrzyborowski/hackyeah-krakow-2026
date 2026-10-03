export type Citation = {
    type: 'legal' | 'article';
    label: string;
    url: string | null;
};

export type ConversationSummary = {
    id: number;
    counterpart_name: string;
    offer_title: string;
    last_message: string | null;
    last_message_at: string | null;
    has_unread: boolean;
};

export type ConversationMessage = {
    id: number;
    body: string;
    author_name: string;
    is_mine: boolean;
    created_at: string;
};

export type ConversationCounterpart =
    | { type: 'candidate'; name: string; email: string }
    | { type: 'company'; name: string; rating: number | null };

export type AssistantChatMessage = {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    citations: Citation[];
};
