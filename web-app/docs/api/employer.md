# mumjobs API v1 – Employer

Base URL: `/api/v1`. Every endpoint below requires `Authorization: Bearer <token>` (from `POST /auth/login`), a user with role `employer`, a **verified email address** and a company attached to the account. Send `Accept: application/json`. All data is scoped to the signed-in employer's company.

Common errors:

| Status | When |
| --- | --- |
| 401 | Missing / invalid token (`{"message": "Unauthenticated."}`) |
| 403 | Not an employer, email not verified (`{"message": "Potwierdź swój adres e-mail…", "email_verification_required": true}` – offer `POST /auth/email/verification-notification`), no company, or the offer belongs to another company / is not in the right state |
| 404 | Unknown id; candidate not matched to the offer or hidden from the company; job-share pair of another company or hidden |
| 422 | Validation failed: `{"message": "...", "errors": {"field": ["Polish message"]}}` |
| 429 | Throttled: 120/min per user on every endpoint; company update and offer create/update 20/min (moderated writes); skills 60/min, preview-matches 60/min, invitations 20/min, direct messages 20/min, pair invitations 20/min, team invitations 10/min |

Conventions: resources wrapped in `data`; lists paginated (20 per page) with Laravel's `data` / `links` / `meta` – pass `?page=N`. Dates `Y-m-d`, timestamps ISO 8601. Money in PLN gross (integers). Enums as value plus `*_label` (Polish).

**Privacy.** Before a candidate accepts an invitation the employer only ever sees the anonymous card: first name + surname initial (`anonymous_name`), headline, years of experience, AI summary, confirmed skills, `available_from`, employment fractions, work modes, match. Never surname, email, phone, photo, CV, due/leave dates. Candidates who hid their profile from the company never appear. Full name, email, phone and photo appear only on accepted invitations (`GET /employer/invitations`) and in the conversation header. Accepted invitations also carry `career_gap_note` ("Przerwa w karierze: …") only when the candidate explicitly opted in (privacy toggle `show_availability_instead_of_gap` off); otherwise it is `null`.

**Moderation.** Employer-authored text (offer title/description, company description, invitation messages) is checked for questions about pregnancy/family. Blocked text → 422 on that field.

---

## Dashboard ("Start")

### GET /employer/dashboard

The employer home screen; the web panel (`/employer`) renders the same data. Counters cover the company's **published** offers unless stated otherwise; the query count does not grow with offers or candidates.

```json
{
  "data": {
    "greeting": { "first_name": "Hanna" },
    "company": { "id": 3, "name": "Zielone Biuro", "city": "Kraków", "verified": true, "verified_at": "2026-10-01T09:00:00+02:00" },
    "stats": {
      "published_offers_count": 2,
      "matching_candidates_count": 3,
      "to_review_count": 2,
      "invitations_sent_recent_count": 1,
      "recent_days": 30,
      "responded_count": 2,
      "accepted_count": 1,
      "acceptance_rate": 50,
      "active_conversations_count": 1,
      "submitted_pairs_count": 1,
      "parent_friendly": { "count": 1, "total": 2 }
    },
    "funnel": [
      {
        "offer_id": 12, "title": "Rekruterka", "is_job_share": true, "is_parent_friendly": true,
        "matched_count": 2, "reviewed_count": 3, "to_review_count": 0,
        "invited_count": 2, "responded_count": 2, "accepted_count": 1, "submitted_pairs_count": 1
      }
    ],
    "todo": [
      { "kind": "unread_messages", "count": 1, "offer_id": null, "offer_title": null, "names": ["Anna Nowak"], "hints": [], "url": "/conversations" },
      { "kind": "submitted_pairs", "count": 1, "offer_id": 12, "offer_title": "Rekruterka", "names": [], "hints": [], "url": "/employer/offers/12/job-share-pairs" },
      { "kind": "candidates_to_review", "count": 2, "offer_id": 13, "offer_title": "Kadrowa", "names": [], "hints": [], "url": "/employer/candidates?offer=13" },
      { "kind": "offer_incomplete", "count": 3, "offer_id": 13, "offer_title": "Kadrowa", "names": [], "hints": ["missing_required_skills", "missing_salary", "no_flexible_hours"], "url": "/employer/offers/13/edit" }
    ],
    "reviews": { "approved_count": 1, "average_rating": 4.3 },
    "activity": [
      {
        "id": "pair-5", "kind": "pair_submitted", "title": "Para kandydatek zgłosiła się do oferty Rekruterka", "body": null,
        "url": "/employer/offers/12/job-share-pairs", "read": false,
        "created_at": "2026-10-03T12:01:00+02:00", "created_at_diff": "1 minutę temu",
        "target": { "job_share_pair_id": 5, "job_offer_id": 12 }
      }
    ]
  }
}
```

