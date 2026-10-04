# mumjobs API v1 – Shared endpoints

Base URL `/api/v1`, `Accept: application/json`. See [README.md](README.md) for auth, errors, pagination and polling.
Auth column: **public** = no token; **auth** = any signed-in user (candidate or employer); **candidate** = role `candidate`
(employers get 403).

| Area | Endpoints |
| --- | --- |
| Public | `GET /public/offers`, `GET /public/offers/{id}`, `GET /public/companies/{id}`, `GET /articles`, `GET /articles/{slug}` |
| Conversations | `GET /conversations`, `GET /conversations/{id}`, `GET|POST /conversations/{id}/messages` |
| Job sharing | `GET /job-sharing`, `GET /job-sharing/offers/{id}/partners`, `POST /job-sharing/offers/{id}/pairs`, `POST /job-sharing/offers/{id}/invite-link`, `GET|POST /job-sharing/join/{token}`, `GET /job-sharing/pairs/{id}`, `POST …/accept|decline|cancel`, `GET|POST …/messages`, `PUT …/schedule`, `POST …/schedule/confirm`, `POST …/submit` |
| Assistant | `GET|POST /assistant/messages` |
| Notifications | `GET /notifications`, `GET /notifications/unread-count`, `POST /notifications/{id}/read`, `POST /notifications/read-all` |
| Reviews | `GET /reviews`, `POST /reviews/companies/{id}`, `PUT /reviews/{id}` |
| Devices | `POST /devices`, `DELETE /devices/{token}` |

---

## Public (no token)

Public endpoints never contain candidate data.

### GET /public/offers

Published offers, newest first by default, no match score. Query (all optional, unknown values ignored):

| Param | Example | Meaning |
| --- | --- | --- |
| `q` | `rekrut` | Title, company name or skill contains |
| `location` | `Kraków` | City contains; a value containing "zdal" also matches remote offers |
| `category[]` | `it`, `health`, … (see below) | Industry ("Branża"), any of |
| `work_mode[]` | `remote`, `hybrid`, `onsite` | Any of |
| `fraction[]` | `1`, `3/4`, `3/5`, `1/2` | Employment fraction, any of |
| `contract_type[]` | `employment`, `mandate` | Contract type ("Forma zatrudnienia"), offers with any of them |
| `flexible` | `1` | Only flexible hours |
| `childcare_subsidy` | `1` | Only offers with a nursery/kindergarten subsidy ("Dofinansowanie żłobka lub przedszkola") |
| `with_reviews` | `1` | Only offers of companies with at least one approved parent review ("Firma z opiniami rodziców") |
| `job_share` | `1` | Only job-sharing offers |
| `nursery_nearby` | `1` | Only offers with a nursery/kindergarten at most 3 km from the workplace (`nursery_distance_km` set and `<= 3`) |
| `verified_only` | `1` | Only offers of companies verified by mumjobs (badge "Zweryfikowana firma") |
| `start_from` | `2027-09-01` | "Mogę zacząć od": offers starting no earlier than 30 days before this date (same tolerance as matching); invalid dates ignored |
| `sort` | `rating` | `newest` (default, by published date), `rating` (best company rating, unrated last), `start_date` (soonest start first) |
| `page` | `2` | Page (20 per page) |

