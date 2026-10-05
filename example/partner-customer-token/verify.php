<?php
/**
 * Verifies a partner customer token issued by a GP shop.
 *
 * The token proves which customer is in front of the storefront. It is signed
 * with the secret of your own API client, so no new key has to be exchanged.
 *
 * See README.md in this directory for the whole flow.
 */

/**
 * @param string $ticket   The token, as received from the storefront.
 * @param string $secret   Your API client secret.
 * @param string $shop     The shop host you expect, e.g. "shop.example.com".
 * @param string $clientId Your public client id.
 * @param int    $skew     Tolerated clock drift, in seconds.
 * @return array|null      The claims, or null if the token is not acceptable.
 */
function verifyPartnerToken($ticket, $secret, $shop, $clientId, $skew = 60) {
	if (!is_string($ticket)) {
		return null;
	}

	$parts = explode('.', $ticket);
	if (count($parts) !== 2) {
		return null;
	}
	list($payload, $signature) = $parts;

	// Sign the payload exactly as it arrived. Decoding the JSON and re-encoding
	// it reorders the keys, and a valid token would then fail.
	$expected = rtrim(strtr(base64_encode(
		hash_hmac('sha256', $payload, $secret, true)
	), '+/', '-_'), '=');

	// hash_equals, never ===: a plain comparison returns early on the first
	// differing byte, which leaks the signature one byte at a time.
	if (!hash_equals($expected, $signature)) {
		return null;
	}

	$claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
	if (!is_array($claims)) {
		return null;
	}

	// A signature only proves that somebody holding a secret signed these
	// claims. The three checks below prove they were signed for you, for this
	// shop, and recently.
	if (!isset($claims['exp']) || $claims['exp'] + $skew < time()) {
		return null;
	}
	if (!isset($claims['shop']) || $claims['shop'] !== $shop) {
		return null;
	}
	if (!isset($claims['client_id']) || $claims['client_id'] !== $clientId) {
		return null;
	}
	if (!isset($claims['customer_id']) || !is_int($claims['customer_id'])) {
		return null;
	}

	return $claims;
}

// ---------------------------------------------------------------------------

$ticket = '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
foreach ($headers as $name => $value) {
	if (strtolower($name) === 'authorization' && preg_match('/^Bearer\s+(.+)$/i', trim($value), $m)) {
		$ticket = $m[1];
	}
}

$claims = verifyPartnerToken(
	$ticket,
	getenv('GP_CLIENT_SECRET'),
	getenv('GP_SHOP'),
	getenv('GP_CLIENT_ID')
);

header('Content-Type: application/json');

if ($claims === null) {
	http_response_code(401);
	echo json_encode(['error' => 'invalid_ticket']);
	return;
}

// Optional: restrict the feature to newsletter subscribers. Only 'yes' counts —
// a blocked address will never receive a newsletter again.
if (empty($claims['newsletter'])) {
	http_response_code(403);
	echo json_encode([
		'error' => 'newsletter_required',
		'status' => isset($claims['newsletter_status']) ? $claims['newsletter_status'] : null,
	]);
	return;
}

// Customer ids are unique within one shop, not across shops — key anything you
// store on the pair, never on the id alone.
$quotaKey = $claims['shop'] . ':' . $claims['customer_id'];

echo json_encode([
	'customer_id' => $claims['customer_id'],
	'email' => $claims['email'],
	'quota_key' => $quotaKey,
]);