- `stats.matching_candidates_count` – unique candidates matched by at least one published offer (visible to the company, can start within 30 days of the offer start, share ≥1 skill). `to_review_count` – sum of the per-offer review queues.
- `stats.invitations_sent_recent_count` – invitations (incl. direct messages) created in the last `recent_days` days; `acceptance_rate` – accepted / answered in %, all time, `null` when nobody answered yet. `active_conversations_count` – conversations with a message in the last `recent_days` days.
- `stats.parent_friendly` – published offers that have the "Przyjazna rodzicom" badge out of all published offers.
- `funnel` – one row per published offer: matched → reviewed (skipped / saved / invited) → invited → accepted.
- `todo[].kind`: `unread_messages` (conversations with unread candidate messages, `names` = up to 3 candidates), `submitted_pairs` (job-share pairs waiting for a decision), `candidates_to_review` (top 3 offers by queue size), `offer_incomplete` (published or draft offer; `hints`: `missing_required_skills`, `missing_salary`, `no_flexible_hours`), `no_approved_reviews` (no approved company review yet – no offer can get the badge). `url` is a web path; use `offer_id` in the app.
- `activity` – last 10 items: the employer's notifications (`invitation_accepted`, `invitation_declined`, `new_message`, `pair_hired_company`, …, same shape as `GET /notifications`) merged with job-share pair submissions (`pair_submitted`), newest first.
- `company.verified` is `true` once mumjobs verified the NIP.

---

## Company

### GET /employer/company

```json
{
  "data": {
    "id": 3,
    "name": "Zielone Biuro",
    "nip": "1234563218",
    "city": "Kraków",
    "description": "Elastyczne godziny…",
    "verified": false,
    "verified_at": null,
    "ratings": { "count": 2, "overall": 4.2, "return": 4.5, "flexibility": 4.0, "no_pregnancy_questions": 4.0 },
    "reviews": [
      {
        "id": 9, "quote": "Powrót po urlopie był świetnie zaplanowany.", "author_label": "Mama dwójki, HR",
        "rating_return": 5, "rating_flexibility": 4, "rating_no_pregnancy_questions": 3, "overall": 4.0,
        "created_at": "2026-09-30T10:00:00+00:00"
      }
    ]
  }
}
```

Only approved reviews are included; rating fields are `null` when there are none.

`verified` / `verified_at` (ISO 8601): whether a mumjobs admin verified the company's NIP. While `verified` is `false` show the banner "Twoja firma czeka na weryfikację – zweryfikowane firmy dostają więcej odpowiedzi." (invitations are not blocked). Verification is done by an admin on the web; members get a `company_verified` notification.

### PUT /employer/company

Body: `name` (required, max 255), `nip` (optional, valid Polish NIP – 10 digits with a correct checksum, unique across companies; spaces and dashes are stripped, e.g. `123-456-32-18`), `city`, `description` (max 5000, moderated). Returns the company resource.

Throttle 20/min. Errors: 422 `nip` ("NIP musi składać się z 10 cyfr." / "Podany NIP jest nieprawidłowy." / "Firma z tym NIP-em ma już konto w mumjobs."), 422 `description` (moderation reason + suggestion).

