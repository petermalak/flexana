<!doctype html>
@php
    // Optional theme overrides for embedding in WordPress:
    // /web-session-bookings?primary=f4d0d2&accent=6a4a4f&bg=fffaf9&text=111111&muted=5b5b5b&card=ffffff
    $hex = static function (?string $value, string $fallback): string {
        $v = strtolower(trim((string) $value));
        $v = ltrim($v, '#');
        if ($v === '' || ! preg_match('/^[0-9a-f]{6}$/', $v)) {
            return $fallback;
        }

        return '#'.$v;
    };

    $primary = $hex(request()->query('primary'), '#f4d0d2');
    $primary2 = $hex(request()->query('primary2'), '#f7e1e3');
    // Default accent is intentionally not pure black (reads softer on blush backgrounds).
    $accent = $hex(request()->query('accent'), '#3a2a2a');
    $bg = $hex(request()->query('bg'), '#fffaf9');
    $card = $hex(request()->query('card'), '#ffffff');
    $text = $hex(request()->query('text'), '#111111');
    $muted = $hex(request()->query('muted'), '#5b5b5b');
    $border = $hex(request()->query('border'), '#efd6d8');

    $paletteParentsRaw = (string) config('booking_embed.palette_parents', '');
    $paletteParents = array_values(array_filter(array_map('trim', explode(',', $paletteParentsRaw))));
@endphp
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book a session</title>
    <style>
        :root {
            color-scheme: light;
            --flex-primary: {{ $primary }};
            --flex-primary-2: {{ $primary2 }};
            --flex-accent: {{ $accent }};
            --flex-bg: {{ $bg }};
            --flex-card: {{ $card }};
            --flex-text: {{ $text }};
            --flex-muted: {{ $muted }};
            --flex-border: {{ $border }};
            --flex-shadow: 0 10px 30px rgba(17, 17, 17, 0.06);
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: radial-gradient(1200px 500px at 20% 0%, var(--flex-primary-2), var(--flex-bg));
            color: var(--flex-text);
        }

        header {
            padding: 16px 18px;
            border-bottom: 1px solid var(--flex-border);
            background: rgba(255, 255, 255, 0.78);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 2;
        }

        h1 { font-size: 16px; margin: 0; font-weight: 650; letter-spacing: 0.2px; }
        .wrap { padding: 14px 18px 40px; max-width: 980px; margin: 0 auto; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin: 12px 0 16px; }
        label { font-size: 12px; color: var(--flex-muted); display: block; margin-bottom: 6px; font-weight: 600; }
        input, select, button { font: inherit; }
        input, select {
            padding: 10px 10px;
            border: 1px solid var(--flex-border);
            border-radius: 12px;
            background: var(--flex-card);
            min-width: 200px;
            outline: none;
        }
        input:focus, select:focus {
            box-shadow: 0 0 0 4px rgba(244, 208, 210, 0.55);
            border-color: var(--flex-primary);
        }
        button {
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid rgba(58, 42, 42, 0.35);
            background: linear-gradient(180deg, #6a4a4f, var(--flex-accent));
            color: #fff;
            cursor: pointer;
        }
        button.secondary { background: var(--flex-card); color: var(--flex-accent); border-color: var(--flex-border); }
        button:disabled { opacity: .55; cursor: not-allowed; }
        .muted { color: var(--flex-muted); font-size: 13px; }
        .card {
            border: 1px solid var(--flex-border);
            border-radius: 16px;
            background: var(--flex-card);
            padding: 14px;
            margin: 10px 0;
            box-shadow: var(--flex-shadow);
        }
        .title { font-weight: 650; margin: 0 0 6px; }
        .meta { font-size: 13px; color: var(--flex-muted); line-height: 1.35; }
        .pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 12px;
            border: 1px solid var(--flex-border);
            background: rgba(244, 208, 210, 0.22);
            color: var(--flex-text);
        }
        .right { float: right; }
        .banner {
            border: 1px solid var(--flex-border);
            background: rgba(244, 208, 210, 0.18);
            color: var(--flex-text);
            padding: 12px 12px;
            border-radius: 14px;
            margin: 10px 0;
            white-space: pre-wrap;
        }
        .banner--error {
            border-color: #f3b4b4;
            background: #fff4f4;
            color: #7a1212;
        }
        .banner--success {
            border-color: #b7e7d6;
            background: #f0fdf9;
            color: #0f5132;
        }
        dialog { border: 1px solid var(--flex-border); border-radius: 16px; padding: 0; width: min(640px, calc(100vw - 24px)); box-shadow: var(--flex-shadow); }
        dialog::backdrop { background: rgba(0,0,0,.35); }
        .dlg-head { padding: 14px 16px; border-bottom: 1px solid var(--flex-border); font-weight: 650; background: rgba(244, 208, 210, 0.12); }
        .dlg-body { padding: 14px 16px 6px; }
        .dlg-foot { padding: 12px 16px 16px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid rgba(239, 214, 216, 0.7); }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        @media (max-width: 720px) { .grid { grid-template-columns: 1fr; } }
        .field { margin-bottom: 10px; }
        .field input { width: 100%; box-sizing: border-box; min-width: 0; }
    </style>
