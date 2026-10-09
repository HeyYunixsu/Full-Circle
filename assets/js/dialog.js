// Styled replacement for the browser's confirm()/alert() popups.
//
// Markup:  <a href="..." data-confirm="Message" data-confirm-title="Delete session?" data-confirm-ok="Delete" data-confirm-danger>
//          <form data-confirm="..."> works the same (the clicked submit button's name/value is kept).
// Script:  appConfirm('Message', { title, ok, danger }).then(ok => { if (ok) ... });
//          appAlert('Message', { title });
(function () {
    var overlay, titleEl, msgEl, okBtn, cancelBtn, resolver, lastFocus;
    var ICON_ALERT = '<svg class="icon-svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2L1 21h22L12 2z"/><line x1="12" y1="10" x2="12" y2="14"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
    var ICON_INFO  = '<svg class="icon-svg" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="11"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';

    function build() {
        overlay = document.createElement('div');
        overlay.className = 'modal-overlay dialog';
        overlay.innerHTML =
            '<div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="dlg-title" aria-describedby="dlg-msg">' +
                '<div class="dialog-head"><span class="dialog-icon"></span>' +
                '<div><h3 id="dlg-title"></h3><p id="dlg-msg"></p></div></div>' +
                '<div class="modal-actions">' +
                    '<button type="button" class="btn btn-secondary" data-act="cancel">Cancel</button>' +
                    '<button type="button" class="btn btn-primary" data-act="ok">OK</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);
        titleEl = overlay.querySelector('#dlg-title');
        msgEl = overlay.querySelector('#dlg-msg');
        okBtn = overlay.querySelector('[data-act=ok]');
        cancelBtn = overlay.querySelector('[data-act=cancel]');

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) return close(false);
            var act = e.target.closest('[data-act]');
            if (act) close(act.dataset.act === 'ok');
        });
        overlay.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.preventDefault(); close(false); }
            if (e.key === 'Tab') {   // keep focus inside the dialog
                var btns = [cancelBtn, okBtn].filter(function (b) { return !b.hidden; });
                var i = btns.indexOf(document.activeElement);
                e.preventDefault();
                btns[(i + (e.shiftKey ? -1 : 1) + btns.length) % btns.length].focus();
            }
        });
    }

    function close(ok) {
        if (!resolver) return;
        overlay.classList.remove('show');
        var r = resolver;
        resolver = null;
        if (lastFocus && lastFocus.focus) lastFocus.focus();
        r(ok);
    }

    window.appConfirm = function (message, o) {
        o = o || {};
        if (!overlay) build();
        if (resolver) close(false);
        titleEl.textContent = o.title || 'Are you sure?';
        msgEl.textContent = message || '';
        msgEl.hidden = !message;
        okBtn.textContent = o.ok || 'Confirm';
        okBtn.className = 'btn ' + (o.danger ? 'btn-danger-solid' : 'btn-primary');
        cancelBtn.textContent = o.cancel || 'Cancel';
        cancelBtn.hidden = !!o.alert;
        overlay.classList.toggle('is-danger', !!o.danger);
        overlay.querySelector('.dialog-icon').innerHTML = o.danger ? ICON_ALERT : ICON_INFO;
        lastFocus = document.activeElement;
        overlay.classList.add('show');
        // Destructive actions start on Cancel so a stray Enter does not delete anything
        (o.danger && !o.alert ? cancelBtn : okBtn).focus();
        return new Promise(function (res) { resolver = res; });
    };

    window.appAlert = function (message, o) {
        o = o || {};
        return window.appConfirm(message, { title: o.title || 'Heads up', ok: o.ok || 'OK', alert: true, danger: o.danger });
    };

    function optsFrom(el) {
        return { title: el.dataset.confirmTitle, ok: el.dataset.confirmOk, danger: 'confirmDanger' in el.dataset };
    }

    // Links and plain buttons
    document.addEventListener('click', function (e) {
        var el = e.target.closest('a[data-confirm], button[data-confirm]');
        if (!el || el.dataset.confirmed) return;
        if (el.tagName === 'BUTTON' && el.type === 'submit' && el.form && el.form.dataset.confirm) return; // the form handles it
        e.preventDefault();
        e.stopPropagation();
        window.appConfirm(el.dataset.confirm, optsFrom(el)).then(function (ok) {
            if (!ok) return;
            if (el.tagName === 'A') { window.location.href = el.href; return; }
            el.dataset.confirmed = '1';
            el.click();
            delete el.dataset.confirmed;
        });
    }, true);

    // Forms
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.matches('form[data-confirm]') || f.dataset.confirmed) return;
        e.preventDefault();
        var submitter = e.submitter;
        window.appConfirm(f.dataset.confirm, optsFrom(f)).then(function (ok) {
            if (!ok) return;
            f.dataset.confirmed = '1';
            if (f.requestSubmit) f.requestSubmit(submitter || undefined); else f.submit();
            delete f.dataset.confirmed;
        });
    }, true);

    // One send per POST form: a double-click or Enter-then-click would otherwise save twice (e.g. two
    // identical events). Bubble phase, so it runs after the confirm step and skips forms that JS handles.
    // The button is not disabled, because some forms need the clicked button's name/value.
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (e.defaultPrevented || (f.getAttribute('method') || '').toLowerCase() !== 'post') return;
        if (f.dataset.sending) { e.preventDefault(); return; }
        f.dataset.sending = '1';
        var b = e.submitter;
        if (b) b.setAttribute('aria-busy', 'true');
        setTimeout(function () { delete f.dataset.sending; if (b) b.removeAttribute('aria-busy'); }, 8000);   // downloads keep the page open
    });
    window.addEventListener('pageshow', function (e) {   // Back button restores the page as it was: allow sending again
        if (e.persisted) document.querySelectorAll('form[data-sending]').forEach(function (f) { delete f.dataset.sending; });
    });

    // The same pop-up header.php shows after a page action, for actions that finish without loading a page
    // (saving a badge design, fixing a name on the badge page). appToast('Saved.', 'success'|'error'|'warning'|'info')
    var TOAST_ICONS = {
        success: '<circle cx="12" cy="12" r="10"/><polyline points="9 12 12 15 16 9"/>',
        error:   '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
        warning: '<path d="M12 2L1 21h22L12 2z"/><line x1="12" y1="10" x2="12" y2="14"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        info:    '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="11"/><line x1="12" y1="8" x2="12.01" y2="8"/>'
    };
    function svg(cls, paths) {
        return '<svg class="' + cls + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths + '</svg>';
    }
    window.appToast = function (message, type) {
        type = TOAST_ICONS[type] ? type : 'success';
        var stack = document.querySelector('.flash-stack');
        if (!stack) { stack = document.createElement('div'); stack.className = 'flash-stack'; document.body.appendChild(stack); }
        var t = document.createElement('div');
        t.className = 'flash-toast flash-' + type;
        t.setAttribute('role', type === 'error' || type === 'warning' ? 'alert' : 'status');
        t.style.setProperty('--ft-life', { success: 5, info: 6, warning: 8, error: 9 }[type] + 's');   // same as header.php
        t.innerHTML = '<span class="ft-icon">' + svg('icon-svg', TOAST_ICONS[type]) + '</span><div class="ft-body"></div>'
            + '<button type="button" class="ft-close" aria-label="Dismiss notification">' + svg('icon-svg icon-sm', '<line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>') + '</button>'
            + '<span class="ft-timer" aria-hidden="true"></span>';
        t.querySelector('.ft-body').textContent = message;
        stack.innerHTML = '';   // one message at a time: the newest replaces the last
        stack.appendChild(t);
        var hide = function () { t.classList.add('hide'); setTimeout(function () { t.remove(); }, 250); };
        t.querySelector('.ft-close').addEventListener('click', hide);
        t.querySelector('.ft-timer').addEventListener('animationend', hide);
    };
    // Show a message on the next page load (for actions that reload the page right after saving)
    window.appToastNext = function (message, type) {
        try { sessionStorage.setItem('appToastNext', JSON.stringify({ m: message, t: type })); } catch (e) {}
    };
    try {
        var next = JSON.parse(sessionStorage.getItem('appToastNext') || 'null');
        sessionStorage.removeItem('appToastNext');
        if (next) window.appToast(next.m, next.t);
    } catch (e) {}
})();