---

## Team (recruiters of the company)

A company can have many recruiters (`users.company_id`). Every member sees the same company data (offers, candidates, conversations) – nothing beyond what the company already sees – and every member may manage the team (no owner role).

**Joining is web-only.** `POST /employer/team/invitations` e-mails a signed link (`/company-invitations/{token}?signature=…`, valid 7 days). On that web page a person without an account sets name + password (the account is created as a verified employer attached to the company); a signed-in employer **without a company** whose e-mail matches joins with one click; an existing account of that address is asked to log in first. Expired / used links show a friendly message. The API never exposes the token. Registration with a NIP that already exists answers `company_nip`: "Firma z tym NIP-em ma już konto w mumjobs. Poproś osobę z Twojej firmy o zaproszenie do zespołu w mumjobs."

### GET /employer/team

Small fixed lists (not paginated). Members sorted by name; invitations = not accepted yet (expired ones included with `is_expired: true` until revoked or re-sent), newest first.

```json
{
  "data": {
    "members": [
      { "id": 12, "name": "Anna Rekruterka", "email": "anna@firma.pl", "joined_at": "2026-10-03T12:00:00+00:00", "is_current_user": true }
    ],
    "invitations": [
      { "id": 4, "email": "nowa@firma.pl", "invited_by": "Anna Rekruterka", "created_at": "2026-10-03T12:05:00+00:00", "expires_at": "2026-10-10T12:05:00+00:00", "is_expired": false }
    ]
  }
}
```

`joined_at` = when the member accepted the invitation (account creation for the founder).

### POST /employer/team/invitations → 201

Body: `email` (required, e-mail; trimmed and lower-cased). Sends the e-mail and returns the invitation (`data`, same shape as above). A previous expired invitation for the same address is replaced. Throttle 10/min.

Errors: 422 `email` – "Ta osoba już należy do Twojego zespołu." / "Zaproszenie na ten adres już czeka na akceptację.". Whether the address already has a mumjobs account is never revealed; the acceptance page explains when an account cannot join (candidate account, member of another company).

### DELETE /employer/team/invitations/{invitation} → 204

Revokes a pending invitation (the link stops working). 403 for another company's invitation or an already accepted one.

### DELETE /employer/team/members/{user} → 204

Removes a colleague: detaches them from the company and revokes **all their API tokens** (and push devices). 403 for yourself ("Nie możesz usunąć z zespołu własnego konta."), for a user of another company, or when they are the last member ("W zespole musi zostać co najmniej jedna osoba.").

---

## Skills autocomplete

### GET /employer/skills?q=rekru

Up to 10 skills whose name or slug contains `q` (all suggestable skills when `q` is empty), sorted by name. Throttle 60/min.
Only the curated skill dictionary and skills already used by at least one offer are suggested – free-text tags typed by
candidates never appear here.

```json
{ "data": [{ "id": 7, "name": "Rekrutacja IT" }] }
```

---

## Offers

### Offer resource

```json
{
  "id": 21,
  "title": "Specjalistka ds. rekrutacji",
  "category": "hr", "category_label": "HR i rekrutacja",
  "city": "Poznań",
  "work_mode": "hybrid", "work_mode_label": "Hybrydowo",
  "employment_fraction": "3/5", "employment_fraction_label": "3/5 etatu",
  "salary_min": 8500, "salary_max": 11000,
  "start_date": "2027-09-01",
  "description": "Prowadzenie procesów rekrutacyjnych…",
  "flexible_hours": true, "fixed_meeting_hours": true, "childcare_subsidy": false,
  "nursery_distance_km": 2,
  "is_job_share": false, "workday_starts_at": null, "workday_ends_at": null, "hours_per_person": null,
  "status": "published", "status_label": "Opublikowana",
  "published_at": "2026-10-01T09:00:00+00:00",
  "required_skills": ["Rekrutacja IT"],
  "nice_to_have_skills": ["Onboarding"],
  "is_parent_friendly": true,
  "statistics": { "matched_count": 12, "to_review_count": 7, "invited_count": 3, "responded_count": 2, "accepted_count": 1 },
  "submitted_pairs_count": 0,
  "created_at": "2026-09-30T08:00:00+00:00",
  "updated_at": "2026-10-01T09:00:00+00:00"
}
```

