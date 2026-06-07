(function () {
	'use strict';

	// ── State ──────────────────────────────────────────────────────────────────
	var currentPhone      = null;   // phone number of the open conversation
	var newConvPhone      = null;   // phone number being composed to (new conv)
	var outboxTimer       = null;

	// ── Helpers ────────────────────────────────────────────────────────────────
	function ncUrl(path) {
		return OC.generateUrl('/apps/ocsms' + path);
	}

	function phoneFromUrl() {
		var m = window.location.search.match(/phonenumber=([^&]+)/);
		return m ? decodeURIComponent(m[1]) : null;
	}

	function escapeHtml(s) {
		return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	function labelForPhone(phone) {
		var el = document.querySelector('a[mailbox-navigation="' + phone + '"]');
		return el ? (el.textContent.trim() || phone) : phone;
	}

	// ── Compose bar ────────────────────────────────────────────────────────────
	var composeBar     = null;
	var toNumber       = null;
	var msgArea        = null;
	var sendBtn        = null;
	var statusEl       = null;
	var newConvBtn     = null;

	function showComposeBar(phone) {
		if (!composeBar) return;
		currentPhone = phone;
		newConvPhone = null;
		toNumber.textContent = labelForPhone(phone) !== phone
			? labelForPhone(phone) + ' (' + phone + ')'
			: phone;
		composeBar.style.display = 'flex';
		msgArea.focus();
	}

	function showComposeBarForNew(phone) {
		if (!composeBar) return;
		currentPhone = null;
		newConvPhone = phone;
		toNumber.textContent = phone + ' ' + t('ocsms', '(new)');
		composeBar.style.display = 'flex';
		msgArea.focus();
	}

	function hideComposeBar() {
		if (!composeBar) return;
		composeBar.style.display = 'none';
		currentPhone = null;
		newConvPhone = null;
	}

	function setStatus(type, msg) {
		statusEl.textContent = msg;
		statusEl.dataset.status = type || '';
	}

	// ── Send ───────────────────────────────────────────────────────────────────
	function doSend() {
		var phone   = currentPhone || newConvPhone;
		var message = msgArea.value.trim();

		if (!phone)   { setStatus('error', t('ocsms', 'No recipient.')); return; }
		if (!message) { setStatus('error', t('ocsms', 'Message is empty.')); return; }

		sendBtn.disabled = true;
		setStatus('loading', t('ocsms', 'Sending…'));

		fetch(ncUrl('/front-api/v1/send'), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'requesttoken': OC.requestToken },
			body: JSON.stringify({ address: phone, message: message }),
		})
		.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
		.then(function (res) {
			if (res.ok && res.data.status) {
				setStatus('success', t('ocsms', 'Queued — your phone will send it shortly.'));
				msgArea.value = '';
				refreshOutbox(phone);
				// For new conversations: notify user the contact will appear after sync
				if (newConvPhone) {
					setStatus('success', t('ocsms', 'Queued. The contact will appear after your phone sends it.'));
				}
				setTimeout(function () { setStatus('', ''); }, 4000);
			} else {
				setStatus('error', res.data.msg || t('ocsms', 'Failed to queue message.'));
			}
		})
		.catch(function () { setStatus('error', t('ocsms', 'Network error.')); })
		.finally(function () { sendBtn.disabled = false; });
	}

	// ── Outbox ─────────────────────────────────────────────────────────────────
	function refreshOutbox(phone) {
		var target = phone || currentPhone || newConvPhone;
		if (!target) {
			renderOutbox([]);
			return;
		}
		fetch(ncUrl('/front-api/v1/queued') + '?phoneNumber=' + encodeURIComponent(target), {
			headers: { 'requesttoken': OC.requestToken }
		})
		.then(function (r) { return r.json(); })
		.then(function (d) { renderOutbox(d.messages || []); })
		.catch(function () {});
	}

	function renderOutbox(messages) {
		var wrapper = document.getElementById('app-content-wrapper');
		if (!wrapper) return;

		var section = document.getElementById('ocsms-outbox-section');
		if (!section) {
			section = document.createElement('div');
			section.id = 'ocsms-outbox-section';
			wrapper.appendChild(section);
		}

		if (!messages || messages.length === 0) {
			section.innerHTML = '';
			section.style.display = 'none';
			return;
		}

		section.style.display = 'block';

		var statusLabel = { 0: t('ocsms', 'Queued'), 2: t('ocsms', 'Failed') };
		var statusClass = { 0: 'queued', 2: 'failed' };

		var html = '<div class="ocsms-outbox-header">' + t('ocsms', 'Outbox') + '</div>';
		messages.forEach(function (msg) {
			var cls   = statusClass[msg.status]  || 'queued';
			var label = statusLabel[msg.status] || '';
			var retry = msg.status === 2
				? '<button class="ocsms-outbox-retry" data-id="' + msg.id + '">' + t('ocsms', 'Retry') + '</button>'
				: '';
			html +=
				'<div class="ocsms-outbox-item" data-id="' + msg.id + '">'
				+ '<div class="ocsms-outbox-msg">' + escapeHtml(msg.msg) + '</div>'
				+ '<div class="ocsms-outbox-meta">'
				+ '<span class="ocsms-outbox-status ' + cls + '">' + label + '</span>'
				+ retry
				+ '</div></div>';
		});
		section.innerHTML = html;

		section.querySelectorAll('.ocsms-outbox-retry').forEach(function (btn) {
			btn.addEventListener('click', function () {
				fetch(ncUrl('/front-api/v1/queued/' + btn.dataset.id + '/retry'), {
					method: 'POST',
					headers: { 'requesttoken': OC.requestToken }
				}).then(function () { refreshOutbox(); });
			});
		});
	}

	function scheduleOutboxRefresh() {
		if (outboxTimer) clearInterval(outboxTimer);
		refreshOutbox();
		outboxTimer = setInterval(refreshOutbox, 15000);
	}

	// ── New conversation modal ─────────────────────────────────────────────────
	var modal       = null;
	var modalInput  = null;

	function showNewConvModal() {
		if (!modal) return;
		modalInput.value = '';
		modal.style.display = 'flex';
		modalInput.focus();
	}

	function hideNewConvModal() {
		if (!modal) return;
		modal.style.display = 'none';
	}

	function startConversation(rawInput) {
		var input = rawInput.trim();
		if (!input) return;

		hideNewConvModal();

		// Try to find an existing contact by phone number or label
		var byNav = document.querySelector('a[mailbox-navigation="' + input + '"]');

		if (!byNav) {
			// Try case-insensitive label match
			var links = document.querySelectorAll('a[mailbox-label]');
			for (var i = 0; i < links.length; i++) {
				var label = (links[i].getAttribute('mailbox-label') || '').toLowerCase();
				if (label === input.toLowerCase()) {
					byNav = links[i];
					break;
				}
			}
		}

		if (byNav) {
			// Existing contact: simulate click to load conversation
			var li = byNav.closest('li');
			if (li) li.click();
		} else {
			// New number: show compose bar pre-filled, show empty conversation hint
			showEmptyConversationHint(input);
			showComposeBarForNew(input);
			renderOutbox([]);
		}
	}

	function showEmptyConversationHint(phone) {
		// Hide Vue's "select a conversation" message and show our own
		var emptyEl = document.getElementById('ocsms-empty-conversation');
		if (emptyEl) emptyEl.style.display = 'none';

		var hint = document.getElementById('ocsms-new-conv-hint');
		if (!hint) {
			hint = document.createElement('div');
			hint.id = 'ocsms-new-conv-hint';
			hint.className = 'ocsms-new-conv-hint';
			var wrapper = document.getElementById('app-content-wrapper');
			if (wrapper) wrapper.insertBefore(hint, wrapper.firstChild);
		}
		hint.textContent = t('ocsms', 'New conversation with') + ' ' + phone;
		hint.style.display = 'block';
	}

	function clearNewConvHint() {
		var hint = document.getElementById('ocsms-new-conv-hint');
		if (hint) hint.style.display = 'none';
		var emptyEl = document.getElementById('ocsms-empty-conversation');
		if (emptyEl) emptyEl.style.display = '';
	}

	// ── Conversation change detection ──────────────────────────────────────────
	function onConversationChange() {
		var phone = phoneFromUrl();
		clearNewConvHint();
		if (phone) {
			// Small delay so Vue finishes rendering the contact header
			setTimeout(function () {
				showComposeBar(phone);
				scheduleOutboxRefresh();
			}, 200);
		} else {
			hideComposeBar();
			renderOutbox([]);
		}
	}

	// Intercept Vue's pushState to detect conversation switches
	(function () {
		var orig = history.pushState.bind(history);
		history.pushState = function () {
			orig.apply(history, arguments);
			setTimeout(onConversationChange, 100);
		};
		window.addEventListener('popstate', function () {
			setTimeout(onConversationChange, 100);
		});
	})();

	// ── Init ───────────────────────────────────────────────────────────────────
	document.addEventListener('DOMContentLoaded', function () {
		composeBar  = document.getElementById('ocsms-compose-bar');
		toNumber    = document.getElementById('ocsms-compose-to-number');
		msgArea     = document.getElementById('ocsms-compose-msg');
		sendBtn     = document.getElementById('ocsms-compose-send');
		statusEl    = document.getElementById('ocsms-compose-status');
		newConvBtn  = document.getElementById('ocsms-newconv-btn');
		modal       = document.getElementById('ocsms-newconv-modal');
		modalInput  = document.getElementById('ocsms-newconv-input');

		if (!composeBar) return;

		// Send button
		sendBtn.addEventListener('click', doSend);

		// Ctrl+Enter in textarea
		msgArea.addEventListener('keydown', function (e) {
			if (e.ctrlKey && e.key === 'Enter') doSend();
		});

		// New conversation button (+)
		newConvBtn.addEventListener('click', showNewConvModal);

		// Modal controls
		document.getElementById('ocsms-newconv-cancel').addEventListener('click', hideNewConvModal);
		document.getElementById('ocsms-newconv-backdrop').addEventListener('click', hideNewConvModal);

		document.getElementById('ocsms-newconv-start').addEventListener('click', function () {
			startConversation(modalInput.value);
		});

		modalInput.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') startConversation(modalInput.value);
			if (e.key === 'Escape') hideNewConvModal();
		});

		// Restore state if a conversation was already open on page load
		setTimeout(onConversationChange, 600);
	});
})();
