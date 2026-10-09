// Draws a saved Badge Designer layout for one attendee.
// Used by the badge page (print), the Badges list, the event form's design picker and the event page.
// renderBadge(layout, data, scale) returns HTML; scale < 1 makes a thumbnail of the exact same drawing.

const BADGE_SAMPLE = { name: 'Juan Dela Cruz', company: 'Full Circle Events Asia', designation: 'Event Coordinator', code: '123456789', event: 'Tech Summit 2026', qr_url: null };

// Stand-in QR for previews (three corner squares) so thumbnails need no network
const BADGE_QR_PLACEHOLDER = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 21 21" shape-rendering="crispEdges"><path fill="#111" d="M0 0h7v7H0zM14 0h7v7h-7zM0 14h7v7H0zM9 1h2v2H9zM8 4h3v2H8zM9 8h4v2H9zM14 9h2v3h-2zM17 9h3v2h-3zM9 12h2v3H9zM12 14h3v2h-3zM16 13h2v4h-2zM9 17h3v3H9zM14 18h5v2h-5zM1 9h5v2H1zM3 11h2v2H3z"/><path fill="#fff" d="M1 1h5v5H1zM15 1h5v5h-5zM1 15h5v5H1z"/><path fill="#111" d="M2 2h3v3H2zM16 2h3v3h-3zM2 16h3v3H2z"/></svg>');

function badgeEsc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
// Layout values go into style="": keep only characters a colour or font name needs
const badgeCss = v => String(v ?? '').replace(/[^\w\s#.,%()\-]/g, '');
const badgeNum = v => Number(v) || 0;

function renderBadge(layout, data = BADGE_SAMPLE, scale = 1) {
    const w = badgeNum(layout?.size?.w) || 360, h = badgeNum(layout?.size?.h) || 225;
    const fields = { name: data.name, company: data.company, designation: data.designation, code: data.code, event: data.event };
    const blocks = (layout?.elements || []).filter(e => e.type === 'qr' || e.type === 'img');
    // Room for a text: up to the next QR/logo on its right that it would run into, else the badge edge
    const room = (el, fs) => {
        const top = badgeNum(el.y), bottom = top + fs * 1.3, x = badgeNum(el.x);
        const stops = blocks.map(b => ({ x: badgeNum(b.x), y: badgeNum(b.y), h: badgeNum(b.size || b.h) }))
            .filter(b => b.x > x && b.y < bottom && b.y + b.h > top).map(b => b.x - 8);
        return Math.min(w - 10, ...stops) - x;
    };
    let inner = '';
    (layout?.elements || []).forEach(el => {
        const pos = `position:absolute;left:${badgeNum(el.x)}px;top:${badgeNum(el.y)}px;`;
        if (el.type === 'text') {
            // A field left empty for this attendee (e.g. no designation) prints nothing, never the designer's sample text
            const txt = el._field ? (fields[el._field] ?? '') : el.content;
            const fs = badgeNum(el.fontSize) || 14;
            inner += `<div data-fit-w="${Math.max(20, room(el, fs))}" style="${pos}font-family:'${badgeCss(el.fontFamily) || 'Poppins'}',sans-serif;font-size:${badgeNum(el.fontSize) || 14}px;font-weight:${badgeNum(el.fontWeight) || 400};color:${badgeCss(el.color)};white-space:nowrap;line-height:1.3;">${badgeEsc(txt)}</div>`;
        } else if (el.type === 'qr') {
            const s = badgeNum(el.size);
            inner += `<div style="${pos}width:${s}px;height:${s}px;background:#fff;"><img src="${badgeEsc(data.qr_url || BADGE_QR_PLACEHOLDER)}" alt="" style="display:block;width:100%;height:100%;"></div>`;
        } else if (el.type === 'img') {
            inner += `<img src="${badgeEsc(el.src)}" alt="" style="${pos}width:${badgeNum(el.w)}px;height:${badgeNum(el.h)}px;object-fit:contain;">`;
        } else if (el.type === 'rect' || el.type === 'line') {
            inner += `<div style="${pos}width:${badgeNum(el.w)}px;height:${badgeNum(el.h)}px;background:${badgeCss(el.fill)};opacity:${el.opacity ?? 1};border-radius:${badgeNum(el.radius)}px;"></div>`;
        }
    });
    const badge = `<div class="badge-render" style="width:${w}px;height:${h}px;background:${badgeCss(layout?.bg?.color) || '#fff'};border-radius:12px;position:relative;overflow:hidden;margin:0 auto;">${inner}</div>`;
    if (scale === 1) return badge;
    return `<div style="width:${w * scale}px;height:${h * scale}px;overflow:hidden;border-radius:${12 * scale}px;"><div style="width:${w}px;height:${h}px;transform:scale(${scale});transform-origin:0 0;">${badge}</div></div>`;
}

// Long texts (names) that are wider than their room shrink down to 65% of the designed size; a name still too
// long then wraps onto two lines at half size, which take the same height as one designed line.
// Needs the badge on the page, so call it after inserting renderBadge() output.
function fitBadgeText(root) {
    root.querySelectorAll('[data-fit-w]').forEach(el => {
        const max = Number(el.dataset.fitW), start = parseFloat(el.style.fontSize);
        let size = start;
        while (el.offsetWidth > max && size > start * 0.65) {
            size -= 0.5;
            el.style.fontSize = size + 'px';
        }
        if (el.offsetWidth > max) {
            el.style.fontSize = (start / 2) + 'px';
            el.style.whiteSpace = 'normal';
            el.style.width = max + 'px';
        }
    });
}
