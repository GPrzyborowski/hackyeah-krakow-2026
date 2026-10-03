# MomJobs API v1 – Candidate

Base URL: `/api/v1`. Every endpoint below requires `Authorization: Bearer <token>` (from `POST /auth/login`) and a user with role `candidate`. Send `Accept: application/json`.

Common errors:

| Status | When |
| --- | --- |
| 401 | Missing / invalid token (`{"message": "Unauthenticated."}`) |
| 403 | Signed-in user is not a candidate, or the action is not allowed (`{"message": "..."}`) |
| 404 | Unknown id, or the offer is not published |
| 422 | Validation failed: `{"message": "...", "errors": {"field": ["Polish message"]}}` |
| 429 | Throttled (CV analysis: 5 per minute) |

Conventions: resources wrapped in `data`; lists paginated (20 per page) with Laravel's `data` / `links` / `meta` (`meta.current_page`, `meta.last_page`, `meta.total`, …) – pass `?page=N`. Dates `Y-m-d`, timestamps ISO 8601. Money in PLN gross (integers). Enums as value plus `*_label` (Polish).

---

## Profile & onboarding

All profile mutations return the updated **profile resource** (same as `GET /candidate/profile`).

### GET /candidate/profile

The candidate's own full profile, including private dates (never shown to employers).

```json
{
  "data": {
    "id": 12,
    "full_name": "Marta Kowalska",
    "anonymous_name": "Marta K.",
    "headline": "Specjalistka ds. rekrutacji IT",
    "years_of_experience": 6,
    "city": "Kraków",
    "ai_summary": "Rekruterka IT z 6-letnim doświadczeniem…",
    "available_from": "2027-09-01",
    "leave_starts_on": "2026-12-01",
    "due_date": "2027-01-02",
    "work_modes": [{ "value": "hybrid", "label": "Hybrydowo" }],
    "employment_fractions": [{ "value": "3/5", "label": "3/5 etatu" }],
    "wants_flexible_hours": true,
    "open_to_job_sharing": false,
    "preferred_day_part": "morning",
    "preferred_day_part_label": "Poranki",
    "privacy": {
      "show_availability_instead_of_gap": true,
      "allow_direct_messages": false,
      "hidden_from_company": { "id": 3, "name": "Obecny Pracodawca" }
    },
    "cv": { "original_name": "CV.pdf", "size": 204800, "status": "parsed", "has_text": true },
    "skills": [
      { "id": 5, "name": "Excel", "source": "ai", "confirmed": false },
      { "id": 7, "name": "Rekrutacja IT", "source": "manual", "confirmed": true }
    ],
    "suggested_positions": [{ "title": "Specjalistka ds. rekrutacji", "score": 92 }],
    "onboarding_step": 4,
    "published": true,
    "published_at": "2026-10-01T10:00:00+00:00"
  }
}
```

`cv.status`: `uploaded` | `parsing` | `parsed` | `failed` | `null`. Only `confirmed` skills are visible to employers and used for matching. The CV file path and raw CV text are never returned.

### GET /candidate/profile/options

Choice lists for the profile forms.

```json
{ "data": {
  "work_modes": [{ "value": "remote", "label": "Zdalnie" }, …],
  "employment_fractions": [{ "value": "1", "label": "Pełny etat" }, …],
  "day_parts": [{ "value": "morning", "label": "Poranki" }, …],
  "companies": [{ "id": 3, "name": "Zielone Biuro" }, …],
  "skill_suggestions": ["Excel", "Rekrutacja IT", …]
} }
```

`companies` feeds the "hide my profile from my current employer" picker (`hidden_from_company_id`).

### POST /candidate/profile/cv

`multipart/form-data`. Throttled: 5 requests per minute.

| Field | Rules |
| --- | --- |
| `cv` | PDF file, max 5 MB; required without `cv_text` |
| `cv_text` | string, max 20000; required without `cv` |

Stores the CV privately and runs the CV analyzer: proposes **unconfirmed** skills (`source: "ai"`), `suggested_positions`, `ai_summary`, and fills an empty headline / years of experience. Response: profile resource plus

```json
"meta": { "analysis_succeeded": true, "message": "Przeczytaliśmy Twoje CV. Sprawdź proponowane tagi." }
```