</head>
<body>
<header>
    <h1>Book a session</h1>
    <div class="muted">Powered by Flexana scheduling</div>
</header>

<div class="wrap">
    <div id="banner" class="banner" style="display:none;"></div>

    <div class="row">
        <div>
            <label for="date">Date</label>
            <input id="date" type="date">
        </div>
        <div>
            <label for="category">Category</label>
            <select id="category">
                <option value="Yoga">Yoga</option>
                <option value="Reformer Pilates">Reformer Pilates</option>
            </select>
        </div>
        <div>
            <button id="reload" type="button">Refresh</button>
        </div>
    </div>

    <div id="sessions"></div>
</div>

<dialog id="bookDlg">
    <form method="dialog" id="bookForm">
        <div class="dlg-head">Confirm booking</div>
        <div class="dlg-body">
            <div class="muted" id="dlgSession"></div>
            <div class="grid" style="margin-top: 10px;">
                <div class="field">
                    <label for="firstName">First name</label>
                    <input id="firstName" autocomplete="given-name" required>
                </div>
                <div class="field">
                    <label for="lastName">Last name</label>
                    <input id="lastName" autocomplete="family-name" required>
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label for="email">Email</label>
                    <input id="email" type="email" autocomplete="email" required>
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label for="phone">Phone (optional)</label>
                    <input id="phone" autocomplete="tel">
                </div>
                <div class="field">
                    <label for="spots">Spots</label>
                    <input id="spots" type="number" min="1" max="20" value="1" required>
                </div>
                <div class="field">
                    <label for="promo">Promo code (optional)</label>
                    <input id="promo" maxlength="64">
                </div>
            </div>
        </div>
        <div class="dlg-foot">
            <button class="secondary" value="cancel" formnovalidate>Cancel</button>
            <button id="confirmBook" value="default">Book</button>
        </div>
    </form>
</dialog>