```json
{
  "data": [{
    "id": 4,
    "title": "Specjalistka ds. kadr",
    "category": "hr",
    "category_label": "HR i rekrutacja",
    "city": "Kraków",
    "work_mode": "hybrid",
    "work_mode_label": "Hybrydowo",
    "employment_fraction": "3/5",
    "employment_fraction_label": "3/5 etatu",
    "contract_types": ["employment"],
    "contract_type_labels": ["Umowa o pracę"],
    "salary_min": 6500,
    "salary_max": 8000,
    "start_date": "2027-09-01",
    "flexible_hours": true,
    "fixed_meeting_hours": false,
    "childcare_subsidy": true,
    "nursery_distance_km": 2,
    "is_parent_friendly": true,
    "job_share": { "is_job_share": false, "workday_starts_at": null, "workday_ends_at": null, "hours_per_person": null },
    "published_at": "2026-09-28T09:00:00+00:00",
    "company": {
      "id": 2, "name": "Zielone Biuro", "verified": true, "rating": 4.6,
      "featured_quote": { "quote": "Po powrocie dostałam miesiąc na wdrożenie.", "author_label": "Mama dwójki" }
    }
  }],
  "links": { "first": "…?page=1", "last": "…?page=3", "prev": null, "next": "…?page=2" },
  "meta": {
    "current_page": 1, "last_page": 3, "per_page": 20, "total": 47,
    "filters": { "q": "", "location": "", "category": [], "work_mode": ["hybrid"], "fraction": [], "contract_type": [], "flexible": false, "childcare_subsidy": false, "nursery_nearby": false, "with_reviews": false, "job_share": false, "verified_only": false, "start_from": null, "sort": "newest" }
  }
}
```

Every offer has exactly one industry: `category` (value) and `category_label` (Polish label). Values: `it`, `health`, `hr`, `finance`, `marketing`, `customer_service`, `administration`, `sales`, `education`, `design`, `other` ("IT i technologie", "Medycyna i zdrowie", "HR i rekrutacja", "Finanse i księgowość", "Marketing i komunikacja", "Obsługa klienta", "Administracja i biuro", "Sprzedaż", "Edukacja", "Projektowanie i kreatywne", "Inne"). The same two fields are on the offer detail and on the offers of `GET /public/companies/{company}`.

Every offer has one or more contract types ("Forma zatrudnienia"): `contract_types` (values, list) and `contract_type_labels` (Polish labels, same order). Values: `employment` ("Umowa o pracę"), `mandate` ("Umowa zlecenie"). Offers created before the field existed report `["employment"]`. The same two fields are on the offer detail and on the offers of `GET /public/companies/{company}`.

### GET /public/offers/{offer}

A published offer (404 for drafts / closed). Card fields above plus:

```json
{
  "data": {
    "id": 4, "title": "Specjalistka ds. kadr", "…": "…card fields…",
    "description": "Szukamy osoby…",
    "required_skills": ["Kadry i płace", "Excel"],
    "nice_to_have_skills": ["SAP"],
    "workday_starts_at": null,
    "workday_ends_at": null,
    "company": {
      "id": 2, "name": "Zielone Biuro", "city": "Kraków", "verified": true,
      "rating": { "overall": 4.6, "count": 5, "categories": { "return": 4.8, "flexibility": 4.4, "no_pregnancy_questions": 4.6 } },
      "featured_quote": { "quote": "…", "author_label": "Mama dwójki" }
    }
  }
}
```

`workday_starts_at` / `workday_ends_at` (`HH:MM`) are set for job-sharing offers.

### GET /public/companies/{company}

Company profile with **approved** reviews only (anonymous) and published offers.

```json
{
  "data": {
    "id": 2, "name": "Zielone Biuro", "city": "Kraków", "verified": true, "description": "…",
    "rating": { "overall": 4.6, "count": 5, "categories": { "return": 4.8, "flexibility": 4.4, "no_pregnancy_questions": 4.6 } },
    "reviews": [{
      "id": 9, "rating_return": 5, "rating_flexibility": 4, "rating_no_pregnancy_questions": 5,
      "overall": 4.7, "quote": "…", "author_label": "Mama dwójki, księgowość"
    }],
    "offers": [{
      "id": 4, "title": "…", "category": "hr", "category_label": "HR i rekrutacja", "city": "Kraków", "work_mode": "hybrid", "work_mode_label": "Hybrydowo",
      "employment_fraction_label": "3/5 etatu", "contract_types": ["employment"], "contract_type_labels": ["Umowa o pracę"], "salary_min": 6500, "salary_max": 8000, "start_date": "2027-09-01",
      "flexible_hours": true, "fixed_meeting_hours": false, "childcare_subsidy": true, "is_job_share": false
    }]
  }
}
```