If the analyzer fails, the response is still `200` with `analysis_succeeded: false`, `data.cv.status: "failed"` – ask the user to add skills manually.

### POST /candidate/profile/skills

Add a skill tag typed by the candidate (dictionary skill or a new one). Confirmed immediately. Body: `name` (required, 2–60 chars). Response `201` + profile.

### DELETE /candidate/profile/skills/{skill}

Remove a tag (`skill` = skill id). Response `200` + profile.

### POST /candidate/profile/skills/confirm

Confirm all remaining (AI-proposed) tags; onboarding step ≥ 2. `422` `errors.skills` when the profile has no skills.

### PUT /candidate/profile/preferences

| Field | Rules |
| --- | --- |
| `available_from` | **required**, date |
| `headline` | nullable string, max 120 |
| `years_of_experience` | nullable int 0–50 |
| `city` | nullable string, max 100 |
| `work_modes[]` | `remote` \| `hybrid` \| `onsite` (see options) |
| `employment_fractions[]` | `1` \| `3/4` \| `3/5` \| `1/2` |
| `wants_flexible_hours`, `open_to_job_sharing` | boolean |
| `preferred_day_part` | nullable `morning` \| `afternoon` \| `any` |
| `leave_starts_on`, `due_date` | nullable date (private) |

Omitted `work_modes` / `employment_fractions` are saved as empty. Onboarding step ≥ 3.

### PATCH /candidate/profile/privacy

Partial update; send only the toggles that changed: `show_availability_instead_of_gap` (bool), `allow_direct_messages` (bool), `hidden_from_company_id` (nullable company id).

### PATCH /candidate/profile/summary

Body: `ai_summary` (nullable string, max 400; empty clears it). Rejected (`422`) when it contains an e-mail / phone number or mentions pregnancy, children or family plans.

### POST /candidate/profile/publish

Optional body: the privacy fields above. Publishes the profile (onboarding step 4). `422` with `errors.available_from` and/or `errors.skills` when the start date or a confirmed skill is missing.

### POST /candidate/profile/visibility

Body: `visible` (required boolean). `false` hides the profile from employers, `true` shows it again (same `422` checks as publish).

### GET /candidate/profile/employer-preview

Exactly what employers see before an invitation is accepted (anonymous allowlist; no surname, e-mail, CV or private dates).

```json
{ "data": {
  "id": 12, "anonymous_name": "Marta K.", "initial": "M",
  "headline": "Specjalistka ds. rekrutacji IT", "years_of_experience": 6,
  "ai_summary": "…",
  "skills": [{ "name": "Rekrutacja IT", "matched": false }],
  "available_from": "2027-09-01",
  "employment_fractions": ["3/5 etatu"], "work_modes": ["Hybrydowo"],
  "match": null, "is_interested": false
} }
```

---

## Home

### GET /candidate/home

```json
{ "data": {
  "greeting": { "first_name": "Marta" },
  "profile": { "published": true, "onboarding_step": 4 },
  "calendar": {
    "pregnancy_week": 27, "due_date": "2027-01-02", "leave_starts_on": "2026-12-01",
    "available_from": "2027-09-01", "current_phase": "pregnancy"
  },
  "invitations": { "pending_count": 2, "company_names": ["Zielone Biuro", "Kamienica"] },
  "pair_invitations_count": 1,
  "saved_offers_count": 3,
  "top_offers": [ /* 3 × offer card, see below */ ]
} }
```

`calendar.current_phase`: `pregnancy` | `leave` | `ready`; any date may be `null`. When `profile.published` is `false` the app should send the user to onboarding.

---

## Offers

### GET /candidate/offers

Published offers ranked by match (same filters as the web list). Query parameters:

| Param | Meaning |
| --- | --- |
| `q` | text in title or skill names |
| `location` | city substring; a value containing "zdaln" means remote offers |
| `work_modes[]` | `remote`, `hybrid`, `onsite` |
| `employment_fractions[]` | `1`, `3/4`, `3/5`, `1/2` |
| `flexible_hours`, `childcare_subsidy`, `with_reviews`, `job_share`, `saved` | `1` to enable |
| `start_from` | date; offers starting no earlier than 30 days before it. Defaults to the candidate's `available_from`; send `start_from=` (empty) to disable |
| `sort` | `match` (default) or `newest` |
| `page` | page number (20 per page) |

