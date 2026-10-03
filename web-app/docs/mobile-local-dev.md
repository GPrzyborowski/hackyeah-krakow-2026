# Running the backend for mobile development

How to run the API on your machine and connect the mobile app (emulator or a real phone) to it.
No Laravel knowledge needed. The endpoint reference is in [api/README.md](api/README.md).

## 1. Start the backend

First time: follow [setup-windows.md](setup-windows.md) or [setup-linux-macos.md](setup-linux-macos.md) (ends with `./setup.sh`).

Every next time (on Windows: in the **Ubuntu** terminal, with Docker Desktop running):

```bash
cd ~/code/hackyeah-krakow-2026/web-app
vendor/bin/sail up -d
```

Check it works — open http://localhost/api/v1/openapi.yaml in the browser, or:

```bash
curl -s -X POST http://localhost/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"marta@mumjobs.test","password":"password","device_name":"curl"}'
```

You should get `{"token": "...", "user": {...}}`.

Stop it at the end of the day with `vendor/bin/sail stop`. You don't need `npm run dev` for API work.

### "Every GET I open doesn't work"

Almost every endpoint needs a token, so opening it in a browser address bar returns `401 {"message":"Unauthenticated."}`.
That's expected — the backend works. Only these GETs work without a token:

- `/api/v1/public/offers`, `/api/v1/public/offers/{id}`, `/api/v1/public/companies/{id}`
- `/api/v1/articles`, `/api/v1/articles/{slug}`
- `/api/v1/openapi.yaml`

For everything else: log in first (`POST /auth/login`, the curl above), then send the token with every request:

```bash
TOKEN=<token from the login response>
curl -s http://localhost/api/v1/candidate/profile -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

Also check:

- **The path** — all paths start with `/api/v1/` and are listed in [api/README.md](api/README.md) / `openapi.yaml`.
  There's no `/api/v1/offers`: it's `/public/offers` (no login), `/candidate/offers` (candidate) or `/employer/offers` (employer). A wrong path returns `404 "The route … could not be found."`.
- **The role** — `/candidate/*` needs a candidate token (`marta@mumjobs.test`), `/employer/*` an employer token
  (`hr@zielonebiuro.test`). The wrong one returns `403`.
- **No response at all** (timeout / connection refused) is a network problem, not the API — see step 2.

Easiest way to click through GETs: Postman with the collection from step 6 — it logs in and adds the token for you.

## 2. Point the app at the backend

The base URL depends on where the app runs:

| App runs on | Base URL |
| --- | --- |
| Android emulator | `http://10.0.2.2/api/v1` |
| iOS simulator (macOS) | `http://localhost/api/v1` |
| Real phone, same Wi-Fi | `http://<your computer's IP>/api/v1` (see below) |
| Real phone, any network | HTTPS tunnel (see below) |

The backend is plain **HTTP** locally. Allow cleartext traffic in **debug builds only**:
Android — `android:usesCleartextTraffic="true"` (or a network security config) in the debug manifest;
iOS — an `NSAppTransportSecurity` exception for your dev host. A tunnel gives you HTTPS and avoids this.

### Real phone on the same Wi-Fi

1. Find your computer's IP: Windows — `ipconfig` in PowerShell (the Wi-Fi adapter's IPv4, e.g. `192.168.1.23`,
   **not** the WSL `172.x` one); macOS — `ipconfig getifaddr en0`; Linux — `hostname -I`.
2. **Windows only:** allow port 80 through the firewall (PowerShell as Administrator, once):

   ```powershell
   New-NetFirewallRule -DisplayName "mumjobs dev" -Direction Inbound -LocalPort 80 -Protocol TCP -Action Allow
   ```

3. Open `http://192.168.1.23/api/v1/openapi.yaml` in the phone's browser. If it loads, the app will work too.

Guest / corporate Wi-Fi often blocks phone-to-laptop traffic — use a tunnel then.

### HTTPS tunnel (any network, no firewall changes)

```bash
docker run --rm --add-host=host.docker.internal:host-gateway \
  cloudflare/cloudflared:latest tunnel --url http://host.docker.internal:80
```

It prints a `https://<random>.trycloudflare.com` address — use `https://<random>.trycloudflare.com/api/v1`.
The address changes every run. URLs the API builds itself (e.g. `photo_url`) may come back as `http://…`;
switch them to `https` in the app if needed.

## 3. Test accounts

All passwords are `password`. Accounts are verified and filled with demo data.

| E-mail | Role | Good for |
| --- | --- | --- |
| `marta@mumjobs.test` | candidate | Finished profile, offers with match %, invitations, conversations, notifications |
| `ewa@mumjobs.test` | candidate | Job-sharing partner of Marta |
| `hr@zielonebiuro.test` | employer | Dashboard, offers, candidate swipe, invitations, chat with Marta |
| `rekrutacja@kamienica.test` | employer | Second company |

`admin@mumjobs.test` can't log in through the API (`403`), only on the web.

Log in as the employer in a browser (http://localhost) and as the candidate in the app to see both sides of
a conversation or invitation live.

## 4. Things that work differently locally

- **E-mails** (verification, password reset) aren't sent anywhere — they land in Mailpit: http://localhost:8025.
- **E-mail verification of a new account:** the link is built with the host the app used (e.g. `10.0.2.2`),
  so it may not open on your computer. Easiest is to verify the account by hand:

  ```bash
  vendor/bin/sail artisan tinker --execute 'App\Models\User::firstWhere("email", "new@example.com")->markEmailAsVerified();'
  ```

- **AI** (CV analysis, moderation, legal assistant) works without an API key using a simpler built-in fallback.
  For real AI answers put `ANTHROPIC_API_KEY=...` in `.env`.
- **Push notifications** are silently skipped until `FCM_CREDENTIALS` (service-account JSON or path) is set in
  `.env`. `POST /devices` still works, and every push also exists as an in-app notification (`GET /notifications`).
- **Rate limits** are on locally too — e.g. 5 login attempts per minute. Wait a minute on `429`.
- **Tokens** last 60 days and survive restarts, but not a database reset.

## 5. Reset the data

Messed up the demo data? Back to a clean state (all API tokens are invalidated — log in again):

```bash
vendor/bin/sail artisan migrate:fresh --seed
```

After `git pull`, if the backend changed:

```bash
vendor/bin/sail composer install && vendor/bin/sail artisan migrate
```

## 6. Explore the API without the app

- **Postman:** import [api/mumjobs.postman_collection.json](api/mumjobs.postman_collection.json), set `baseUrl`
  to `http://localhost/api/v1`, run *Auth → Login as candidate / employer* — the token is stored for you.
- **Swagger / Insomnia:** import http://localhost/api/v1/openapi.yaml.

## 7. Backend tests

The API has its own feature tests (`tests/Feature/Api`). Run them before you report a backend bug — or to
confirm one:

```bash
vendor/bin/sail artisan test --compact tests/Feature/Api       # API only
vendor/bin/sail artisan test --compact                         # everything
```

Tests use a separate database, so they don't touch your demo data.

## Troubleshooting

| Problem | Fix |
| --- | --- |
| App: "connection refused" / timeout | Wrong base URL for your device (table in step 2), containers not running (`vendor/bin/sail up -d`), or Windows firewall. |
| Android: `CLEARTEXT communication not permitted` | Allow cleartext in the debug build, or use the tunnel. |
| Phone browser can't open `http://<IP>/…` | Firewall rule missing, wrong IP (use the Wi-Fi one, not WSL/Docker), or the network isolates devices — use the tunnel. |
| `401 Unauthenticated` after it worked before | Token was revoked (database reset, logout-all, password change). Log in again. |
| `403` with `email_verification_required` | New account not verified — see step 4. |
| Port 80 already taken | Set `APP_PORT=8080` in `.env`, `vendor/bin/sail up -d`, and add `:8080` to every URL above. |
| `500` / something weird on the server | Read the end of the log: `tail -n 50 storage/logs/laravel.log`. |