### GET /articles

Published blog articles, featured first then newest, paginated. Query: `category` (one of `meta.categories[].value`,
unknown values ignored), `featured=1` (only featured), `page`.

```json
{
  "data": [{
    "id": 3, "title": "Rozmowa w ciąży: co musisz powiedzieć, a czego nie", "slug": "rozmowa-w-ciazy",
    "excerpt": "…", "category": "rights", "category_label": "Prawa", "reading_minutes": 5,
    "is_featured": true, "published_at": "2026-09-20T08:00:00+00:00"
  }],
  "links": { "…": "…" },
  "meta": {
    "current_page": 1, "…": "…",
    "categories": [{ "value": "pregnancy", "label": "W ciąży" }, { "value": "leave", "label": "Urlop" }, "…"],
    "active_category": null
  }
}
```

### GET /articles/{slug}

A published article (404 when unpublished or scheduled). Body as Markdown and as safe HTML rendered exactly like the
web (raw HTML stripped, `javascript:` links removed), plus up to 3 related articles (same category).

```json
{
  "data": {
    "id": 3, "title": "…", "slug": "rozmowa-w-ciazy", "…": "…card fields…",
    "body_markdown": "## Co mówi prawo\n\n…",
    "body_html": "<h2>Co mówi prawo</h2>\n<p>…</p>\n"
  },
  "related": [{ "id": 5, "title": "…", "slug": "…", "…": "…card fields…" }]
}
```

---

## Conversations (auth, both roles)

A conversation exists once a candidate accepts an invitation. Participants: the candidate and **every member** of the
inviting company. Anybody else gets 403.

**Team chat ("Czat zespołu")** – when a job-sharing pair is invited, the first member's acceptance also opens one shared
conversation for the pair (`is_team_chat: true`): the inviting company plus every pair member who has **accepted** her
invitation (the other member joins when she accepts; until then she gets 403). It starts with the invitation message.
Each member keeps her own 1:1 conversation with the company for private matters. In a team chat the company sees a
member's full name and contact data only once she joined; members see each other only anonymously ("Ewa N.") – in the
header and in `author_name`. `unread_count` is tracked per participant; a message's `read_at` is when the first other
participant read it. Show a "Para job-sharing" chip on the list item and header.

### GET /conversations

Your conversations, latest activity first, paginated.

```json
{
  "data": [{
    "id": 7,
    "counterpart_name": "Zielone Biuro",
    "is_team_chat": false,
    "offer": { "id": 4, "title": "Specjalistka ds. kadr" },
    "last_message": { "id": 31, "excerpt": "Dzień dobry, zapraszamy…", "created_at": "2026-10-03T12:00:00+00:00" },
    "last_message_at": "2026-10-03T12:00:00+00:00",
    "unread_count": 2,
    "has_unread": true
  }],
  "links": { "…": "…" }, "meta": { "…": "…" }
}
```

`counterpart_name` is the company name for a candidate, the candidate's full name for an employer (revealed after acceptance).
For a team chat it is `"Czat zespołu: Marta K. i Ewa N."` (an employer sees the full name of each member who joined).

### GET /conversations/{conversation}

Header of the thread.

```json
{
  "data": {
    "id": 7,
    "offer": { "id": 4, "title": "Specjalistka ds. kadr" },
    "is_team_chat": false,
    "counterpart": { "type": "company", "id": 2, "name": "Zielone Biuro", "verified": true, "rating": 4.6 },
    "pair_partner_name": null,
    "viewer_role": "candidate",
    "last_message_at": "2026-10-03T12:00:00+00:00"
  }
}
```

