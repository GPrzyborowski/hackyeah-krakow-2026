# MomJobs API v1 – Employer

Base URL: `/api/v1`. Every endpoint below requires `Authorization: Bearer <token>` (from `POST /auth/login`), a user with role `employer`, a **verified email address** and a company attached to the account. Send `Accept: application/json`. All data is scoped to the signed-in employer's company.

Common errors:

| Status | When |
| --- | --- |
| 401 | Missing / invalid token (`{"message": "Unauthenticated."}`) |
| 403 | Not an employer, email not verified (`"Your email address is not verified."`), no company, or the offer belongs to another company / is not in the right state |
| 404 | Unknown id; candidate not matched to the offer or hidden from the company; job-share pair of another company or hidden |
| 422 | Validation failed: `{"message": "...", "errors": {"field": ["Polish message"]}}` |
| 429 | Throttled: skills 60/min, preview-matches 60/min, invitations 20/min, direct messages 20/min, pair invitations 20/min |

Conventions: resources wrapped in `data`; lists paginated (20 per page) with Laravel's `data` / `links` / `meta` – pass `?page=N`. Dates `Y-m-d`, timestamps ISO 8601. Money in PLN gross (integers). Enums as value plus `*_label` (Polish).

**Privacy.** Before a candidate accepts an invitation the employer only ever sees the anonymous card: first name + surname initial (`anonymous_name`), headline, years of experience, AI summary, confirmed skills, `available_from`, employment fractions, work modes, match. Never surname, email, CV, due/leave dates. Candidates who hid their profile from the company never appear. Full name and email appear only on accepted invitations (`GET /employer/invitations`).

**Moderation.** Employer-authored text (offer title/description, company description, invitation messages) is checked for questions about pregnancy/family. Blocked text → 422 on that field.

---

## Company

### GET /employer/company

```json
{
  "data": {
    "id": 3,
    "name": "Zielone Biuro",
    "nip": "1234567890",
    "city": "Kraków",
    "description": "Elastyczne godziny…",
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

### PUT /employer/company

Body: `name` (required, max 255), `nip` (optional, 10 digits; spaces and dashes are stripped, e.g. `123-456-78-90`), `city`, `description` (max 5000, moderated). Returns the company resource.

Errors: 422 `nip` ("NIP musi składać się z 10 cyfr."), 422 `description` (moderation reason + suggestion).

---

## Skills autocomplete

### GET /employer/skills?q=rekru

Up to 10 skills whose name or slug contains `q` (all skills when `q` is empty), sorted by name. Throttle 60/min.

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

Body (same rules as the web form):

| Field | Rules |
| --- | --- |
| `action` | required, `draft` or `publish` |
| `title` | required, max 255, moderated |
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

Same body as POST (skills are replaced). Returns the offer resource. 403 for another company's offer.

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
        "headline": "Specjalistka ds. rekrutacji IT", "years_of_experience": 6
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

`kind`: `invitation` | `direct_message` (question sent via `/direct-message`). Status labels: pending "Czeka na odpowiedź", accepted "Zaakceptowane", declined "Odrzucone", withdrawn "Wycofane". `conversation_id` (accepted only) is used with the shared conversations endpoints.

---

## Job-sharing pairs

### GET /employer/offers/{offer}/job-share-pairs

Pairs that applied together for a published job-share offer (404 for a regular offer, 403 for another company's / unpublished offer). Includes pairs `submitted` (waiting), `invited` and `rejected`; waiting first. Pairs with a member who hid her profile from the company are excluded. Not paginated.

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

Status labels: submitted "Czeka na decyzję", invited "Zaproszona", rejected "Odrzucona".

### POST /employer/job-share-pairs/{pair}/invitation → 201

Body: `message` (required, max 2000, moderated – same 422 shape as candidate invitations). Throttle 20/min. Creates one pending invitation per member and marks the pair `invited`. Returns the pair resource. Errors: 404 (another company's or hidden pair), 403 "O tej parze już zdecydowano." (pair not `submitted`), 422 "Jedna z osób z pary ma już zaproszenie do tej oferty."

### POST /employer/job-share-pairs/{pair}/reject → 200

Marks the pair `rejected`; returns the pair resource. Same 404/403 rules as the invitation.
