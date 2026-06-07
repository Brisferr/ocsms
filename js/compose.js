(function () {
	'use strict';

	// ── Helpers ────────────────────────────────────────────────────────────────

	function generateUrl(path) {
		return OC.generateUrl('/apps/ocsms' + path);
	}

	function currentPhoneNumber() {
		var match = window.location.search.match(/phonenumber=([^&]+)/);
		return match ? decodeURIComponent(match[1]) : null;
	}

	// ── Outbox section ─────────────────────────────────────────────────────────

	var outboxRefreshTimer = null;

	function renderOutbox(messages) {
		var container = document.getElementById('ocsms-outbox-section');
		if (!container) {
			container = document.createElement('div');
			container.id = 'ocsms-outbox-section';
			var appContent = document.getElementById('app-content');
			if (appContent) appContent.appendChild(container);
		}

		if (!messages || messages.length === 0) {
			container.innerHTML = '';
			container.style.display = 'none';
			return;
		}

		container.style.display = 'block';

		var statusLabel = { 0: t('ocsms', 'Queued'), 2: t('ocsms', 'Failed') };
		var statusClass = { 0: 'queued', 2: 'failed' };

		var html = '<div class="ocsms-outbox-header">' + t('ocsms', 'Outbox') + '</div>';
		messages.forEach(function (msg) {
			var cls   = statusClass[msg.status]  || 'queued';
			var label = statusLabel[msg.status] || '';
			var retry = msg.status === 2
				? '<button class="ocsms-outbox-retry" data-id="' + msg.id + '">'
				  + t('ocsms', 'Retry') + '</button>'
				: '';
			html +=
				'<div class="ocsms-outbox-item" data-id="' + msg.id + '">'
				+ '<div class="ocsms-outbox-msg">' + escapeHtml(msg.msg) + '</div>'
				+ '<div class="ocsms-outbox-meta">'
				+   '<span class="ocsms-outbox-status ' + cls + '">' + label + '</span>'
				+   retry
				+ '</div>'
				+ '</div>';
		});
		container.innerHTML = html;

		container.querySelectorAll('.ocsms-outbox-retry').forEach(function (btn) {
			btn.addEventListener('click', function () {
				retryMessage(parseInt(btn.dataset.id, 10));
			});
		});
	}

	function refreshOutbox() {
		var phone = currentPhoneNumber();
		if (!phone) {
			renderOutbox([]);
			return;
		}
		fetch(generateUrl('/front-api/v1/queued') + '?phoneNumber=' + encodeURIComponent(phone), {
			headers: { 'requesttoken': OC.requestToken }
		})
		.then(function (r) { return r.json(); })
		.then(function (data) { renderOutbox(data.messages || []); })
		.catch(function () { /* silently ignore network errors */ });
	}

	function retryMessage(id) {
		fetch(generateUrl('/front-api/v1/queued/' + id + '/retry'), {
			method: 'POST',
			headers: { 'requesttoken': OC.requestToken }
		})
		.then(function (r) { return r.json(); })
		.then(function (data) {
			if (data.status) refreshOutbox();
		});
	}

	function scheduleOutboxRefresh() {
		if (outboxRefreshTimer) clearInterval(outboxRefreshTimer);
		refreshOutbox();
		// Poll every 15 s while a conversation is open
		outboxRefreshTimer = setInterval(refreshOutbox, 15000);
	}

	// Intercept Vue's pushState calls to detect conversation switches
	(function () {
		var orig = history.pushState.bind(history);
		history.pushState = function () {
			orig.apply(history, arguments);
			setTimeout(scheduleOutboxRefresh, 150);
		};
		window.addEventListener('popstate', function () {
			setTimeout(scheduleOutboxRefresh, 150);
		});
	})();

	// ── Compose panel ──────────────────────────────────────────────────────────

	document.addEventListener('DOMContentLoaded', function () {
		var toggleBtn = document.getElementById('ocsms-compose-toggle');
		var panel     = document.getElementById('ocsms-compose-panel');
		var toInput   = document.getElementById('ocsms-compose-to');
		var msgInput  = document.getElementById('ocsms-compose-msg');
		var sendBtn   = document.getElementById('ocsms-compose-send');
		var statusEl  = document.getElementById('ocsms-compose-status');

		if (!toggleBtn) return;

		toggleBtn.addEventListener('click', function () {
			var isOpen = panel.classList.contains('open');
			panel.classList.toggle('open', !isOpen);
			if (!isOpen) {
				// Pre-fill "To" from the currently open conversation
				var phone = currentPhoneNumber();
				if (phone && !toInput.value) toInput.value = phone;
				toInput.focus();
			}
		});

		msgInput.addEventListener('keydown', function (e) {
			if (e.ctrlKey && e.key === 'Enter') sendBtn.click();
		});

		sendBtn.addEventListener('click', function () {
			var address = toInput.value.trim();
			var message = msgInput.value.trim();

			if (!address || !message) {
				setStatus('error', t('ocsms', 'Please enter a phone number and a message.'));
				return;
			}

			sendBtn.disabled = true;
			setStatus('loading', t('ocsms', 'Sending…'));

			fetch(generateUrl('/front-api/v1/send'), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'requesttoken': OC.requestToken,
				},
				body: JSON.stringify({ address: address, message: message }),
			})
			.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
			.then(function (result) {
				if (result.ok && result.data.status) {
					setStatus('success', t('ocsms', 'Message queued — your phone will send it shortly.'));
					msgInput.value = '';
					// Refresh outbox immediately so the new pending message appears
					refreshOutbox();
					setTimeout(function () {
						panel.classList.remove('open');
						setStatus('', '');
					}, 2000);
				} else {
					setStatus('error', result.data.msg || t('ocsms', 'Failed to queue message.'));
				}
			})
			.catch(function () {
				setStatus('error', t('ocsms', 'Network error. Please try again.'));
			})
			.finally(function () {
				sendBtn.disabled = false;
			});
		});

		function setStatus(type, msg) {
			statusEl.textContent = msg;
			statusEl.dataset.status = type;
		}

		// Initial outbox load if a conversation is already open on page load
		setTimeout(scheduleOutboxRefresh, 500);
	});

	// ── Utility ────────────────────────────────────────────────────────────────

	function escapeHtml(str) {
		return str
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}
})();