For an employer `counterpart` is `{"type": "candidate", "name": "Marta Kowalska", "email": "marta@mumjobs.test", "phone": "+48 600 100 200", "photo_url": "https://mumjobs.test/api/v1/candidate-photos/12?v=1a2b3c4d"}` (`phone` / `photo_url` may be `null`). Show the photo as the header avatar, or the name's initial without one.
`pair_partner_name` is set when the invitation was for a job-sharing pair: the candidate's partner, anonymous ("Ewa N."); `null` in a team chat.

For a team chat `counterpart` is (employer view; header "Czat zespołu: … · {offer.title}"):

```json
{
  "type": "team",
  "name": "Czat zespołu: Marta Kowalska i Ewa N.",
  "company": { "id": 2, "name": "Zielone Biuro", "verified": true, "rating": 4.6 },
  "members": [
    { "name": "Marta Kowalska", "joined": true, "is_me": false, "email": "marta@mumjobs.test", "phone": null, "photo_url": null },
    { "name": "Ewa N.", "joined": false, "is_me": false, "email": null, "phone": null, "photo_url": null }
  ]
}
```

`email` / `phone` / `photo_url` are only given to the company and only for members who joined; a candidate always gets
anonymous names and `null` contact data.

Errors: 403 not a participant, 404 unknown id.

### GET /conversations/{conversation}/messages

Messages **newest first**, paginated. Reading marks the other side's messages as read (decreases `unread_count`).
Polling: `?after_id=<last id you have>` (preferred) or `?since=<ISO 8601>`.

```json
{
  "data": [{
    "id": 31,
    "body": "Dzień dobry, zapraszamy na rozmowę w czwartek.",
    "author_name": "Anna Rekruterka",
    "author_side": "company",
    "is_mine": false,
    "read_at": "2026-10-03T12:05:00+00:00",
    "created_at": "2026-10-03T12:00:00+00:00"
  }],
  "links": { "…": "…" }, "meta": { "…": "…" }
}
```

`is_mine` is true for your side: your own messages as a candidate, any colleague's messages as an employer.
In a team chat a candidate sees her partner's `author_name` anonymously ("Ewa N."); use `author_name` as the bubble speaker there.
Errors: 403 not a participant, 422 invalid `since` / `after_id`.

### POST /conversations/{conversation}/messages

Throttle: 30/min. Body: `{"body": "…"}` (required, max 2000). → `201 {"data": <message>}`.

Employer messages are moderated; a question about pregnancy / family plans fails with:

```json
{
  "message": "…",
  "errors": {
    "body": ["Pytania o ciążę i plany rodzinne są niedozwolone."],
    "body_suggestion": ["Od kiedy może Pani rozpocząć pracę i jaki wymiar godzin Pani odpowiada?"]
  }
}
```

Errors: 403 not a participant, 422 validation / moderation, 429 throttled.

---

## Candidate photo (auth)

### GET /candidate-photos/{profile}

Streams the candidate's photo (`image/jpeg`, `image/png` or `image/webp`; `Cache-Control: private`). Use the `photo_url` from a payload (it already points here, with a `v` cache-busting query) and send the bearer token – e.g. load it into an authenticated image component.

Allowed: the candidate herself, or a member of a company whose invitation she **accepted**. Everybody else – other companies, a company whose invitation is still pending or was declined, other candidates – gets `404`, as does a profile without a photo. `401` without a token.

---

## Job sharing (candidate)

Two candidates apply together for a job-sharing offer and split the workday. Other candidates are always shown
anonymously (first name + surname initial). Pair statuses: `forming` (invited partner has not answered), `formed`
(both in, planning the split), `submitted` (sent to the employer), `invited` (employer invited the pair),
`rejected`, `cancelled`, `hired` (both members accepted their invitations), `declined` (a member declined her
invitation) – each with a Polish `status_label`.

### GET /job-sharing

The hub (not paginated).

