<?php
/**
 * Attribute group examples for the OBB REST API.
 *
 * Attribute groups are the colour families an attribute value belongs to - the shop draws the
 * coloured boxes in category filters from them. Demonstrates:
 *   1. listing groups
 *   2. creating a group
 *   3. partial update, and delete
 *   4. assigning a value to groups by id AND by title in one request
 *   5. an unknown title creating a group, an existing one being reused
 *   6. replacing and clearing a value's groups
 *
 * See attribute_groups.md for the reference documentation.
 *
 * These endpoints only exist on v3, while get_token_guzzle.php points $apiClient at /api/v2/,
 * so every call below uses an absolute /api/v3/... path - Guzzle replaces the path and keeps
 * the host.
 *
 * Not for production use.
 */

require "get_token_guzzle.php";

$attributeId = 2; // an attribute to hang test values off - replace with one from your shop

/**
 * Print a value's groups as "id:title" pairs.
 */
function showGroups($apiClient, $attributeId, $valueId, $label) {
	$body = $apiClient->get('/api/v3/attributes/' . $attributeId . '/values/' . $valueId . '.json')->getBody();
	$value = json_decode($body, true);
	$pairs = [];
	foreach ($value['groups'] as $group) {
		$pairs[] = $group['id'] . ':' . $group['title'];
	}
	echo str_pad($label, 46) . ($pairs ? implode(', ', $pairs) : '(none)') . "\n";
	return $value;
}

/**
 * How many groups currently carry this title - used below to show that an existing title is
 * reused rather than duplicated.
 */
function countGroupsTitled($apiClient, $title) {
	$groups = json_decode($apiClient->get('/api/v3/attributegroups.json')->getBody(), true);
	$count = 0;
	foreach ($groups as $group) {
		if ($group['title'] === $title) {
			$count++;
		}
	}
	return $count;
}


/* ---------------------------------------------------------------------------
 * 1. List the groups that already exist
 * ------------------------------------------------------------------------- */

$groups = json_decode($apiClient->get('/api/v3/attributegroups.json')->getBody(), true);
echo 'existing groups: ' . count($groups) . "\n";
foreach (array_slice($groups, 0, 3) as $group) {
	echo '  ' . $group['id'] . ' ' . $group['title'] . ' ' . (isset($group['code']) ? $group['code'] : '') . "\n";
}


/* ---------------------------------------------------------------------------
 * 2. Create a group
 *    Only title is required. `code` is a hex colour, max 7 characters, and unique
 *    across groups - a duplicate or over-long value comes back as a 400.
 * ------------------------------------------------------------------------- */

$suffix = substr(md5(implode('', array_map('strval', array_column($groups, 'id')))), 0, 6);
$familyTitle = 'Warm colours ' . $suffix;

$created = json_decode($apiClient->post('/api/v3/attributegroups.json', [
	'json' => [
		'title' => $familyTitle,
		'sorting' => 30,
	],
])->getBody(), true);
echo "created group {$created['id']} \"{$created['title']}\"\n";


/* ---------------------------------------------------------------------------
 * 3. Partial update, then delete
 *    Unlike the attribute value endpoint, group PUT leaves out what you omit.
 * ------------------------------------------------------------------------- */

$throwaway = json_decode($apiClient->post('/api/v3/attributegroups.json', [
	'json' => ['title' => 'Throwaway ' . $suffix, 'sorting' => 90],
])->getBody(), true);

$updated = json_decode($apiClient->put('/api/v3/attributegroups/' . $throwaway['id'] . '.json', [
	'json' => ['sorting' => 95], // title omitted on purpose - it survives
])->getBody(), true);
echo "partial update kept the title: \"{$updated['title']}\", sorting now {$updated['sorting']}\n";

$apiClient->delete('/api/v3/attributegroups/' . $throwaway['id'] . '.json');
echo "deleted group {$throwaway['id']}\n";


/* ---------------------------------------------------------------------------
 * 4. Assign a value to groups by id AND by title in one request
 *    The id resolves to an existing group; the title is matched, and created if
 *    no group carries it.
 * ------------------------------------------------------------------------- */

// Prefer a group that carries a colour code - those are the real colour families, rather than
// whatever happens to sort first.
$firstGroupId = $groups[0]['id'];
foreach ($groups as $group) {
	if (!empty($group['code'])) {
		$firstGroupId = $group['id'];
		break;
	}
}

$value = json_decode($apiClient->post('/api/v3/attributes/' . $attributeId . '/values.json', [
	'json' => [
		'title' => 'Crimson ' . $suffix,
		'sorting' => 10,
		'groups' => [$firstGroupId, $familyTitle],
	],
])->getBody(), true);
$valueId = $value['id'];
showGroups($apiClient, $attributeId, $valueId, 'assigned by id + by title');


/* ---------------------------------------------------------------------------
 * 5. An existing title is reused, not duplicated
 * ------------------------------------------------------------------------- */

$before = countGroupsTitled($apiClient, $familyTitle);
$second = json_decode($apiClient->post('/api/v3/attributes/' . $attributeId . '/values.json', [
	'json' => [
		'title' => 'Scarlet ' . $suffix,
		'sorting' => 11,
		'groups' => [$familyTitle], // same title again
	],
])->getBody(), true);
$after = countGroupsTitled($apiClient, $familyTitle);
echo "groups titled \"$familyTitle\": $before before, $after after - reused, not duplicated\n";
showGroups($apiClient, $attributeId, $second['id'], 'second value, same group by title');


/* ---------------------------------------------------------------------------
 * 6. Replace and clear a value's groups
 *
 *    The value PUT replaces EVERY field, so title and sorting have to be resent -
 *    omitting sorting nulls a NOT NULL column and returns a 500. That is the
 *    long-standing contract of the attribute value endpoint, not something the
 *    groups field introduced.
 * ------------------------------------------------------------------------- */

$apiClient->put('/api/v3/attributes/' . $attributeId . '/values/' . $valueId . '.json', [
	'json' => [
		'title' => 'Crimson ' . $suffix,
		'sorting' => 10,
		'groups' => [$familyTitle], // the id-assigned group is dropped
	],
]);
showGroups($apiClient, $attributeId, $valueId, 'after replacing the list');

$apiClient->put('/api/v3/attributes/' . $attributeId . '/values/' . $valueId . '.json', [
	'json' => [
		'title' => 'Crimson ' . $suffix,
		'sorting' => 10,
		'groups' => [],
	],
]);
showGroups($apiClient, $attributeId, $valueId, 'after clearing');
