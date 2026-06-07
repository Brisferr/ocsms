(function () {
	'use strict';

	// ── State ──────────────────────────────────────────────────────────────────
	var currentPhone     = null;
	var newConvPhone     = null;
	var outboxTimer      = null;
	var convRefreshTimer = null;
	var lastConvDate     = 0;

	// DOM refs
	var composeBar, msgArea, sendBtn, statusEl, newConvBtn;
	var modal, modalInput;

	// ── Helpers ────────────────────────────────────────────────────────────────
	function ncUrl(path) { return OC.generateUrl('/apps/ocsms' + path); }

	function phoneFromUrl() {
		var m = window.location.search.match(/phonenumber=([^&]+)/);
		return m ? decodeURIComponent(m[1]) : null;
	}

	function escapeHtml(s) {
		return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}

	// ── Build compose bar (FAB is the last button in the input row) ────────────
	function buildComposeBar() {
		var bar = document.createElement('div');
		bar.id = 'ocsms-compose-bar';
		bar.innerHTML =
			'<div id="ocsms-compose-input-row">'
			+  '<textarea id="ocsms-compose-msg" placeholder="' + t('ocsms','Ctrl+Enter to send') + '"></textarea>'
			+  '<button id="ocsms-compose-send" class="primary">' + t('ocsms','Send') + '</button>'
			+  '<button id="ocsms-newconv-btn" title="' + t('ocsms','New conversation') + '">+</button>'
			+ '</div>'
			+ '<span id="ocsms-compose-status"></span>';
		document.body.appendChild(bar);

		composeBar = bar;
		msgArea    = document.getElementById('ocsms-compose-msg');
		sendBtn    = document.getElementById('ocsms-compose-send');
		statusEl   = document.getElementById('ocsms-compose-status');
		newConvBtn = document.getElementById('ocsms-newconv-btn');

		positionBar();
		window.addEventListener('resize', positionBar);

		sendBtn.addEventListener('click', doSend);
		msgArea.addEventListener('keydown', function (e) {
			if (e.ctrlKey && e.key === 'Enter') doSend();
		});
		newConvBtn.addEventListener('click', showModal);
	}

	// ── Build new-conversation modal ───────────────────────────────────────────
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

	// ── Positioning (aligns bar left edge with right edge of #ocsms-left) ──────
	function positionBar() {
		if (!composeBar) return;
		var nav = document.getElementById('ocsms-left');
		if (nav) {
			composeBar.style.left = (nav.getBoundingClientRect().right) + 'px';
		} else {
			composeBar.style.left = '0';
		}
	}

	// ── Compose bar show / hide ────────────────────────────────────────────────
	function showBar(phone, isNew) {
		if (!composeBar) return;
		if (isNew) { currentPhone = null;  newConvPhone = phone; }
		else       { currentPhone = phone; newConvPhone = null;  }
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
		statusEl.textContent    = msg;
		statusEl.dataset.status = type || '';
	}

	// ── Send ───────────────────────────────────────────────────────────────────
	function doSend() {
		var phone   = currentPhone || newConvPhone;
		var message = msgArea ? msgArea.value.trim() : '';
		if (!phone)   { setStatus('error', t('ocsms','No recipient.')); return; }
		if (!message) { setStatus('error', t('ocsms','Message is empty.')); return; }

		sendBtn.disabled = true;
		setStatus('loading', t('ocsms','Sending…'));

		fetch(ncUrl('/front-api/v1/send'), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'requesttoken': OC.requestToken },
			body: JSON.stringify({ address: phone, message: message }),
		})
		.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
		.then(function (res) {
			if (res.ok && res.data.status) {
				setStatus('success', t('ocsms','Queued — your phone will send it shortly.'));
				msgArea.value = '';
				refreshOutbox(phone);
				setTimeout(function () { setStatus('',''); }, 4000);
			} else {
				setStatus('error', res.data.msg || t('ocsms','Failed to queue message.'));
			}
		})
		.catch(function () { setStatus('error', t('ocsms','Network error.')); })
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
		var wrapper = document.getElementById('ocsms-messages-wrap');
		if (!wrapper) return;
		var section = document.getElementById('ocsms-outbox-section');
		if (!section) {
			section = document.createElement('div');
			section.id = 'ocsms-outbox-section';
			wrapper.appendChild(section);
		}
		if (!messages || messages.length === 0) {
			section.innerHTML = ''; section.style.display = 'none'; return;
		}
		section.style.display = 'block';
		var lbl = { 0: t('ocsms','Queued'), 1: t('ocsms','Sent ✓'), 2: t('ocsms','Failed') };
		var cls = { 0: 'queued', 1: 'sent', 2: 'failed' };
		var html = '<div class="ocsms-outbox-header">' + t('ocsms','Outbox') + '</div>';
		messages.forEach(function (msg) {
			var retry = msg.status === 2
				? '<button class="ocsms-outbox-retry" data-id="' + msg.id + '">' + t('ocsms','Retry') + '</button>'
				: '';
			html += '<div class="ocsms-outbox-item ocsms-outbox-item--' + (cls[msg.status]||'queued') + '">'
				+ '<div class="ocsms-outbox-msg">' + escapeHtml(msg.msg) + '</div>'
				+ '<div class="ocsms-outbox-meta">'
				+ '<span class="ocsms-outbox-status ' + (cls[msg.status]||'queued') + '">' + (lbl[msg.status]||'') + '</span>'
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

	// ── Conversation auto-refresh (catches replies from the other person) ───────
	function startConvRefresh(phone) {
		stopConvRefresh();
		lastConvDate = 0;
		convRefreshTimer = setInterval(function () {
			if (!phone) return;
			fetch(ncUrl('/front-api/v1/new_messages') + '?lastDate=' + lastConvDate, {
				headers: { 'requesttoken': OC.requestToken }
			})
			.then(function (r) { return r.json(); })
			.then(function (d) {
				var phonelist = d.phonelist || {};
				if (phonelist[phone] && phonelist[phone] > lastConvDate) {
					lastConvDate = phonelist[phone];
					// New message for current conversation — reload it via Vue
					var contact = { nav: phone, label: phone, unread: 1, lastmsg: lastConvDate, uid: phone };
					if (window.ContactList) {
						window.ContactList.loadConversation(contact);
					} else if (window.Conversation) {
						window.Conversation.fetch(contact);
					}
					refreshOutbox(phone);
				}
			})
			.catch(function () {});
		}, 30000);
	}

	function stopConvRefresh() {
		if (convRefreshTimer) { clearInterval(convRefreshTimer); convRefreshTimer = null; }
	}

	// ── Custom header for new conversations ────────────────────────────────────
	// Vue's #ocsms-header only shows when messages.length > 0. For a brand-new
	// contact with no history we inject our own header that looks the same.
	function showNewConvHeader(phone) {
		var right = document.getElementById('ocsms-right');
		if (!right) return;
		var h = document.getElementById('ocsms-new-conv-header');
		if (!h) {
			h = document.createElement('div');
			h.id = 'ocsms-new-conv-header';
			// Insert at the top of #ocsms-right, before the loader/messages
			right.insertBefore(h, right.firstChild);
		}
		h.innerHTML =
			'<div id="ocsms-new-conv-phone">' + escapeHtml(phone) + '</div>'
			+ '<div id="ocsms-new-conv-hint">' + t('ocsms','New conversation — write your first message below') + '</div>';
		h.style.display = 'flex';
	}

	function hideNewConvHeader() {
		var h = document.getElementById('ocsms-new-conv-header');
		if (h) h.style.display = 'none';
	}

	// ── New conversation modal ─────────────────────────────────────────────────
	function showModal() { if (!modal) return; modalInput.value = ''; modal.classList.add('open'); modalInput.focus(); }
	function hideModal() { if (modal) modal.classList.remove('open'); }

	function startConversation(rawInput) {
		var input = rawInput.trim();
		if (!input) return;
		hideModal();

		// 1. Try exact phone number match in contact list
		var byNav   = document.querySelector('a[mailbox-navigation="' + input + '"]');
		var phone   = input;

		// 2. Try case-insensitive label match (contact name)
		if (!byNav) {
			var links = document.querySelectorAll('a[mailbox-label]');
			for (var i = 0; i < links.length; i++) {
				if ((links[i].getAttribute('mailbox-label') || '').toLowerCase().trim() === input.toLowerCase()) {
					byNav = links[i];
					phone = links[i].getAttribute('mailbox-navigation') || input;
					break;
				}
			}
		}

		if (byNav) {
			// ── Existing contact ───────────────────────────────────────────────
			hideNewConvHeader();
			var li = byNav.closest('li');
			if (li) li.click(); // triggers Vue's loadConversation
		} else {
			// ── New number ─────────────────────────────────────────────────────
			var contact = { label: phone, nav: phone, unread: 0, lastmsg: 0, uid: phone };

			// Add to Vue's reactive contact list (appears immediately in left column)
			if (window.ContactList) {
				window.ContactList.addContact(contact);
			}

			// Small delay for Vue to render the new <li> before we interact with it
			setTimeout(function () {
				// Use Vue's own loadConversation: updates URL + calls Conversation.fetch
				if (window.ContactList) {
					window.ContactList.loadConversation(contact);
				} else if (window.Conversation) {
					window.Conversation.fetch(contact);
					OC.Util.History.pushState('phonenumber=' + encodeURIComponent(phone));
				}

				// Inject our custom header (Vue's header requires messages.length > 0)
				setTimeout(function () {
					showNewConvHeader(phone);
					showBar(phone, true);
					scheduleOutboxRefresh();
					startConvRefresh(phone);
				}, 250);
			}, 60);
		}
	}

	// ── Conversation change ────────────────────────────────────────────────────
	function onConversationChange() {
		var phone = phoneFromUrl();
		hideNewConvHeader();
		stopConvRefresh();
		if (phone) {
			showBar(phone, false);
			scheduleOutboxRefresh();
			startConvRefresh(phone);
		} else {
			hideBar();
			renderOutbox([]);
		}
	}

	// Hook Vue's pushState AFTER DOMContentLoaded (Vue already mounted)
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
		buildComposeBar();
		buildModal();
		hookHistory();
		setTimeout(onConversationChange, 800);
	});
})();