```json
{
  "data": {
    "is_open_to_job_sharing": true,
    "is_profile_published": true,
    "invitations": [{
      "id": 5, "status": "forming", "status_label": "Czeka na odpowiedź partnerki",
      "offer": { "id": 9, "title": "Rekruterka IT", "company": "Kamienica", "job_share": { "is_job_share": true, "workday_starts_at": "08:00", "workday_ends_at": "16:00", "hours_per_person": 4 } },
      "partner": { "display_name": "Marta K.", "headline": "Rekruterka IT", "preferred_day_part_label": "Poranki" }
    }],
    "pairs": [ "…same shape: pairs you are an accepted member of (cancelled ones are hidden)…" ],
    "offers": [{
      "id": 9, "title": "Rekruterka IT", "company": "Kamienica", "city": "Kraków", "work_mode_label": "Hybrydowo",
      "score": 87, "job_share": { "…": "…" },
      "active_pair_id": 5,
      "active_pair_state": "invite_received"
    }]
  }
}
```

`invitations` = pair invitations waiting for **your** answer. `active_pair_state`: `pair` (formed or later),
`invite_sent` (you invited, waiting), `invite_received` (you were invited), or `null` (no active pair → show
"Find a partner").

### GET /job-sharing/offers/{offer}/partners

Possible partners for a published job-sharing offer (404 otherwise): candidates open to job sharing, eligible for the
offer, not already paired for it. Interested candidates first, then by match. Empty when your profile is not
published or you already have an active pair (see `meta`).

```json
{
  "data": [{
    "id": 14,
    "anonymous_name": "Ewa N.",
    "initial": "E",
    "headline": "Specjalistka HR",
    "years_of_experience": 5,
    "available_from": "2027-08-01",
    "preferred_day_part": "afternoon",
    "preferred_day_part_label": "Popołudnia",
    "skills": [{ "name": "Rekrutacja IT", "matched": true }, { "name": "Excel", "matched": false }],
    "score": 92,
    "is_interested": true,
    "is_complementary": true
  }],
  "meta": {
    "offer": { "id": 9, "title": "Rekruterka IT", "company": "Kamienica", "city": "Kraków", "work_mode_label": "Hybrydowo", "job_share": { "…": "…" } },
    "my_day_part_label": "Poranki",
    "is_profile_published": true,
    "active_pair_id": null
  }
}
```

Never contains surname, e-mail, CV, due date or leave dates.

### POST /job-sharing/offers/{offer}/pairs

Throttle: 20/min. Body: `{"partner_id": 14}` (an `id` from the partners list). → `201 {"data": <pair>}`.
The partner gets a notification (`pair_invitation_received`, mail + bell).

Errors: 404 offer not a published job-sharing offer; 422 `partner_id` – missing / unknown, your profile not
published ("Najpierw opublikuj swój profil…"), you already have a pair for the offer, or the person is no longer
available.

### POST /job-sharing/offers/{offer}/invite-link

"Zaproś koleżankę linkiem". Throttle: 20/min. No body. Creates a pair in `forming` with you as the only member when you
have none for the offer, plus a link valid for 7 days. A link that still works is reused (`200`); a new one is `201`:

```json
{ "data": { "url": "https://mumjobs.pl/job-sharing/join/AbC…", "token": "AbC…", "expires_at": "2026-10-11T10:00:00+00:00", "pair_id": 5 } }
```

Share `url` (system share sheet / copy). It opens the web page, so it works for friends without the app or an account.
One link = one person: it stops working when someone joins, when you invite someone from the partners list (the pair
then has its second person) or when the pair is cancelled. While you wait, the partners list still works and inviting
someone from it reuses the same pair. Errors: 404 offer not a published job-sharing offer; 422 `join_link` – your
profile is not published, or you already have a pair for the offer.

### GET /job-sharing/join/{token}

Public (bearer token optional), throttle 30/min. Preview for the deep link / join screen. 404 for an unknown token.

