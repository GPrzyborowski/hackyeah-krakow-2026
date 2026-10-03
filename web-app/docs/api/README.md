# MomJobs mobile API v1

Plain REST/JSON API for the MomJobs mobile app. It mirrors the web app (same business rules, privacy rules,
moderation and rate limits) and is grouped into three reference documents:

| Document | Covers |
| --- | --- |
| [candidate.md](candidate.md) | Candidate profile & onboarding, CV analysis, offers with match %, invitations (role `candidate`) |
| [employer.md](employer.md) | Company, offers, candidate swipe, invitations, job-sharing pairs from the employer side (role `employer`, verified e-mail) |
| [shared.md](shared.md) | Public offers/companies/blog, conversations, job sharing (candidate side), legal assistant, notifications, company reviews, push device tokens |

## Base URL

```
https://<host>/api/v1
```

Locally (Sail): `http://localhost/api/v1`. All paths in the docs are relative to the base URL.

## Headers

| Header | Value |
| --- | --- |
| `Accept` | `application/json` (always – guarantees JSON errors) |
| `Content-Type` | `application/json` (or `multipart/form-data` for the CV upload) |
| `Authorization` | `Bearer <token>` for every endpoint except `auth/register`, `auth/login` and the public endpoints |

## Authentication flow

1. `POST /auth/register` (`name`, `email`, `password`, `password_confirmation`, `role` = `candidate`|`employer`,
   employers also `company_name`, `company_nip`, plus `device_name`) → `201 {token, user}`.
   A verification e-mail is sent; employer endpoints require a verified e-mail.
2. `POST /auth/login` (`email`, `password`, `device_name`, e.g. "iPhone Marty") → `200 {token, user}`.
3. Store the token securely (Keychain / Keystore) and send it as `Authorization: Bearer <token>`.
   Tokens do not expire; one token per device.
4. `GET /auth/me` → `{data: user}` – use on app start to restore the session and route by `data.role`
   (`candidate` → onboarding when `data.candidate_profile.published` is false, `employer` → offers).
5. `POST /auth/logout` → `204`, revokes the current token. Also call `DELETE /devices/{token}` for the push token.

```json
{
  "token": "3|Jt0k…",
  "user": {
    "id": 1,
    "name": "Marta Kowalska",
    "email": "marta@momjobs.test",
    "email_verified": true,
    "role": "candidate",
    "company": null,
    "candidate_profile": { "id": 1, "published": true, "onboarding_step": 4 }
  }
}
```

Demo accounts (password `password`): `marta@momjobs.test` (candidate), `hr@zielonebiuro.test` (employer).

## Responses

- Single resources are wrapped in `data`: `{"data": {...}}`.
- Lists are paginated, 20 per page, Laravel style: `data`, `links` (`first`, `last`, `prev`, `next`) and `meta`
  (`current_page`, `last_page`, `per_page`, `total`, …). Request further pages with `?page=N`. Some endpoints add
  their own keys to `meta` (e.g. `meta.unread_count`). Small fixed lists (job-sharing hub, reviews overview) are not paginated.
- Timestamps are ISO 8601 (`2026-10-03T14:05:00+00:00`, UTC); date-only fields are `Y-m-d`.
- Enums are returned as their value plus a Polish `*_label` where the UI shows them.
- Money is an integer in PLN gross.
- Actions without a body return `204 No Content`.

## Errors

All errors are JSON:

| Status | Body | When |
| --- | --- | --- |
| 401 | `{"message": "Unauthenticated."}` | Missing, invalid or revoked token |
| 403 | `{"message": "This action is unauthorized."}` or a Polish reason | Wrong role, not a participant / owner, unverified employer (`"Your email address is not verified."`) |
| 404 | `{"message": "…"}` | Unknown id, unpublished offer/article, or something hidden from you |
| 422 | `{"message": "…", "errors": {"field": ["Polski komunikat"]}}` | Validation failed (messages are Polish and can be shown as-is) |
| 429 | `{"message": "Too Many Attempts."}` + `Retry-After` header | Rate limit hit |

Moderated employer texts (invitation message, chat message) fail with 422 and two keys: `errors.body` (reason)
and `errors.body_suggestion` (a rewritten, acceptable message to offer the user); for invitations the keys are
`errors.message` / `errors.message_suggestion`.

## Polling (no websockets)

Endpoints that change often accept polling cursors:

- `?since=<ISO 8601>` – only items created strictly after that moment (conversation messages, pair messages, notifications).
- `?after_id=<id>` – only items with a greater id (conversation and pair messages). Preferred for chat threads because
  it is immune to two messages created in the same second.

Suggested intervals: open chat thread 5 s, conversation list / notifications badge 30 s
(`GET /notifications/unread-count`), nothing in the background. Lists are newest first; with a cursor you get only
the new items (still paginated – read `meta.last_page`).

## Rate limits (per user, per minute)

| Endpoint | Limit |
| --- | --- |
| `POST /auth/register`, `POST /auth/login` | 10 |
| CV analysis (candidate) | 5 |
| Invitations, job-sharing pair invitations, employer pair invitations | 20 |
| Conversation messages, pair chat messages | 30 |
| Legal assistant questions | 10 |
| Employer match preview | 60 |

## Privacy rules (same as the web app)

- Employers see candidates only anonymously (first name + surname initial, headline, experience, AI summary,
  confirmed skills, availability, work modes/fractions, match %) until the candidate accepts an invitation.
  Then the inviting company sees full name and e-mail – only inside that conversation.
- Never exposed to other users: surname before acceptance, e-mail, CV file / text, due date, leave dates, reason of the gap.
- Profiles hidden from a company are invisible to it.
- Job-sharing partner search and pairs show other candidates in the same anonymous form; the pair chat is visible
  only to the two members who accepted the pair (never to the employer).
- Public endpoints (offers, companies, blog) never return candidate data; company reviews are anonymous
  (only the author's optional self-chosen label).
- Employer-authored texts are moderated: questions about pregnancy or family plans are rejected with a suggestion.
