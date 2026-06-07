(function () {
	'use strict';

	// ── State ──────────────────────────────────────────────────────────────────
	var currentPhone  = null;   // phone number of the open conversation
	var newConvPhone  = null;   // phone number of a new (not yet synced) conversation
	var outboxTimer   = null;

	// DOM refs — set after elements are created
	var composeBar, toNumberEl, msgArea, sendBtn, statusEl, newConvBtn;
	var modal, modalInput;

	// ── Helpers ────────────────────────────────────────────────────────────────
	function ncUrl(path) { return OC.generateUrl('/apps/ocsms' + path); }

	function phoneFromUrl() {
		var m = window.location.search.match(/phonenumber=([^&]+)/);
		return m ? decodeURIComponent(m[1]) : null;
	}

	function labelForPhone(phone) {
		var a = document.querySelector('a[mailbox-navigation="' + phone + '"]');
		if (!a) return phone;
		var txt = a.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
		return (txt && txt !== phone) ? txt + ' (' + phone + ')' : phone;
	}

	function escapeHtml(s) {
		return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}

	// ── Build DOM elements once ────────────────────────────────────────────────
	function buildComposeBar() {
		var bar = document.createElement('div');
		bar.id = 'ocsms-compose-bar';
		bar.innerHTML =
			'<div id="ocsms-compose-input-row">'
			+  '<button id="ocsms-newconv-btn" title="' + t('ocsms','New conversation') + '">+</button>'
			+  '<textarea id="ocsms-compose-msg" placeholder="' + t('ocsms','Ctrl+Enter to send') + '"></textarea>'
			+  '<button id="ocsms-compose-send" class="primary">' + t('ocsms','Send') + '</button>'
			+ '</div>'
			+ '<span id="ocsms-compose-status"></span>';
		document.body.appendChild(bar);

		composeBar = bar;
		toNumberEl = null; // recipient line removed
		msgArea    = document.getElementById('ocsms-compose-msg');
		sendBtn    = document.getElementById('ocsms-compose-send');
		statusEl   = document.getElementById('ocsms-compose-status');
		newConvBtn = document.getElementById('ocsms-newconv-btn');

		// Align left edge with right edge of #app-navigation
		positionBar();
		window.addEventListener('resize', positionBar);

		// Events
		sendBtn.addEventListener('click', doSend);
		msgArea.addEventListener('keydown', function (e) {
			if (e.ctrlKey && e.key === 'Enter') doSend();
		});
		newConvBtn.addEventListener('click', showModal);
	}

	function buildModal() {
		var m = document.createElement('div');
		m.id = 'ocsms-newconv-modal';
		m.innerHTML =
			'<div id="ocsms-newconv-backdrop"></div>'
			+ '<div id="ocsms-newconv-dialog">'
			+   '<h3>' + t('ocsms','New conversation') + '</h3>'
			+   '<label>' + t('ocsms','To (number or contact)') + '</label>'
			+   '<input type="tel" id="ocsms-newconv-input" placeholder="+32…" />'
			+   '<div class="ocsms-newconv-actions">'
			+     '<button id="ocsms-newconv-cancel">' + t('ocsms','Cancel') + '</button>'
			+     '<button id="ocsms-newconv-start" class="primary">' + t('ocsms','Start') + '</button>'
			+   '</div>'
			+ '</div>';
		document.body.appendChild(m);

		modal      = m;
		modalInput = document.getElementById('ocsms-newconv-input');

		document.getElementById('ocsms-newconv-cancel').addEventListener('click', hideModal);
		document.getElementById('ocsms-newconv-backdrop').addEventListener('click', hideModal);
		document.getElementById('ocsms-newconv-start').addEventListener('click', function () {
			startConversation(modalInput.value);
		});
		modalInput.addEventListener('keydown', function (e) {
			if (e.key === 'Enter')  startConversation(modalInput.value);
			if (e.key === 'Escape') hideModal();
		});
	}

	// ── Positioning ────────────────────────────────────────────────────────────
	function positionBar() {
		if (!composeBar) return;
		var nav = document.getElementById('ocsms-left');
		if (nav) {
			var r = nav.getBoundingClientRect();
			composeBar.style.left = (r.left + r.width) + 'px';
		} else {
			composeBar.style.left = '0';
		}
	}

	// ── Compose bar show/hide ──────────────────────────────────────────────────
	function showBar(phone, isNew) {
		if (!composeBar) return;
		if (isNew) {
			currentPhone = null;
			newConvPhone = phone;
			toNumberEl.textContent = phone + ' ' + t('ocsms', '(new)');
		} else {
			currentPhone = phone;
			newConvPhone = null;
			toNumberEl.textContent = labelForPhone(phone);
		}
		positionBar();
		composeBar.style.display = 'flex';
	}

	function hideBar() {
		if (composeBar) composeBar.style.display = 'none';
		currentPhone = null;
		newConvPhone = null;
	}

	function setStatus(type, msg) {
		if (!statusEl) return;
		statusEl.textContent = msg;
		statusEl.dataset.status = type || '';
	}

	// ── Send ───────────────────────────────────────────────────────────────────
	function doSend() {
		var phone   = currentPhone || newConvPhone;
		var message = msgArea ? msgArea.value.trim() : '';
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
				var msg = newConvPhone
					? t('ocsms', 'Queued — contact will appear after sync.')
					: t('ocsms', 'Queued — your phone will send it shortly.');
				setStatus('success', msg);
				msgArea.value = '';
				refreshOutbox(phone);
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
		if (!target) { renderOutbox([]); return; }
		fetch(ncUrl('/front-api/v1/queued') + '?phoneNumber=' + encodeURIComponent(target), {
			headers: { 'requesttoken': OC.requestToken }
		})
		.then(function (r) { return r.json(); })
		.then(function (d) { renderOutbox(d.messages || []); })
		.catch(function () {});
	}

	function renderOutbox(messages) {
		// Inject outbox section at end of the conversation wrapper (Vue doesn't manage our injected el)
		var wrapper = document.getElementById('ocsms-messages-wrap');
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

		var lblStatus = { 0: t('ocsms','Queued'), 2: t('ocsms','Failed') };
		var clsStatus = { 0: 'queued', 2: 'failed' };
		var html = '<div class="ocsms-outbox-header">' + t('ocsms','Outbox') + '</div>';
		messages.forEach(function (msg) {
			var cls   = clsStatus[msg.status]  || 'queued';
			var label = lblStatus[msg.status] || '';
			var retry = msg.status === 2
				? '<button class="ocsms-outbox-retry" data-id="' + msg.id + '">' + t('ocsms','Retry') + '</button>'
				: '';
			html += '<div class="ocsms-outbox-item">'
				+ '<div class="ocsms-outbox-msg">' + escapeHtml(msg.msg) + '</div>'
				+ '<div class="ocsms-outbox-meta">'
				+ '<span class="ocsms-outbox-status ' + cls + '">' + label + '</span>'
				+ retry + '</div></div>';
		});
		section.innerHTML = html;
		section.querySelectorAll('.ocsms-outbox-retry').forEach(function (btn) {
			btn.addEventListener('click', function () {
				fetch(ncUrl('/front-api/v1/queued/' + btn.dataset.id + '/retry'), {
					method: 'POST', headers: { 'requesttoken': OC.requestToken }
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
	function showModal() {
		if (!modal) return;
		modalInput.value = '';
		modal.classList.add('open');
		modalInput.focus();
	}

	function hideModal() {
		if (modal) modal.classList.remove('open');
	}

	function startConversation(rawInput) {
		var input = rawInput.trim();
		if (!input) return;
		hideModal();

		// Try exact phone number match in contact list
		var byNav = document.querySelector('a[mailbox-navigation="' + input + '"]');

		if (!byNav) {
			// Try case-insensitive label match
			var links = document.querySelectorAll('a[mailbox-label]');
			for (var i = 0; i < links.length; i++) {
				if ((links[i].getAttribute('mailbox-label') || '').toLowerCase() === input.toLowerCase()) {
					byNav = links[i];
					break;
				}
			}
		}

		if (byNav) {
			// Existing contact — simulate click to load via Vue
			var li = byNav.closest('li');
			if (li) li.click();
		} else {
			// New number — show compose bar pre-filled, inject hint
			showNewConvHint(input);
			showBar(input, true);
			renderOutbox([]);
		}
	}

	function showNewConvHint(phone) {
		var wrapper = document.getElementById('ocsms-messages-wrap');
		if (!wrapper) return;
		var hint = document.getElementById('ocsms-new-conv-hint');
		if (!hint) {
			hint = document.createElement('div');
			hint.id = 'ocsms-new-conv-hint';
			hint.className = 'ocsms-new-conv-hint';
			wrapper.insertBefore(hint, wrapper.firstChild);
		}
		hint.textContent = t('ocsms', 'New conversation with') + ' ' + phone;
		hint.style.display = 'block';
	}

	function clearNewConvHint() {
		var hint = document.getElementById('ocsms-new-conv-hint');
		if (hint) hint.style.display = 'none';
	}

	// ── Conversation change ────────────────────────────────────────────────────
	function onConversationChange() {
		var phone = phoneFromUrl();
		clearNewConvHint();
		if (phone) {
			showBar(phone, false);
			scheduleOutboxRefresh();
		} else {
			hideBar();
			renderOutbox([]);
		}
	}

	// Intercept Vue's pushState AFTER Vue has mounted (inside DOMContentLoaded)
	// so we don't interfere with Vue's own initialisation
	function hookHistory() {
		var orig = history.pushState.bind(history);
		history.pushState = function () {
			orig.apply(history, arguments);
			setTimeout(onConversationChange, 200);
		};
		window.addEventListener('popstate', function () {
			setTimeout(onConversationChange, 200);
		});
	}

	// ── Init ───────────────────────────────────────────────────────────────────
	document.addEventListener('DOMContentLoaded', function () {
		// Build compose bar and modal entirely via JS — never touch Vue's template
		buildComposeBar();
		buildModal();

		// Hook history AFTER DOMContentLoaded so Vue has already run its own setup
		hookHistory();

		// Restore state if a conversation was open on page load
		// 800ms delay to ensure Vue has finished its initial render
		setTimeout(onConversationChange, 800);
	});
})();