```json
{
  "data": {
    "offer": {
      "id": 9, "title": "Rekruterka IT", "company": "Kamienica", "city": "Kraków",
      "work_mode_label": "Hybrydowo", "employment_fraction_label": "Pełny etat",
      "workday_starts_at": "08:00", "workday_ends_at": "16:00", "hours_per_person": 4
    },
    "inviter": { "first_name": "Marta", "display_name": "Marta K." },
    "expires_at": "2026-10-11T10:00:00+00:00",
    "can_join": true,
    "requires_sign_in": false,
    "reason": null,
    "reason_message": null
  }
}
```

`can_join` is true only for a signed-in candidate who may join. A guest with a working link gets
`requires_sign_in: true` (sign in or register as a candidate, then call the preview again). Otherwise `reason` is one
of `expired`, `used`, `not_candidate`, `own_link`, `pair_closed`, `pair_full`, `offer_closed`, `already_paired`, and
`reason_message` is the Polish text to show instead of the button.

### POST /job-sharing/join/{token}

Candidates only, throttle 30/min, no body → `200 {"data": <pair>}`. You join as an accepted member, `status` →
`formed`, your profile becomes open to job sharing (`open_to_job_sharing: true`) and the inviter is notified
(`pair_invitation_accepted`). No skill matching – the inviter chose you. 404 unknown token; 422 `join_link` with the
same messages as `reason_message`.

### Pair resource – GET /job-sharing/pairs/{pair}

Members only (including an invited partner who has not answered yet); others get 403.

```json
{
  "data": {
    "id": 5,
    "status": "formed",
    "status_label": "Ustalacie podział dnia",
    "submitted_at": null,
    "has_saved_schedule": true,
    "offer": {
      "id": 9, "title": "Rekruterka IT", "company": "Kamienica", "city": "Kraków",
      "work_mode_label": "Hybrydowo", "employment_fraction_label": "Pełny etat", "is_published": true,
      "workday_starts_at": "08:00", "workday_ends_at": "16:00"
    },
    "members": [{
      "id": 12, "first_name": "Marta", "display_name": "Marta K.", "headline": "…",
      "preferred_day_part": "morning", "preferred_day_part_label": "Poranki",
      "is_me": true, "is_initiator": true, "has_accepted": true, "has_confirmed_schedule": false
    }, {
      "id": 14, "first_name": "Ewa", "display_name": "Ewa N.", "…": "…",
      "is_me": false, "is_initiator": false, "has_accepted": true, "has_confirmed_schedule": false
    }],
    "schedule": [
      { "candidate_profile_id": 12, "starts_at": "08:00", "ends_at": "12:00" },
      { "candidate_profile_id": 14, "starts_at": "12:00", "ends_at": "16:00" }
    ],
    "can": { "respond": false, "chat": true, "send_message": true, "plan_schedule": true, "cancel": true },
    "invite_link": null
  }
}
```

`invite_link` (`{url, token, expires_at}`) is set only for the initiator while nobody else is in the pair and a link
still works – show it with "Kopiuj link" / "Udostępnij". Members are ordered initiator first. `schedule` is the saved proposal, or a suggested even split (respecting preferred
parts of the day) when nothing is saved yet (`has_saved_schedule: false`). Use `can.*` to show buttons.

### POST /job-sharing/pairs/{pair}/accept · /decline · /cancel

No body. Each returns `200 {"data": <pair>}`.

- `accept` – invited partner joins (`status` → `formed`); the initiator is notified (`pair_invitation_accepted`).
  403 if you are not the invited partner or already answered; 422 `pair` if you already have another pair for the offer
  or the pair is full.
- `decline` – invited partner refuses (`status` → `cancelled`). 403 as above.
- `cancel` – an accepted member leaves a `forming` / `formed` pair (`status` → `cancelled`). 403 otherwise.

### GET /job-sharing/pairs/{pair}/messages

Private pair chat, only for members who accepted the pair (403 for the invited partner before accepting and for
everyone else; the employer never sees it). Newest first, paginated, polling with `after_id` / `since`.

