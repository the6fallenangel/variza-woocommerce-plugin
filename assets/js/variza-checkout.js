(() => {
	'use strict';

	const cfg = window.varizaCheckout;
	if (!cfg || !cfg.ajaxUrl) return;

	let tries = 0;

	function check() {
		tries += 1;
		if (tries > cfg.maxTries) return;

		const url = cfg.ajaxUrl + '&order_id=' + encodeURIComponent(cfg.orderId) + '&nonce=' + encodeURIComponent(cfg.nonce);

		fetch(url, { credentials: 'same-origin' })
			.then((r) => r.json())
			.then((data) => {
				if (data && data.success && data.data && data.data.needs_payment === false) {
					window.location.reload();
					return;
				}

				window.setTimeout(check, cfg.poll);
			})
			.catch(() => {
				window.setTimeout(check, cfg.poll);
			});
	}

	window.setTimeout(check, cfg.poll);
})();
