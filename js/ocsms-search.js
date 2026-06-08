/* ocsms-search.js — contact search + message search for NC33 */
(function () {
    'use strict';

    function esc(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

    // Scroll mark into view inside #ocsms-messages-wrap
    function scrollToMark(mark) {
        var wrap = document.getElementById('ocsms-messages-wrap');
        if (!wrap || !mark) return;
        var offset = 0, el = mark;
        while (el && el !== wrap) { offset += el.offsetTop; el = el.offsetParent; }
        wrap.scrollTop = offset - wrap.clientHeight / 2 + mark.offsetHeight / 2;
    }

    // ── Contact search ────────────────────────────────────────────────────────
    var lastContactQuery = '';
    var applyingFilter   = false; // guard against MutationObserver re-entrancy

    function filterContacts(q) {
        lastContactQuery = q;
        var lq = q.trim().toLowerCase();
        applyingFilter = true;
        document.querySelectorAll('#app-mailbox-peers .contact-list li').forEach(function (li) {
            if (!lq) { li.style.display = ''; return; }

            // textContent always contains the rendered label regardless of Vue attribute resolution
            var text = (li.textContent || '').toLowerCase();

            // Also check mailbox-navigation attribute on the anchor (raw phone number)
            var a   = li.querySelector('a');
            var nav = a ? (a.getAttribute('mailbox-navigation') || '').toLowerCase() : '';

            li.style.display = (text.indexOf(lq) >= 0 || nav.indexOf(lq) >= 0) ? '' : 'none';
        });
        applyingFilter = false;
    }

    // Re-apply the filter when Vue adds/removes contacts (e.g. new conversation).
    // We watch only childList on the <ul> itself — NOT attributes — to avoid
    // an infinite loop where setting style.display triggers the observer again.
    function watchContactList() {
        var ul = document.querySelector('#app-mailbox-peers .contact-list');
        if (!ul) { setTimeout(watchContactList, 500); return; }
        new MutationObserver(function () {
            if (applyingFilter || !lastContactQuery) return;
            filterContacts(lastContactQuery);
        }).observe(ul, { childList: true, subtree: false });
    }

    // ── Message search ────────────────────────────────────────────────────────
    var savedHtml = null;
    var marks     = [];
    var markIdx   = -1;

    function getContainer() {
        return document.querySelector('#ocsms-messages-wrap .ocsms-messages-container');
    }

    function restore() {
        var c = getContainer();
        if (c && savedHtml !== null) c.innerHTML = savedHtml;
        marks = []; markIdx = -1;
    }

    function resetSaved() { savedHtml = null; marks = []; markIdx = -1; }

    function hlHtml(html, q) {
        var re = new RegExp('(' + esc(q) + ')(?![^<]*>)', 'gi');
        var tagRe = /<[^>]+>/g, out = '', last = 0, m;
        while ((m = tagRe.exec(html)) !== null) {
            out += html.slice(last, m.index).replace(re, '<mark class="ocsms-hl">$1</mark>');
            out += m[0]; last = tagRe.lastIndex;
        }
        return out + html.slice(last).replace(re, '<mark class="ocsms-hl">$1</mark>');
    }

    function doSearch(q) {
        restore();
        updateUI(q);
        var c = getContainer();
        if (!q || !c) return;
        if (savedHtml === null) savedHtml = c.innerHTML;
        var plain = savedHtml.replace(/<[^>]*>/g, '').toLowerCase();
        if (plain.indexOf(q.toLowerCase()) < 0) { updateUI(q); return; }
        c.innerHTML = hlHtml(savedHtml, q);
        marks = Array.from(c.querySelectorAll('.ocsms-hl'));
        markIdx = 0;
        if (marks.length) { marks[0].classList.add('ocsms-hl-active'); scrollToMark(marks[0]); }
        updateUI(q);
    }

    function goToMatch(dir) {
        if (!marks.length) return;
        marks[markIdx].classList.remove('ocsms-hl-active');
        markIdx = (markIdx + dir + marks.length) % marks.length;
        marks[markIdx].classList.add('ocsms-hl-active');
        scrollToMark(marks[markIdx]);
        updateUI(document.getElementById('ocsms-msg-search-input')?.value || '');
    }

    function updateUI(q) {
        var n = marks.length, hasQ = q && q.trim().length > 0;
        var count = document.getElementById('ocsms-search-count');
        var prev  = document.getElementById('ocsms-search-prev');
        var next  = document.getElementById('ocsms-search-next');
        var none  = document.getElementById('ocsms-search-none');
        if (count) count.textContent = n > 0 ? (markIdx + 1) + ' / ' + n : '';
        if (prev)  prev.style.display  = n > 0 ? '' : 'none';
        if (next)  next.style.display  = n > 0 ? '' : 'none';
        if (none)  none.style.display  = (hasQ && n === 0) ? '' : 'none';
    }

    // ── Event delegation (survives Vue v-if re-renders) ───────────────────────
    document.addEventListener('input', function (e) {
        if (e.target.id === 'ocsms-contact-search')   filterContacts(e.target.value);
        if (e.target.id === 'ocsms-msg-search-input') doSearch(e.target.value.trim());
    });

    document.addEventListener('keydown', function (e) {
        if (e.target.id === 'ocsms-msg-search-input' && e.key === 'Enter') {
            e.preventDefault(); goToMatch(1);
        }
    });

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (t.id === 'ocsms-search-prev' || t.closest && t.closest('#ocsms-search-prev')) goToMatch(-1);
        if (t.id === 'ocsms-search-next' || t.closest && t.closest('#ocsms-search-next')) goToMatch(1);

        // Reset message search when switching conversation
        if (t.closest && t.closest('#app-mailbox-peers li')) {
            resetSaved();
            var inp = document.getElementById('ocsms-msg-search-input');
            if (inp && inp.value.trim()) setTimeout(function () { doSearch(inp.value.trim()); }, 700);
        }
    });

    // ── Init ──────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        watchContactList();
    });

})();