```json
{
  "data": [{ "id": 40, "body": "Cześć! Wolę poranki.", "author_name": "Marta", "is_mine": true, "created_at": "2026-10-03T10:00:00+00:00" }],
  "links": { "…": "…" }, "meta": { "…": "…" }
}
```

### POST /job-sharing/pairs/{pair}/messages

Throttle: 30/min. Body `{"body": "…"}` (required, max 2000; not moderated – candidates only). → `201 {"data": <message>}`.
Only while the pair is active (`forming`, `formed`, `submitted`, `invited`, `hired`; `can.send_message`). In a
`cancelled`, `rejected` or `declined` pair the chat stays readable (`can.chat`) but posting is `403`
("Ta para została zakończona – czat jest już tylko do odczytu.").

### PUT /job-sharing/pairs/{pair}/schedule

Formed pairs only (403 otherwise). Saves a proposal and resets both confirmations. → `200 {"data": <pair>}`.

```json
{
  "schedule": [
    { "candidate_profile_id": 12, "starts_at": "08:00", "ends_at": "12:00" },
    { "candidate_profile_id": 14, "starts_at": "12:00", "ends_at": "16:00" }
  ]
}
```

Rules (422): 1–2 blocks, `HH:MM` times (`schedule.0.starts_at`…), each member exactly one block of at least one hour
inside the offer's workday, no overlaps and no gaps – together the blocks cover the whole workday (error key
`schedule`, e.g. "Nikt nie pracuje między 11:00 a 12:00. Podzielcie cały dzień pracy.").

### POST /job-sharing/pairs/{pair}/schedule/confirm

You accept the current proposal. 422 `schedule` when nothing is saved yet. → `200 {"data": <pair>}`.

### POST /job-sharing/pairs/{pair}/submit

Sends the pair to the employer (`status` → `submitted`, still anonymous). 422 `schedule` unless both members confirmed
the saved split. → `200 {"data": <pair>}`.

---

## Legal assistant (auth, both roles)

### GET /assistant/messages

Your chat history, newest first, paginated.

```json
{
  "data": [{
    "id": 22,
    "role": "assistant",
    "content": "Najbliżej Twojego pytania jest ten przepis…",
    "citations": [
      { "type": "legal", "label": "Kodeks pracy, art. 22¹", "url": null },
      { "type": "article", "label": "Blog: Rozmowa w ciąży", "url": "/blog/rozmowa-w-ciazy" }
    ],
    "created_at": "2026-10-03T10:00:00+00:00"
  }],
  "links": { "…": "…" },
  "meta": {
    "current_page": 1, "…": "…",
    "disclaimer": "To informacja ogólna, a nie porada prawna. Przy sporze skontaktuj się z prawnikiem.",
    "suggestions": ["Czy muszę mówić o ciąży na rozmowie?", "Zwolnienie lekarskie w ciąży", "Ochrona przed zwolnieniem w ciąży", "Zasiłek macierzyński"]
  }
}
```

`meta.suggestions` depend on the candidate's private stage – pregnant: questions about pregnancy at work; after leave: "Powrót na część etatu", "Urlop rodzicielski a powrót", "Przerwy na karmienie", …; employers and candidates without a stage get the general list.

`role`: `user` | `assistant`. Article citation `url` is a web path – open the article in the app via
`GET /articles/{slug}` (the slug is the last path segment).

### POST /assistant/messages

Throttle: 10/min (429 JSON). Body `{"question": "…"}` (3–1000 chars). The answer can take a few seconds. → `201`.

```json
{
  "data": {
    "question": { "id": 21, "role": "user", "content": "Czy muszę mówić o ciąży na rozmowie?", "citations": [], "created_at": "…" },
    "answer": { "id": 22, "role": "assistant", "content": "…", "citations": [ "…" ], "created_at": "…" }
  },
  "meta": { "disclaimer": "To informacja ogólna, a nie porada prawna. …" }
}
```

---

## Notifications (auth, both roles)

### GET /notifications

