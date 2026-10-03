# mumjobs mobile API v1

Plain REST/JSON API for the mumjobs mobile app. It mirrors the web app (same business rules, privacy rules,
moderation and rate limits) and is grouped into three reference documents:

| Document | Covers |
| --- | --- |
| [candidate.md](candidate.md) | Candidate profile & onboarding, CV analysis, offers with match %, invitations (role `candidate`) |
| [employer.md](employer.md) | Company, offers, candidate swipe, invitations, job-sharing pairs from the employer side (role `employer`, verified e-mail) |
| [shared.md](shared.md) | Public offers/companies/blog, conversations, job sharing (candidate side), legal assistant, notifications, company reviews, push device tokens |

Machine-readable versions:

- [openapi.yaml](openapi.yaml) – OpenAPI 3.1 spec of every endpoint (also served outside production at
  `GET /api/v1/openapi.yaml`, e.g. `http://localhost/api/v1/openapi.yaml` – import it into Swagger UI, Redocly, Insomnia…).
  `tests/Feature/Api/OpenApiSpecTest.php` fails when a route is missing from the spec (or the spec lists a removed one).
- [mumjobs.postman_collection.json](mumjobs.postman_collection.json) – Postman v2.1 collection (folders per area,
  `{{baseUrl}}` / `{{token}}` variables). Run *Auth → Login as candidate / employer* first; it stores the token.
  Generated from the spec – after changing `openapi.yaml` run `vendor/bin/sail artisan mumjobs:postman` (don't edit the JSON by hand).

## Base URL

```
https://<host>/api/v1
```

Locally (Sail): `http://localhost/api/v1` (Android emulator: `http://10.0.2.2/api/v1`; real phone, tunnel, test accounts:
[../mobile-local-dev.md](../mobile-local-dev.md)). All paths in the docs are relative to the base URL.

## Headers

| Header | Value |
| --- | --- |
| `Accept` | `application/json` (recommended; the server forces it for every `/api/*` request, so errors are JSON even without it) |
| `Content-Type` | `application/json` (or `multipart/form-data` for the CV upload) |
| `Authorization` | `Bearer <token>` for every endpoint except `auth/register`, `auth/login` and the public endpoints |

## Authentication flow

1. `POST /auth/register` (`name`, `email`, `password`, `password_confirmation`, `role` = `candidate`|`employer`,
   employers also `company_name`, `company_nip`, plus `device_name`) → `201 {token, user}`.
   A verification e-mail is sent; employer endpoints require a verified e-mail.
2. `POST /auth/login` (`email`, `password`, `device_name`, e.g. "iPhone Marty") → `200 {token, user}`.
   - **Two-factor authentication:** when the account has 2FA enabled (set up in the web app), the first attempt
     returns `422` with `"two_factor_required": true` and `errors.code`. Show a code field and repeat the same
     request adding `code` (6-digit TOTP from the authenticator app) **or** `recovery_code` (single use – it is
     replaced after a successful login). A wrong code returns the same `422` shape.
   - Admin accounts are refused with `403` ("Panel administratora jest dostępny tylko w przeglądarce.").
   - Throttled: 5 attempts/min per e-mail + IP and 20/min per e-mail (`429`).
3. Store the token securely (Keychain / Keystore) and send it as `Authorization: Bearer <token>`.
   One token per device. **Tokens expire after 60 days** (`SANCTUM_TOKEN_EXPIRATION`, minutes) – on `401` send the
   user back to login. All tokens are also revoked when the password is changed or reset (web app).
4. `GET /auth/me` → `{data: user}` – use on app start to restore the session and route by `data.role`
   (`candidate` → onboarding when `data.candidate_profile.published` is false, `employer` → `GET /employer/dashboard`).
5. `POST /auth/logout` → `204`, revokes the current token. A push token registered with `POST /devices` using this
   API token is deleted automatically (device tokens are bound to the API token that registered them).
6. `POST /auth/logout-all` → `204`, revokes every token of the user (all devices, with their push tokens).
7. `PATCH /auth/me/preferences` (`{"push_enabled": false}`) → `200 {data: user}`. Turns push notifications off
   (or on) for all devices of the account; registered devices are kept. `data.push_enabled` is also in `GET /auth/me`.
8. `POST /auth/email/verification-notification` (authenticated, 6/min) → `202 {message}` sends a new verification
   link (`200` with "Adres e-mail jest już zweryfikowany." when nothing is needed). **The link opens in the browser**
   (web app) – after clicking it, call `GET /auth/me` again; `data.email_verified` becomes `true`.

```json
{
  "token": "3|Jt0k…",
  "user": {
    "id": 1,
    "name": "Marta Kowalska",
    "email": "marta@mumjobs.test",
    "email_verified": true,
    "role": "candidate",
    "push_enabled": true,
    "company": null,
    "candidate_profile": { "id": 1, "published": true, "onboarding_step": 4 }
  }
}
```

Demo accounts (password `password`): `marta@mumjobs.test` (candidate), `hr@zielonebiuro.test` (employer).

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
| 403 | `{"message": "This action is unauthorized."}` or a Polish reason | Wrong role, not a participant / owner, action not allowed in the current state |
| 403 | `{"message": "Potwierdź swój adres e-mail…", "email_verification_required": true}` | Unverified e-mail: all employer endpoints and accepting an invitation (candidate). Offer "resend link" (`POST /auth/email/verification-notification`) |
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

## Push notifications

Register the device's FCM registration token with `POST /devices` (iOS too – use the Firebase SDK, which maps the APNs
token to an FCM token). The server sends a push for every in-app notification (new invitation, accepted/declined
invitation, new message, job-sharing pair events, company verification) to all devices of the user when
`push_enabled` is true. Payload:

```json
{
  "notification": { "title": "Nowa wiadomość od: Zielone Biuro", "body": "Dzień dobry…" },
  "data": { "kind": "new_message", "notification_id": "9b1c…", "conversation_id": "12", "url": "/conversations/12" }
}
```

`data` values are always strings: `kind` (same as `GET /notifications`), `notification_id` (pass to
`POST /notifications/{id}/read`), the target ids that the notification has (`conversation_id`, `invitation_id`,
`job_share_pair_id`, `company_id`) and the web `url`. `body` may be missing. Tokens that FCM reports as unregistered
or invalid are deleted on the server – register again on the next app start.

## Rate limits (per minute)

Every authenticated endpoint shares a general limit of **120 requests/min per user**; the endpoints below have an
additional, stricter limit.

| Endpoint | Limit |
| --- | --- |
| `POST /auth/register` | 10 per IP |
| `POST /auth/login` | 5 per e-mail + IP, 20 per e-mail |
| `POST /auth/email/verification-notification` | 6 |
| Moderated writes: `PATCH /candidate/profile/summary`, `PUT /employer/company`, `POST /employer/offers`, `PUT /employer/offers/{id}` | 20 |
| CV analysis (candidate) | 5 |
| Invitations, job-sharing pair invitations, employer pair invitations | 20 |
| Conversation messages, pair chat messages | 30 |
| Legal assistant questions | 10 |
| Employer match preview | 60 |

## Privacy rules (same as the web app)

- Employers see candidates only anonymously (first name + surname initial, headline, experience, AI summary,
  confirmed skills, availability, work modes/fractions, match %) until the candidate accepts an invitation.
  Then the inviting company sees full name, e-mail, phone and photo (`photo_url` → authorised `GET /candidate-photos/{profile}`).
- Never exposed to other users: surname before acceptance, e-mail, phone, photo, CV file / text, due date, leave dates, reason of the gap.
- Profiles hidden from a company are invisible to it.
- Job-sharing partner search and pairs show other candidates in the same anonymous form; the pair chat is visible
  only to the two members who accepted the pair (never to the employer).
- Public endpoints (offers, companies, blog) never return candidate data; company reviews are anonymous
  (only the author's optional self-chosen label).
- Employer-authored texts are moderated: questions about pregnancy or family plans are rejected with a suggestion.