`status`: `draft` (Szkic) | `published` (Opublikowana) | `closed` (Zamknięta). For job-share offers `workday_*` are `HH:MM` and `hours_per_person` is a number. `statistics`: matched = visible candidates who can start in time and share ≥1 skill; to_review = matched minus already skipped/saved/invited; responded/accepted count invitations. `submitted_pairs_count` = job-share pairs waiting for a decision.

### GET /employer/offers?status=published

Paginated list of the company's offers: published first, then drafts, then closed; newest first. Optional `status` filter (`draft|published|closed`, otherwise 422).

### POST /employer/offers → 201

Throttle 20/min (moderated write). Body (same rules as the web form):

| Field | Rules |
| --- | --- |
| `action` | required, `draft` or `publish` |
| `title` | required, max 255, moderated |
| `category` | optional (required in the web form): `it`, `health`, `hr`, `finance`, `marketing`, `customer_service`, `administration`, `sales`, `education`, `design`, `other`. When omitted, a new offer gets `other` and an update keeps the current value |
| `city` | optional |
| `work_mode` | required: `remote`, `hybrid`, `onsite` |
| `start_date` | required date; when publishing not in the past |
| `description` | optional, max 5000, moderated |
| `employment_fraction` | required: `1`, `3/4`, `3/5`, `1/2` |
| `salary_min`, `salary_max` | optional integers; `salary_max >= salary_min` |
| `flexible_hours`, `fixed_meeting_hours`, `childcare_subsidy`, `is_job_share` | booleans |
| `nursery_distance_km` | optional integer 0–50: km from the workplace to the nearest nursery/kindergarten (candidates filter by it). Ignored (stored as `null`) for `work_mode=remote` |
| `workday_starts_at`, `workday_ends_at` | `HH:MM`, required when `is_job_share`; end after start |
| `required_skills` | array of skill names (max 20); at least one when publishing; must be present (may be `[]`) for drafts |
| `nice_to_have_skills` | array of skill names (max 20), must be present (may be `[]`) |

Unknown skill names are created. Publishing sets `published_at` on first publish. Returns the offer resource.

### GET /employer/offers/{offer}

Offer resource. 403 for another company's offer.

### PUT /employer/offers/{offer}

Same body as POST (skills are replaced), throttle 20/min. Returns the offer resource. 403 for another company's offer.

### POST /employer/offers/{offer}/close

Closes the offer and withdraws pending invitations. Returns the offer resource. 403 if already closed or not own.

### POST /employer/offers/preview-matches

Live counters while editing. Throttle 60/min. Body: `start_date` (required), `required_skills` (array of names, present), `nice_to_have_skills` (array of names, present).

```json
{ "data": { "with_required": 14, "with_nice_to_have": 6 } }
```

---

## Candidate queue (swipe)

Only for **published** offers of the company (otherwise 403).

### GET /employer/offers/{offer}/candidates/next

The next undecided matched candidate (candidates who expressed interest in the offer first, then best match). Pass `?candidate=<id>` to bring a **saved** candidate back onto the card (`is_saved_candidate: true`).