Offer card (also used in `home.top_offers`):

```json
{
  "id": 41, "title": "Specjalistka ds. rekrutacji IT", "city": "Kraków",
  "work_mode": "hybrid", "work_mode_label": "Hybrydowo",
  "employment_fraction": "3/5", "employment_fraction_label": "3/5 etatu",
  "salary_min": 6000, "salary_max": 8000, "start_date": "2027-09-01",
  "flexible_hours": true, "fixed_meeting_hours": false, "childcare_subsidy": true,
  "job_share": { "is_job_share": false, "workday_starts_at": null, "workday_ends_at": null, "hours_per_person": null },
  "published_at": "2026-09-20T08:00:00+00:00",
  "is_parent_friendly": true, "is_interested": false, "is_saved": true,
  "match": {
    "score": 80, "matched_required": ["Rekrutacja IT"], "missing_required": [],
    "matched_nice_to_have": [], "missing_nice_to_have": ["Onboarding"], "start_date_compatible": true
  },
  "company": {
    "id": 3, "name": "Zielone Biuro", "city": "Kraków", "average_rating": 4.7, "reviews_count": 5,
    "first_review": { "quote": "Wróciłam bez stresu.", "author_label": "Mama jednego dziecka" }
  }
}
```

The list response adds `meta.filters` (the effective filters, incl. the defaulted `start_from`), `meta.has_confirmed_skills`, `meta.work_modes` and `meta.employment_fractions` (filter choices).

### GET /candidate/offers/{offer}

Offer card plus:

```json
"description": "…", "company_description": "…",
"job_sharing": {
  "workday_starts_at": "08:00", "workday_ends_at": "16:00", "hours_per_person": 4,
  "is_open_to_job_sharing": true,
  "pair": { "id": 9, "status": "forming", "partner_name": "Anna N.", "awaiting_my_answer": true }
},
"reviews": [{
  "id": 1, "quote": "…", "author_label": "Mama bliźniaków", "rating": 4.7,
  "rating_return": 5, "rating_flexibility": 4, "rating_no_pregnancy_questions": 5,
  "created_at": "2026-08-01T12:00:00+00:00"
}]
```

`job_sharing` is `null` for regular offers; `pair` is `null` when she is not in a pair for this offer. `404` for unpublished offers.

### POST /candidate/offers/{offer}/interest · DELETE /candidate/offers/{offer}/interest

"Pokaż zainteresowanie" – signal / withdraw interest. Idempotent, `204 No Content`. `404` when posting to an unpublished offer.

### POST /candidate/offers/{offer}/save · DELETE /candidate/offers/{offer}/save

Bookmark / remove bookmark. Idempotent, `204 No Content`. `404` when saving an unpublished offer.

---

## Invitations

### GET /candidate/invitations

Paginated, pending first, then newest.

```json
{ "data": [{
  "id": 5, "status": "pending", "status_label": "Oczekuje na odpowiedź",
  "message": "Dzień dobry, zapraszamy do rozmowy…",
  "created_at": "2026-10-01T09:00:00+00:00", "responded_at": null,
  "conversation_id": null,
  "job_share_pair": null,
  "offer": {
    "id": 41, "title": "…", "city": "Kraków",
    "work_mode": "hybrid", "work_mode_label": "Hybrydowo",
    "employment_fraction": "3/5", "employment_fraction_label": "3/5 etatu",
    "is_published": true
  },
  "company": { "id": 3, "name": "Zielone Biuro", "average_rating": 4.7, "reviews_count": 5 }
}], "links": { … }, "meta": { … } }
```

`status`: `pending` | `accepted` | `declined` | `withdrawn`. For job-sharing invitations `job_share_pair` is `{ "id": 9, "partner_name": "Anna N." }`.

### POST /candidate/invitations/{invitation}/accept

Accepts and opens the chat; the company now sees her full name and e-mail. Returns the invitation with `status: "accepted"` and `conversation_id` (open the conversation with the conversations API). `403` for someone else's invitation or one already answered (`"message": "Na to zaproszenie już odpowiedziano."`).

### POST /candidate/invitations/{invitation}/decline

Declines; the candidate stays anonymous. Returns the invitation with `status: "declined"`. Same `403` rules.
