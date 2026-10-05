# Partner customer token

Your service runs on your own backend but is embedded in a shop's storefront. It needs to know which
customer is in front of it — to gate a feature on being logged in, or to count usage per customer.

The rest of this repository covers the server-to-server API, where an OAuth token identifies the
**shop**. That cannot answer this question: it says nothing about the visitor. The shop's session
cookie knows, but it deliberately never leaves the shop's domain.

This endpoint closes that gap.

## The flow

1. A logged-in customer clicks something in your part of the storefront.
2. Your script asks **the shop itself** who is there: `GET /user/partner-token?client_id=…`, same
   origin, so the browser attaches the session cookie by itself.
3. The shop answers with a short-lived note, signed with the secret of **your** API client:

   ```json
   {"token": "<base64url(claims)>.<base64url(hmac-sha256)>", "expires_at": 1790014942}
   ```

4. You send that token to your backend, verify the signature with the same secret, and then trust
   the customer id inside it.

The customer's password and session cookie never reach you. You see the customer id, the e-mail
address and whether they are subscribed to the newsletter — nothing else.

## The claims

```json
{
  "shop": "shop.example.com",
  "customer_id": 1507,
  "email": "customer@example.com",
  "client_id": "7_a1b2c3d4e5",
  "newsletter": true,
  "newsletter_status": "yes",
  "iat": 1790014642,
  "exp": 1790014942
}
```

| Claim | Meaning |
|---|---|
| `shop` | The host the token was issued for. **Always check it** — this is what stops a token from a demo shop being replayed against the live one. |
| `customer_id` | Primary key of the customer account. Stable for the life of the account, including across e-mail changes, and unique *within one shop*. |
| `email` | The address on the account at the moment the token was issued. |
| `client_id` | The client the token was issued to. Check it matches yours. |
| `newsletter` | `true` only for an active subscription. |
| `newsletter_status` | `yes` / `pending` / `no` / `blocked` / `null` — so you can tell an unconfirmed double opt-in apart from a refusal. |
| `iat`, `exp` | Issued-at and expiry, unix seconds. A token lives five minutes. |

## Responses from the endpoint

| Code | Body | When |
|---|---|---|
| `200` | `{"token", "expires_at"}` | A customer is logged in and the client id is known |
| `401` | `{"error": "not_logged_in"}` | Nobody is logged in — or the visitor is a shop admin rather than a customer |
| `400` | `{"error": "missing_client_id"}` | `client_id` absent or empty |
| `400` | `{"error": "unknown_client"}` | No API client on this shop has that id |
| `405` | — | Any method other than `GET` |

The `401` is a real `401` with a JSON body, not a redirect to the login page, so
`response.status === 401` is a reliable test for "not logged in".

## Files here

| File | What it is |
|---|---|
| `storefront-user-scripts.js` | The storefront half: fetches a token, caches it, calls your backend, handles the three refusals. Goes in the shop's `user-scripts.js`. |
| `verify.php` | The smallest correct verifier, plus the checks that are easy to forget. |
| `worker/` | A complete backend as a Cloudflare Worker — CORS, verification, the newsletter gate and a per-customer quota. Deploys in one command; useful as a stand-in while your real service is being built. |

## Four things that are easy to get wrong

**Verify the payload exactly as it arrived.** Parsing the JSON and re-encoding it before computing
the HMAC reorders the keys, and valid tokens start failing.

**Compare signatures in constant time** — `hash_equals` in PHP, `crypto.timingSafeEqual` in Node. A
plain `===` returns early on the first differing byte, which leaks the signature a byte at a time.

**A signature is not enough.** It proves that *somebody holding a secret* signed those claims. Check
`exp`, `shop` and `client_id` as well, or a token minted for another shop or another integration is a
perfectly valid signature over someone else's data.

**Answer the CORS preflight.** The storefront's call to your backend is cross-origin and carries an
`Authorization` header, so the browser sends an `OPTIONS` request first. A backend that does not
answer it fails with a CORS error that the storefront cannot explain — and the error looks like it
comes from the shop. Send the CORS headers on *every* response, error responses included.

## Getting credentials

The shop administrator creates an API client under **Tools → API clients** and gives you a client id
(looks like `7_a1b2c3d4e5`) and a client secret. This endpoint uses no scopes, so that client can be
given the narrowest scope available. The client id is public and lives in browser code; the secret
stays on your server.
