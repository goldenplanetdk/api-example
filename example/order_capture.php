<?php
/**
 * Capturing an order through the OBB REST API.
 *
 * An online payment module only reserves the money at checkout. The customer is charged when the
 * payment is captured, which is what POST /api/v3/orders/{id}/capture.json does. There is no
 * payload: the shop captures the whole outstanding amount on the gateway the order was paid with.
 *
 * Demonstrates:
 *   1. reading the order and the two fields that decide whether it can be captured
 *   2. the capture call itself, and the empty 204 it answers with
 *   3. reading the order back to see the money moved
 *   4. what a refusal looks like, and why you must not retry a capture blindly
 *
 * See order_capture.md for the reference documentation.
 *
 * This endpoint only exists on v3, while get_token_guzzle.php points $apiClient at /api/v2/, so
 * every call below uses an absolute /api/v3/... path - Guzzle replaces the path and keeps the host.
 *
 * WARNING: unlike the other examples in this repo, this one is NOT safe to re-run and cannot be
 * undone. It charges a real customer on a real payment gateway. Point it at a test shop, set the
 * order id, and flip $confirmed below.
 *
 * Not for production use.
 */

require "get_token_guzzle.php";

$orderId   = 1015; // replace with an authorized, not yet captured order from your shop
$confirmed = false; // set to true once you accept that this charges the customer

/**
 * Send a request that is allowed to fail, and return [decoded body, status code, raw body].
 *
 * A capture answers 204 with an EMPTY body, so json_decode() legitimately returns null here -
 * always read the status code, never the body, to decide whether it worked.
 */
function send($apiClient, $method, $path) {
	// Content-Length is not optional, even with nothing to send: a POST that declares no body
	// length is rejected with a bodiless 400 by the proxy in front of the shop, before the
	// endpoint runs. Guzzle sets it for a request with a body; set it explicitly when there
	// is none.
	$response = $apiClient->request($method, $path, [
		'headers'     => ['Content-Length' => '0'],
		'http_errors' => false,
	]);
	$raw = (string)$response->getBody();
	return [json_decode($raw, true), $response->getStatusCode(), $raw];
}


/* ---------------------------------------------------------------------------
 * 1. Read the order first
 *
 *    Two fields decide whether a capture can succeed:
 *
 *      transaction_id - the gateway's reference. Empty means the shop never held an
 *                       authorization (an offline payment, or an order created through the API
 *                       with a synthetic payment line), so there is nothing to capture.
 *      captured       - how much has already been charged. 0 on an authorized order, the frozen
 *                       total once captured.
 *
 *    Reading this BEFORE capturing is not politeness. The shop can capture on its own - every
 *    online payment module has an "Order status for auto capture" setting - so an order you
 *    walked through a status change may already be paid.
 * ------------------------------------------------------------------------- */

$order = json_decode($apiClient->get('/api/v3/orders/' . $orderId . '.json')->getBody(), true);

$total          = $order['total_with_tax'];
$captured       = isset($order['captured']) ? (float)$order['captured'] : 0.0;
$transactionId  = isset($order['transaction_id']) ? $order['transaction_id'] : '';

echo "order {$order['id']}: total {$total}, captured {$captured}, "
	. 'transaction ' . ($transactionId ?: '(none)') . "\n";

if (!$transactionId) {
	exit("nothing to capture: the order has no gateway transaction\n");
}

if ($captured >= $total) {
	exit("already captured in full - stopping rather than provoking a 400\n");
}

if (!$confirmed) {
	exit("would capture " . ($total - $captured) . " - set \$confirmed = true to do it for real\n");
}


/* ---------------------------------------------------------------------------
 * 2. Capture
 *
 *    No body, no query parameters. The amount is always the whole outstanding amount
 *    (frozen total minus what is already captured) - partial capture is not reachable
 *    through the API, only from the admin order page.
 *
 *    204 with an empty body = the gateway approved it.
 *    400 = refused. The body is the payment module's own error text as a JSON string, e.g.
 *          "No QuickPay transaction ID found" or "Capture failed: ...", and it is empty when
 *          the order's payment method is not an online payment module at all. The text is for
 *          a human reading a log - branch on the status code, not on the message.
 * ------------------------------------------------------------------------- */

list($body, $status, $raw) = send($apiClient, 'POST', '/api/v3/orders/' . $orderId . '/capture.json');

if ($status === 204) {
	echo "captured (HTTP 204, empty body as documented)\n";
} else {
	echo "capture refused: HTTP $status " . ($raw !== '' ? $raw : '(empty body)') . "\n";
}


/* ---------------------------------------------------------------------------
 * 3. Read the order back
 *
 *    The 204 carries no numbers, and a capture writes no order history entry, so the order's
 *    own "captured" field is the only confirmation the API offers.
 * ------------------------------------------------------------------------- */

$order = json_decode($apiClient->get('/api/v3/orders/' . $orderId . '.json')->getBody(), true);

echo 'after: captured ' . $order['captured'] . ' of ' . $order['total_with_tax']
	. ', refunded ' . $order['refunded'] . "\n";


/* ---------------------------------------------------------------------------
 * 4. Never retry a capture blindly
 *
 *    Capture moves money and nothing undoes it - deleting or cancelling the order does not
 *    return it, only POST /api/v3/orders/{id}/refunds.json does.
 *
 *    A second call computes an outstanding amount of 0 and is refused by the gateway, which
 *    reads as a 400 you could easily mistake for "the first one did not work". The call below
 *    is here to show that, and is deliberately the last thing this script does.
 *
 *    On a timeout or any unclear answer, GET the order and look at "captured" instead.
 * ------------------------------------------------------------------------- */

list($body, $status, $raw) = send($apiClient, 'POST', '/api/v3/orders/' . $orderId . '/capture.json');

echo "second capture on the same order: HTTP $status " . ($raw !== '' ? $raw : '(empty body)') . "\n";