```json
{
  "data": {
    "offer": {
      "id": 21, "title": "Specjalistka ds. rekrutacji", "city": "Poznań", "start_date": "2027-09-01",
      "salary_min": 8500, "salary_max": 11000, "employment_fraction_label": "3/5 etatu", "work_mode_label": "Hybrydowo",
      "flexible_hours": true, "fixed_meeting_hours": true, "is_job_share": false, "submitted_pairs_count": 0
    },
    "candidate": {
      "id": 12,
      "anonymous_name": "Marta K.",
      "initial": "M",
      "headline": "Specjalistka ds. rekrutacji IT",
      "years_of_experience": 6,
      "ai_summary": "Rekruterka IT z 6-letnim doświadczeniem…",
      "skills": [{ "name": "Rekrutacja IT", "matched": true }, { "name": "Excel", "matched": false }],
      "available_from": "2027-09-01",
      "employment_fractions": ["3/5 etatu"],
      "work_modes": ["Hybrydowo"],
      "match": {
        "score": 80,
        "matched_required": ["Rekrutacja IT"], "missing_required": [],
        "matched_nice_to_have": [], "missing_nice_to_have": ["Onboarding"],
        "start_date_compatible": true
      },
      "is_interested": true,
      "accepts_direct_messages": false
    },
    "is_saved_candidate": false,
    "remaining_count": 6,
    "saved": [{ "id": 15, "anonymous_name": "Anna N.", "initial": "A", "available_from": "2027-08-01", "score": 70 }],
    "invitation_stats": { "invited": 3, "responded": 2 },
    "statistics": { "matched_count": 12, "to_review_count": 6, "invited_count": 3, "responded_count": 2, "accepted_count": 1 }
  }
}
```

`candidate` is `null` when the queue is empty. `remaining_count` includes the current card.

### GET /employer/offers/{offer}/candidates/saved

Full anonymous cards (same shape as `candidate` above) of candidates saved for later, best match first. Not paginated.

### POST /employer/offers/{offer}/candidates/{candidate}/decision → 200

Body: `decision` = `skipped` | `saved` (anything else → 422). The candidate must currently match the offer (otherwise 404). An existing invitation is never downgraded (the response then shows `invited`).

```json
{ "data": { "offer_id": 21, "candidate_id": 12, "decision": "saved" } }
```

### POST /employer/offers/{offer}/candidates/{candidate}/invitation → 201

Body: `message` (required, max 2000, moderated). Throttle 20/min. The candidate must match the offer (404 otherwise). Returns the invitation resource (anonymous until accepted).

Errors:

```json
{
  "message": "…",
  "errors": {
    "message": ["Pytania o plany rodzinne są niedozwolone…"],
    "message_suggestion": ["Zapytaj o dostępność od planowanej daty startu…"]
  }
}
```

Duplicate invitation → 422 `errors.message`: "Ta kandydatka ma już zaproszenie do tej oferty."

### POST /employer/offers/{offer}/candidates/{candidate}/direct-message → 201

