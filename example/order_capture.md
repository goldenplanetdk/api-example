# Capturing an order

An online payment module only **reserves** (authorizes) the money at checkout. The customer is not
charged until the payment is **captured**. This endpoint performs that capture through the shop, so
the amount, the transaction id and the shop's own bookkeeping all come from the order — you never
talk to the gateway yourself. v3 only.

| What | Endpoint |
|---|---|
| Capture | `POST /api/v3/orders/{order_id}/capture.json` |
| Read the order back | `GET /api/v3/orders/{order_id}.json` |
| Refund (the opposite) | `POST /api/v3/orders/{order_id}/refunds.json` — see [order.md](order.md) |

Needs the `order_write` scope. There is **no payload** and no query parameter.

```bash
curl -i -XPOST 'http://SHOP_DOMAIN/api/v3/orders/1015/capture.json' \
  -H 'Authorization: Bearer ACCESS_TOKEN'
```

```
HTTP/1.1 204 No Content
```

A `204` with an empty body is the success answer: the gateway approved the capture and the order's
`captured` total has been raised. Nothing is returned, so read the order back if you want the
numbers.

## How much is captured

Always the whole outstanding amount — the order's frozen total minus what is already captured.

The endpoint takes no body, so **partial capture is not reachable through the API**. Several
gateways support it and the internal payment managers accept an amount, but v3 does not expose it;
capture a part of an order from the admin order page instead.

## Prerequisites

Capture fails, rather than being queued, unless all of these hold:

1. **The order's payment method is an online payment module.** Invoice, cash on delivery, bank
   transfer and friends have nothing to capture — the shop never held an authorization.
2. **That module has a payment manager registered.** The modules that can be captured are:
   `quickpayNew`, `klarnaQuickpay`, `appleQuickpay`, `anydayQuickpay`, `vippsQuickpay`,
   `swish`, `mobilePay`, `ideal`, `viaBillNew`, `paypal`, `paytrail`.
3. **The order carries a gateway transaction id.** Orders created through the API with a synthetic
   payment line (`lines.payment`) have no transaction at the gateway and cannot be captured.
4. **There is something left to capture.** A fully captured order has an outstanding amount of 0
   and the gateway refuses the second call.

## Responses

| Status | Body | Meaning |
|---|---|---|
| `204` | empty | Captured. |
| `400` | empty | The order's payment method is not an online payment module. |
| `400` | the error text, as a JSON string | The gateway refused, or a precondition failed — e.g. `"No QuickPay transaction ID found"`, `"Capture failed: ..."`, `"Invalid capture amount"`. |
| `401` / `403` | — | The token is missing or lacks `order_write`. |
| `404` | — | No such order. |

The `400` text comes straight from the payment module and is meant for a human reading a log. It is
not a stable error code — branch on the status, not on the message.

> **An order with no payment line at all answers `500`, not `400`.** The endpoint reads the payment
> module off the order without a null check, so an order created through the API without
> `lines.payment` fatals instead of being refused cleanly. Check that the order has a payment
> method before calling.

## Confirming it worked

The order resource carries the running totals, so a plain GET tells you where the money stands:

```json
{
  "id": 1015,
  "total_with_tax": 2351.54,
  "transaction_id": "46513332",
  "captured": 2351.54,
  "refunded": 0
}
```

`captured` is `0` on an authorized-but-not-charged order and rises to the frozen total once the
capture goes through. Capture writes no order history entry, so `captured` — not
`GET /api/v3/orders/{id}/history.json` — is the field to poll.

## Do not retry a 204

Capture moves money. A second call computes an outstanding amount of 0 and is refused by the
gateway, which reads as a `400` you might mistake for "the first one did not work". On a timeout or
any unclear answer, **GET the order and look at `captured`** before calling again.

There is also nothing to undo it with: deleting or cancelling the order does not return the money.
Use the refund endpoint (`POST /api/v3/orders/{id}/refunds.json`, see [order.md](order.md)).

## The shop may capture on its own

Every online payment module has an **"Order status for auto capture"** setting in its admin
settings, defaulting to *Do not auto capture*. When it is set, the shop captures the order
by itself the moment the order reaches that status — including when the status is changed through
the API:

```bash
curl -XPUT 'http://SHOP_DOMAIN/api/v3/orders/1015.json' \
  -H 'Authorization: Bearer ACCESS_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{"status": 5}'
```

If `5` is the module's auto-capture status, that single PUT charges the customer. The auto capture
is skipped on an order that already has `captured > 0`, and a failing one mails the shop owner
rather than failing the PUT — so a `200` on the status change says nothing about the capture.

Practical consequence: if your integration both moves orders through statuses and captures them,
read `captured` first, or you will race the shop for the same authorization.

## Runnable example

[order_capture.php](order_capture.php) — reads an order, checks the preconditions above, captures it
and prints the resulting `captured` total. It charges a real customer on a real gateway, so it
refuses to do anything until you set `$confirmed = true;` in it.
