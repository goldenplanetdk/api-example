<?php
/**
 * Reading the shop's event log through the OBB REST API.
 *
 * GET /api/v3/events.json lists the events the shop recorded (order.created, product.updated, ...),
 * oldest first, with the same payload a webhook for the event would send. Use it to poll for
 * changes instead of receiving webhooks, or to catch up on webhooks that were missed.
 *
 * Demonstrates:
 *   1. the five most recent events
 *   2. a filter: today's order events
 *   3. polling with an id cursor kept in a file - run the script again and only what happened
 *      since the last run is listed
 *
 * See events.md for the reference documentation.
 *
 * Requires a shop release with the events endpoint, and a client with the generic "read" scope
 * (product_read, order_read etc. are not enough: events cover every kind of entity).
 *
 * This endpoint only exists on v3, while get_token_guzzle.php points $apiClient at /api/v2/, so
 * every call below uses an absolute /api/v3/... path - Guzzle replaces the path and keeps the host.
 *
 * Read-only: safe to run as often as you like.
 *
 * Not for production use.
 */

require "get_token_guzzle.php";

echo "\n";

// Where the polling cursor (the id of the last handled event) is kept between runs.
$cursorFile = sys_get_temp_dir() . '/obb-api-example-events.cursor';

/**
 * GET the event list and return [decoded body, status code]. The query goes through Guzzle's
 * "query" option, which URL-encodes the filter (it contains =, ; and spaces).
 */
function listEvents($apiClient, array $query) {
	$response = $apiClient->get('/api/v3/events.json', ['query' => $query, 'http_errors' => false]);
	return [json_decode((string)$response->getBody(), true), $response->getStatusCode()];
}

/**
 * Stop with the error message unless the list was returned.
 */
function expectList($body, $status) {
	if ($status !== 200) {
		$message = isset($body['message']) ? $body['message'] : json_encode($body);
		exit("HTTP $status  $message\n"); // 403 "Missing scope: read", 404 on releases without the endpoint
	}
	return $body['_embedded']['resources'];
}

/**
 * One line per event: id, time, name, who or what made the change (the API app first: it tells
 * the services that write through the API apart), and the changed fields.
 */
function showEvent(array $event) {
	// Fields without a value are left out of the response, hence the "?? null".
	$app = $event['api_client'] ?? null;
	if ($app) {
		$by = 'app ' . ($app['name'] ?? '#' . $app['id']) . (isset($event['user_agent']) ? " ({$event['user_agent']})" : '');
	} elseif ($event['admin_id'] ?? null) {
		$by = 'admin #' . $event['admin_id'];
	} elseif ($event['customer_id'] ?? null) {
		$by = 'customer #' . $event['customer_id'];
	} elseif ($event['is_cli_mode'] ?? false) {
		$by = 'cron ' . ($event['source'] ?? '');
	} else {
		$by = 'web ' . ($event['path'] ?? '');
	}
	$changed = !empty($event['changed_fields']) ? implode(',', array_keys($event['changed_fields'])) : '-';

	echo sprintf("  #%-7d %s  %-22s %-30s changed: %s\n",
		$event['id'], $event['created_at'], $event['name'], $by, $changed);
}


/* ---------------------------------------------------------------------------
 * 1. The five most recent events
 *
 *    The list is oldest first (sort=id, order=asc). Reverse it to see the latest.
 * ------------------------------------------------------------------------- */

list($body, $status) = listEvents($apiClient, ['sort' => 'id', 'order' => 'desc', 'limit' => 5]);
$latest = expectList($body, $status);

echo "The 5 latest of {$body['total']} event(s) the shop still keeps:\n";
foreach ($latest as $event) {
	showEvent($event);
}


/* ---------------------------------------------------------------------------
 * 2. Today's order events
 *
 *    filter uses the v3 syntax (filters.md): ";" is AND, "," is OR. The fields are id, name,
 *    type and created_at. created_at is compared in the shop's time zone.
 * ------------------------------------------------------------------------- */

list($body, $status) = listEvents($apiClient, [
	'filter' => 'name=like=order.*;created_at=gte=' . date('Y-m-d') . ' 00:00:00',
	'sort'   => 'id',
	'order'  => 'desc',
	'limit'  => 10,
]);
$orders = expectList($body, $status);

echo "\n{$body['total']} order event(s) since midnight" . ($orders ? ', the latest ' . count($orders) . ':' : '') . "\n";
foreach ($orders as $event) {
	showEvent($event);
}


/* ---------------------------------------------------------------------------
 * 3. Poll: everything after the last handled event
 *
 *    - Ask for id=gt=<cursor> in id order. A full page (= limit) means there is more: ask again
 *      with the new cursor. Never use page=2, 3, ... - old events are deleted every night and
 *      page numbers shift.
 *    - Save the cursor AFTER handling the events: a crash repeats events instead of losing them,
 *      so handling an event twice must be harmless.
 *    - First run: start "from now" (the newest id). Start at 0 instead to replay all the events
 *      the shop still keeps (14 days by default).
 *    - Events older than 14 days are deleted. If the last run was longer ago than that, read
 *      your data in full again: the id gaps do not tell you what you missed.
 * ------------------------------------------------------------------------- */

$limit = 100;

if (is_file($cursorFile)) {
	$cursor = (int)file_get_contents($cursorFile);
	echo "\nPolling events after #$cursor (cursor in $cursorFile):\n";
} else {
	$cursor = $latest ? $latest[0]['id'] : 0;
	file_put_contents($cursorFile, $cursor);
	echo "\nFirst run: the cursor starts at the newest event, #$cursor. Change something in the shop and run again.\n";
	exit;
}

$handled = 0;
do {
	list($body, $status) = listEvents($apiClient, [
		'filter' => 'id=gt=' . $cursor,
		'sort'   => 'id',
		'order'  => 'asc',
		'limit'  => $limit,
	]);
	$events = expectList($body, $status);

	foreach ($events as $event) {
		showEvent($event);

		// "data" is the entity as it was when the event was recorded - the webhook body, in the
		// API v2 shape. Use its id to read the current state, e.g. GET /api/v3/orders/{id}.json.
		if (isset($event['data']['id'])) {
			echo "           {$event['name']} -> id {$event['data']['id']}\n";
		} elseif (strpos($event['name'], 'product.') === 0) {
			// The nightly cleanup sets data to null on every product.* event, so it no longer
			// says which product. Poll more often, or use the product.* webhooks.
			echo "           payload already cleaned up: it no longer says which product\n";
		}

		$cursor = $event['id'];
		$handled++;
	}

	file_put_contents($cursorFile, $cursor);
} while (count($events) === $limit);

echo $handled ? "$handled new event(s). " : "No new events. ";
echo "Cursor saved: #$cursor\n";