A lightweight question without an invitation ("Napisz wiadomość"), allowed only when the card has `accepts_direct_messages: true` (the candidate's privacy toggle "Pozwól firmom pisać bez zaproszenia"). Body: `message` (required, max 1000, moderated – same 422 shape as invitations). Throttle 20/min. Same 404/403 rules as the invitation.

It is stored as an invitation with `kind: "direct_message"` (`kind_label`: "Pytanie od firmy"), so the candidate stays anonymous until she answers (= accepts: the conversation opens with the question as its first message and her full name + e-mail are revealed) or ignores it (= `declined`). Like an invitation it removes her from the swipe queue (decision `invited`) and counts as the one invitation per offer and candidate.

Errors: candidate does not allow direct messages → 422 `errors.message`: "Ta kandydatka nie przyjmuje wiadomości bez zaproszenia. Możesz ją zaprosić do rozmowy."; duplicate → 422 as above.

---

## Invitations

### GET /employer/invitations?status=accepted

Paginated, newest first. Optional `status` = `pending|accepted|declined|withdrawn` (otherwise 422). Pending/declined/withdrawn invitations of candidates who later hid their profile from the company are not listed; accepted ones stay.

```json
{
  "data": [
    {
      "id": 40,
      "status": "accepted", "status_label": "Zaakceptowane",
      "kind": "invitation", "kind_label": "Zaproszenie do rozmowy",
      "message": "Dzień dobry, zapraszamy…",
      "created_at": "2026-10-01T09:00:00+00:00",
      "responded_at": "2026-10-02T12:00:00+00:00",
      "job_share_pair_id": null,
      "offer": { "id": 21, "title": "Specjalistka ds. rekrutacji" },
      "candidate": {
        "id": 12, "anonymous_name": "Marta K.", "full_name": "Marta Kowalska", "email": "marta@example.com",
        "phone": "+48 600 100 200", "photo_url": "https://mumjobs.test/api/v1/candidate-photos/12?v=1a2b3c4d",
        "headline": "Specjalistka ds. rekrutacji IT", "years_of_experience": 6,
        "career_gap_note": null
      },
      "conversation_id": 8
    },
    {
      "id": 41,
      "status": "pending", "status_label": "Czeka na odpowiedź",
      "candidate": { "id": 15, "anonymous_name": "Anna N." },
      "conversation_id": null
    }
  ],
  "links": {}, "meta": {}
}
```

`kind`: `invitation` | `direct_message` (question sent via `/direct-message`). Status labels: pending "Czeka na odpowiedź", accepted "Zaakceptowane", declined "Odrzucone", withdrawn "Wycofane". `conversation_id` (accepted only) is used with the shared conversations endpoints. `phone` and `photo_url` (accepted only, each `null` when the candidate did not provide it) – fetch the photo with the bearer token via `GET /candidate-photos/{profile}` ([shared.md](shared.md)).

---

## Job-sharing pairs

### GET /employer/offers/{offer}/job-share-pairs

Pairs that applied together for a published job-share offer (404 for a regular offer, 403 for another company's / unpublished offer). Includes pairs `submitted` (waiting), `invited`, `rejected`, `hired` (both members accepted) and `declined` (a member declined her invitation); waiting first. Pairs with a member who hid her profile from the company are excluded. Not paginated.

```json
{
  "data": [
    {
      "id": 5,
      "offer_id": 30,
      "status": "submitted", "status_label": "Czeka na decyzję",
      "submitted_at": "2026-10-02T10:00:00+00:00",
      "members": [ { "id": 12, "anonymous_name": "Marta K.", "…": "same anonymous card as the candidate queue" } ],
      "coverage": { "covered": ["Rekrutacja IT", "Onboarding"], "missing": [], "percent": 100 },
      "schedule": [
        { "candidate_profile_id": 12, "starts_at": "08:00", "ends_at": "12:00" },
        { "candidate_profile_id": 14, "starts_at": "12:00", "ends_at": "16:00" }
      ]
    }
  ],
  "offer": {
    "id": 30, "title": "Koordynatorka HR (job sharing)", "city": "Kraków", "start_date": "2027-09-01",
    "salary_min": 9000, "salary_max": 12000, "employment_fraction_label": "1/2 etatu", "work_mode_label": "Hybrydowo",
    "flexible_hours": true, "fixed_meeting_hours": false, "workday_starts_at": "08:00", "workday_ends_at": "16:00"
  }
}
```

Status labels: submitted "Czeka na decyzję", invited "Zaproszona", rejected "Odrzucona", hired "Zatrudniona", declined "Odrzucona przez członkinię". `coverage`: required skills of the offer covered by the two members together (`covered`), the ones neither has (`missing`) and `percent` covered.

### POST /employer/job-share-pairs/{pair}/invitation → 201

Body: `message` (required, max 2000, moderated – same 422 shape as candidate invitations). Throttle 20/min. Creates one pending invitation per member and marks the pair `invited`. Returns the pair resource. Errors: 404 (another company's or hidden pair), 403 "O tej parze już zdecydowano." (pair not `submitted`), 422 "Jedna z osób z pary ma już zaproszenie do tej oferty."

### POST /employer/job-share-pairs/{pair}/reject → 200

Marks the pair `rejected`; returns the pair resource. Same 404/403 rules as the invitation.
