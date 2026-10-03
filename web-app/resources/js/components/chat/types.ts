export type Citation = {
    type: 'legal' | 'article';
    label: string;
    url: string | null;
};

export type ConversationSummary = {
    id: number;
    counterpart_name: string;
    is_team_chat: boolean;
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
    | {
          type: 'candidate';
          name: string;
          email: string;
          phone: string | null;
          photo_url: string | null;
      }
    | {
          type: 'company';
          name: string;
          verified: boolean;
          rating: number | null;
      }
    | {
          type: 'team';
          name: string;
          company: {
              id: number;
              name: string;
              verified: boolean;
              rating: number | null;
          };
          members: TeamChatMember[];
      };

export type TeamChatMember = {
    name: string;
    joined: boolean;
    is_me: boolean;
    email: string | null;
    phone: string | null;
    photo_url: string | null;
};

export type AssistantChatMessage = {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    citations: Citation[];
};