Your notifications, newest first, paginated; `?since=<ISO 8601>` returns only newer ones.

```json
{
  "data": [{
    "id": "9b2f0c1e-…",
    "kind": "new_message",
    "title": "Nowa wiadomość od: Zielone Biuro",
    "body": "Dzień dobry, zapraszamy…",
    "url": "/conversations/7",
    "read": false,
    "created_at": "2026-10-03T12:00:00+00:00",
    "created_at_diff": "5 minut temu",
    "target": { "conversation_id": 7 }
  }],
  "links": { "…": "…" },
  "meta": { "current_page": 1, "…": "…", "unread_count": 3 }
}
```

`kind` → screen to open (ids in `target`; `url` is the web path):

| kind | Recipient | target |
| --- | --- | --- |
| `invitation_received` | candidate | `invitation_id` |
| `invitation_accepted` | employer | `conversation_id` |
| `invitation_declined` | employer | `invitation_id` |
| `new_message` | both | `conversation_id` |
| `pair_invitation_received` | candidate | `job_share_pair_id` |
| `pair_invitation_accepted` | candidate | `job_share_pair_id` |
| `company_verified` | employer (all company members) | `company_id` – "Twoja firma została zweryfikowana"; open the company screen |

### GET /notifications/unread-count

`{"data": {"unread_count": 3}}` – cheap call for the badge.

### POST /notifications/{id}/read

Marks one notification read → `200 {"data": <notification>}`. 404 for an unknown id or another user's notification.

### POST /notifications/read-all

→ `204`.

---

## Company reviews (candidate)

A candidate may review (once) a company whose invitation she accepted. Reviews are anonymous and wait for moderation
(`pending` → `approved` / `rejected`); only `approved` ones are public.

### GET /reviews

```json
{
  "data": {
    "reviewable_companies": [{ "id": 2, "name": "Zielone Biuro", "city": "Kraków" }],
    "reviews": [{
      "id": 9,
      "company": { "id": 3, "name": "Kamienica" },
      "rating_return": 5, "rating_flexibility": 4, "rating_no_pregnancy_questions": 5,
      "quote": "Po powrocie dostałam miesiąc na wdrożenie.",
      "author_label": "Mama dwójki, księgowość",
      "status": "pending", "status_label": "Czeka na moderację",
      "can_edit": true,
      "created_at": "2026-10-03T10:00:00+00:00"
    }]
  }
}
```

### POST /reviews/companies/{company}

```json
{
  "rating_return": 5,
  "rating_flexibility": 4,
  "rating_no_pregnancy_questions": 5,
  "quote": "Po powrocie dostałam miesiąc na wdrożenie.",
  "author_label": "Mama dwójki, księgowość"
}
```

Ratings 1–5 (required); `quote` 10–300 chars (required); `author_label` max 80 (optional). Neither text may contain an
e-mail address or phone number ("Usuń adres e-mail lub numer telefonu – opinie publikujemy anonimowo."). → `201 {"data": <review>}`.

Errors: 403 "Możesz ocenić tylko firmę, której zaproszenie przyjęłaś." / "Ta firma ma już Twoją opinię.", 422 validation.

### PUT /reviews/{review}

Same body; only your own review while it is `pending` (403 otherwise). → `200 {"data": <review>}`.

---

## Push devices (auth, both roles)

Pushes are sent through Firebase Cloud Messaging to every registered device of the user while `push_enabled` is
true (`PATCH /auth/me/preferences`). Payload: see [README.md](README.md#push-notifications).

### POST /devices

`{"token": "<FCM or APNs token>", "platform": "ios" | "android"}` → `204`. Idempotent: call on every app start /
token refresh. A token registered before by another account is moved to the current user. The push token is bound
to the API token used for this call: `POST /auth/logout` (or `logout-all`, a password change/reset, token expiry)
deletes it automatically.

### DELETE /devices/{token}

Forget the token explicitly (optional – logout already removes it). → `204`; 404 if the token is not yours.
