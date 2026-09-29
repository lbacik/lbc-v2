# SES tenant mail (contact form)

The contact form sends only through a deployment-owned SES v2 tenant via
`App\Mail\SesTenantMailSender` (AsyncAws `SesClient` + `SendEmailRequest`).
There is no SMTP/shared fallback: `MAILER_DSN` and the legacy static token
were removed.

## Fixed boundary

| Value              | Expected                    | Env var                  |
|--------------------|-----------------------------|--------------------------|
| Tenant name        | deployment-owned (required) | `SES_TENANT_NAME`        |
| Configuration set  | `lbc-first-contact-events`  | `SES_CONFIGURATION_SET`  |
| Region             | `eu-central-1`              | `SES_REGION`             |
| From identity      | `no-reply@lukaszbacik.com`  | `MAIL_FROM_ADDRESS`      |
| Operator recipient | deployment-owned (required) | `MAIL_OPERATOR_RECIPIENT`|
| Dedicated IAM key  | secret; required unless `MAIL_MODE=disabled` | `SES_ACCESS_KEY_ID` / `SES_SECRET_ACCESS_KEY` |

`App\Mail\MailBoundary::assertValid()` runs before every send and rejects
missing or wrong values. The sender API accepts a `ContactMessage` only, so
HTTP input, template data, and headers can never select another boundary.
The visitor's address is validated and used only as Reply-To.

## Mail modes (`MAIL_MODE`, default `disabled`)

- `disabled` — `/contact` answers 503; nothing is sent.
- `validation` — operator-restricted and time-bounded. Requires
  `MAIL_VALIDATION_UNTIL` (e.g. `2026-10-03 12:00:00+02:00`) and
  `MAIL_VALIDATION_TOKEN`. Each validation request must carry the token in
  the `X-Contact-Validation-Token` header; otherwise the send is rejected
  with 403. Every validation send is logged with `audit: true`
  (correlation ID, outcome, SES message ID, latency — never content,
  addresses, tokens, credentials, or raw AWS exceptions).
- `enabled` — normal operation.

The dedicated IAM key may be absent only in `disabled` mode, so the app can be
deployed before the key is issued. `validation` and `enabled` refuse to start
without it (`MailModeGate`), and the deploy workflow fails before rollout. An
empty key must never reach a send: the SES client would fall back to its
default credential chain and could send as a foreign identity.

Enabling `validation` against the real SES tenant additionally depends on the
non-sending infrastructure in the `aws` repo (`infrastructure/ses-tenants`)
being deployed and the account gate being clear.

## Two-key rotation (dedicated IAM key)

1. In IAM, create a second access key for the tenant user. Do not touch the
   active key.
2. Deploy with the new key (`SES_ACCESS_KEY_ID` / `SES_SECRET_ACCESS_KEY`),
   then submit the form in `validation` mode and confirm the SES message ID
   in the logs.
3. Deactivate (do not delete yet) the old key in IAM. Verify once more.
4. Delete the old key in IAM.

At every step at most one key is active for serving traffic, and rollback is
re-deploying the previous key.

## Request contract (`POST /contact`)

1. Application rate limit per client IP (429 when exhausted).
2. Session CSRF token `_csrf_token` / id `contact` (403 when invalid).
3. Mail mode gate (503 when disabled/expired, 403 when operator token is
   wrong or missing).
4. Strict DTO: allowlisted fields, byte limits, normalization, server-side
   constraints (422 when invalid). Unknown fields — including the removed
   legacy `token` — are rejected.
5. reCAPTCHA with pinned action (`php_email_form_submit`), expected hostname,
   and score threshold (422 when verification fails).
6. SES send. Success is `202` with body `OK` and means only
   *accepted-for-delivery-attempt* after SES returned a message ID — never a
   delivery/read confirmation. Failures are generic, carry the alternative
   contact route (`lukasz@lukaszbacik.com`), and never leak PII or secrets.
