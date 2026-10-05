/* ---------------------------------------------------------------------------
 * AI kit preview — storefront loader
 *
 * Goes in the shop's user-scripts.js (Admin → Design → Code editor). That file
 * is loaded with `defer` on every storefront page.
 *
 * WARNING: user-scripts.js is a STATIC asset. It is cached for 300 seconds and
 * served identically to every visitor, logged in or not. Put code here, never
 * a ticket, a customer id, or the client secret.
 *
 * Replace AI_BACKEND with your own address. Nothing else has to change.
 * ------------------------------------------------------------------------- */
(function () {
	'use strict';

	var CONFIG = {
		// ---- replace this with your own backend ----
		AI_BACKEND: 'https://ai.example.com/generate',

		// Given to you by the shop administrator (Tools → API clients).
		// This is the public client id — it is NOT the secret, and it is fine
		// for it to be visible here. The secret never leaves your server.
		CLIENT_ID: 'REPLACE_WITH_CLIENT_ID',

		// Where the button is injected, and where the colours are read from.
		MOUNT_SELECTOR: '.product-info',
		COLOUR_SELECTOR: 'input[name^="attribute"]:checked, select[name^="attribute"]'
	};

	var TICKET_URL = '/user/partner-token?client_id=' + encodeURIComponent(CONFIG.CLIENT_ID);

	/* -- ticket handling ---------------------------------------------------- */

	var cached = null;

	/**
	 * Returns a signed ticket for the logged-in customer, or null when nobody
	 * is logged in. Tickets live 5 minutes; we re-use one until 30 seconds
	 * before it expires.
	 */
	async function getTicket() {
		var now = Math.floor(Date.now() / 1000);
		if (cached && cached.expires_at - now > 30) {
			return cached.token;
		}

		var res = await fetch(TICKET_URL, { credentials: 'same-origin' });

		if (res.status === 401) {
			cached = null;
			return null;                       // not logged in
		}
		if (!res.ok) {
			throw new Error('partner-token returned HTTP ' + res.status);
		}

		cached = await res.json();
		return cached.token;
	}

	/* -- calling your backend ----------------------------------------------- */

	async function generatePreview(colours) {
		var ticket = await getTicket();
		if (!ticket) {
			return { state: 'login_required' };
		}

		var res = await fetch(CONFIG.AI_BACKEND, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Authorization': 'Bearer ' + ticket
			},
			body: JSON.stringify({
				product: currentProductId(),
				colours: colours
			})
		});

		if (res.status === 403) {
			// Your backend refused because the customer has no active
			// newsletter subscription. It passes the raw state back so we can
			// say something useful.
			var refused = await res.json();
			return { state: 'newsletter_required', status: refused.status };
		}
		if (res.status === 429) {
			return { state: 'quota_reached' };
		}
		if (!res.ok) {
			throw new Error('AI backend returned HTTP ' + res.status);
		}

		return { state: 'ok', image: (await res.json()).image_url };
	}

	/* -- page glue ----------------------------------------------------------- */

	function currentProductId() {
		var el = document.querySelector('[data-product-id]');
		return el ? el.getAttribute('data-product-id') : null;
	}

	function chosenColours() {
		return Array.prototype.map.call(
			document.querySelectorAll(CONFIG.COLOUR_SELECTOR),
			function (el) { return el.value; }
		).filter(Boolean);
	}

	function message(box, text) {
		box.textContent = text;
	}

	async function onClick(button, box) {
		button.disabled = true;
		message(box, 'Generating your preview…');

		try {
			var result = await generatePreview(chosenColours());

			switch (result.state) {
				case 'ok':
					box.innerHTML = '';
					var img = document.createElement('img');
					img.src = result.image;
					img.alt = 'Your kit in the colours you picked';
					img.style.maxWidth = '100%';
					box.appendChild(img);
					break;

				case 'login_required':
					message(box, 'Log in to see the kit in your own colours.');
					break;

				case 'newsletter_required':
					message(box, result.status === 'pending'
						? 'Almost there — click the confirmation link in the e-mail we sent you.'
						: 'Subscribe to our newsletter to use this feature.');
					break;

				case 'quota_reached':
					message(box, 'You have used all your previews for today.');
					break;
			}
		} catch (e) {
			// Never let this break the rest of the page.
			message(box, 'The preview service is unavailable right now.');
			if (window.console) { console.error('[ai-preview]', e); }
		} finally {
			button.disabled = false;
		}
	}

	function init() {
		var mount = document.querySelector(CONFIG.MOUNT_SELECTOR);
		if (!mount || mount.querySelector('.ai-preview-button')) {
			return;                            // not a product page, or already mounted
		}

		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'btn btn-default ai-preview-button';
		button.textContent = 'See it in your colours';

		var box = document.createElement('div');
		box.className = 'ai-preview-output';

		button.addEventListener('click', function () { onClick(button, box); });
		mount.appendChild(button);
		mount.appendChild(box);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