<script>
(() => {
    const sessionsUrl = @json(url('/api/v1/web-sessions'));
    const bookingUrl = @json(url('/api/v1/web-session-bookings'));
    const paletteParents = @json($paletteParents);

    const banner = document.getElementById('banner');
    const sessionsEl = document.getElementById('sessions');
    const dateInput = document.getElementById('date');
    const categorySelect = document.getElementById('category');
    const reloadBtn = document.getElementById('reload');

    const dlg = document.getElementById('bookDlg');
    const dlgSession = document.getElementById('dlgSession');

    let selected = null;

    function normalizeHex6(input) {
        if (!input) return null;
        let v = String(input).trim();
        if (!v) return null;
        v = v.replace(/^#/, '');
        if (!/^[0-9a-fA-F]{6}$/.test(v)) return null;
        return '#' + v.toLowerCase();
    }

    function applyPaletteFromParent(palette) {
        if (!palette || typeof palette !== 'object') return;

        const root = document.documentElement;
        const map = [
            ['primary', '--flex-primary'],
            ['primary2', '--flex-primary-2'],
            ['accent', '--flex-accent'],
            ['bg', '--flex-bg'],
            ['card', '--flex-card'],
            ['text', '--flex-text'],
            ['muted', '--flex-muted'],
            ['border', '--flex-border'],
        ];

        for (const [key, cssVar] of map) {
            const hex = normalizeHex6(palette[key]);
            if (!hex) continue;
            root.style.setProperty(cssVar, hex);
        }
    }

    function isAllowedParentOrigin(origin) {
        if (!origin) return false;
        if (!Array.isArray(paletteParents) || paletteParents.length === 0) return false;
        return paletteParents.includes(origin);
    }

    window.addEventListener('message', (event) => {
        const data = event && event.data;
        if (!data || typeof data !== 'object') return;
        if (data.type !== 'flexana:booking-palette') return;

        if (!isAllowedParentOrigin(event.origin)) {
            return;
        }

        applyPaletteFromParent(data.palette || {});
    });

    function showBanner(msg, kind = 'info') {
        banner.style.display = 'block';
        banner.textContent = msg;
        banner.classList.remove('banner--error', 'banner--success');
        if (kind === 'error') banner.classList.add('banner--error');
        if (kind === 'success') banner.classList.add('banner--success');
    }
    function hideBanner() {
        banner.style.display = 'none';
        banner.textContent = '';
        banner.classList.remove('banner--error', 'banner--success');
    }

    function formatApiError(status, json) {
        const parts = [];
        parts.push(`Request failed (HTTP ${status}).`);

        if (!json) {
            parts.push('No JSON body was returned.');
            return parts.join('\n');
        }

        if (json.message) {
            parts.push(`Message: ${json.message}`);
        }

        if (json.errors) {
            parts.push('Details:');
            try {
                parts.push(JSON.stringify(json.errors, null, 2));
            } catch {
                parts.push(String(json.errors));
            }
        } else {
            // Include compact payload for unknown shapes
            try {
                const clone = { ...json };
                parts.push(JSON.stringify(clone, null, 2));
            } catch {
                parts.push(String(json));
            }
        }

        return parts.join('\n');
    }

    function ymd(d) {
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function fmtWhen(iso) {
        try {
            const d = new Date(iso);
            return d.toLocaleString(undefined, { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
        } catch {
            return iso;
        }
    }

    async function loadSessions() {
        hideBanner();
        sessionsEl.innerHTML = '';
        reloadBtn.disabled = true;

        const params = new URLSearchParams();
        const date = dateInput.value;
        const category = categorySelect.value;
        if (date) params.set('date', date);
        if (category) params.set('category', category);

        const url = `${sessionsUrl}?${params.toString()}`;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const json = await res.json().catch(() => null);

        if (!res.ok) {
            showBanner(formatApiError(res.status, json), 'error');
            reloadBtn.disabled = false;
            return;
        }

        const items = Array.isArray(json?.data) ? json.data : [];
        if (items.length === 0) {
            sessionsEl.innerHTML = `<div class="muted">No sessions found for this filter.</div>`;
            reloadBtn.disabled = false;
            return;
        }

        for (const s of items) {
            const card = document.createElement('div');
            card.className = 'card';

            const canBook = !!s.canBook && !s.isFull;
            const spots = Number(s.remainingSpots ?? 0);
            const price = Number(s.price ?? 0);

            card.innerHTML = `
                <div class="pill right">${s.serviceType || ''}</div>
                <div class="title">${s.service || 'Session'}</div>
                <div class="meta">
                    <div><strong>When:</strong> ${fmtWhen(s.date)}</div>
                    <div><strong>Instructor:</strong> ${s.instructor || ''}</div>
                    <div><strong>Location:</strong> ${s.location_name || ''}</div>
                    <div><strong>Spots left:</strong> ${spots}</div>
                    <div><strong>Price (drop-in):</strong> ${price.toFixed(2)}</div>
                </div>
                <div style="margin-top: 12px; display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="bookBtn" ${canBook ? '' : 'disabled'}>Book</button>
                </div>
            `;

            const btn = card.querySelector('.bookBtn');
            if (btn) {
                btn.addEventListener('click', () => openDialog(s));
            }

            sessionsEl.appendChild(card);
        }

        reloadBtn.disabled = false;
    }

    function openDialog(session) {
        selected = session;
        dlgSession.textContent = `${session.service} — ${fmtWhen(session.date)} — ${session.instructor || ''}`;
        document.getElementById('spots').value = '1';
        document.getElementById('promo').value = '';
        dlg.showModal();
    }

    document.getElementById('bookForm').addEventListener('submit', async (e) => {
        // dialog submit closes unless prevented; we handle async ourselves.
        e.preventDefault();

        if (!selected) {
            dlg.close();
            return;
        }

        const submitter = e.submitter;
        if (submitter && submitter.getAttribute('formnovalidate') !== null) {
            dlg.close();
            return;
        }

        hideBanner();
        const btn = document.getElementById('confirmBook');
        btn.disabled = true;

        const promoRaw = document.getElementById('promo').value.trim();
        const phoneRaw = document.getElementById('phone').value.trim();

        const payload = {
            sessionID: Number(selected.id),
            spots: Number(document.getElementById('spots').value || '1'),
            ...(promoRaw ? { promoCode: promoRaw } : {}),
            // Website embed is drop-in only (package bookings are handled in-app / admin flows).
            isDropIn: true,
            customer: {
                firstName: document.getElementById('firstName').value.trim(),
                lastName: document.getElementById('lastName').value.trim(),
                email: document.getElementById('email').value.trim(),
                ...(phoneRaw ? { phone: phoneRaw } : {}),
            },
        };

        const res = await fetch(bookingUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const json = await res.json().catch(() => null);
        btn.disabled = false;

        if (!res.ok) {
            showBanner(formatApiError(res.status, json), 'error');
            return;
        }

        dlg.close();
        showBanner(`Success: ${json?.message || 'Booking created.'}\n${JSON.stringify(json?.data ?? {}, null, 2)}`, 'success');
        await loadSessions();
    });

    reloadBtn.addEventListener('click', loadSessions);
    dateInput.addEventListener('change', loadSessions);
    categorySelect.addEventListener('change', loadSessions);

    // Default: today (browser local calendar). Users can change date.
    const today = new Date();
    dateInput.value = ymd(today);

    loadSessions();
})();
</script>
</body>
</html>
