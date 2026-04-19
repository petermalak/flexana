<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book a session</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: #fafafa; color: #111; }
        header { padding: 16px 18px; border-bottom: 1px solid #e6e6e6; background: #fff; position: sticky; top: 0; z-index: 2; }
        h1 { font-size: 16px; margin: 0; font-weight: 650; }
        .wrap { padding: 14px 18px 40px; max-width: 980px; margin: 0 auto; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin: 12px 0 16px; }
        label { font-size: 12px; color: #444; display: block; margin-bottom: 6px; }
        input, select, button { font: inherit; }
        input, select { padding: 10px 10px; border: 1px solid #d9d9d9; border-radius: 10px; background: #fff; min-width: 200px; }
        button { padding: 10px 14px; border-radius: 10px; border: 1px solid #111; background: #111; color: #fff; cursor: pointer; }
        button.secondary { background: #fff; color: #111; }
        button:disabled { opacity: .55; cursor: not-allowed; }
        .muted { color: #666; font-size: 13px; }
        .card { border: 1px solid #e6e6e6; border-radius: 14px; background: #fff; padding: 14px; margin: 10px 0; }
        .title { font-weight: 650; margin: 0 0 6px; }
        .meta { font-size: 13px; color: #444; line-height: 1.35; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; border: 1px solid #e6e6e6; background: #fafafa; }
        .right { float: right; }
        .error { border: 1px solid #f3b4b4; background: #fff4f4; color: #7a1212; padding: 10px 12px; border-radius: 12px; margin: 10px 0; white-space: pre-wrap; }
        dialog { border: 1px solid #e6e6e6; border-radius: 14px; padding: 0; width: min(640px, calc(100vw - 24px)); }
        dialog::backdrop { background: rgba(0,0,0,.35); }
        .dlg-head { padding: 14px 16px; border-bottom: 1px solid #eee; font-weight: 650; }
        .dlg-body { padding: 14px 16px 6px; }
        .dlg-foot { padding: 12px 16px 16px; display: flex; gap: 10px; justify-content: flex-end; }
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
    <div id="banner" class="error" style="display:none;"></div>

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
                <div class="field" style="grid-column: 1 / -1;">
                    <label for="dropin">Booking type</label>
                    <select id="dropin">
                        <option value="true" selected>Drop-in (pay)</option>
                        <option value="false">Use package (if available)</option>
                    </select>
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

    const banner = document.getElementById('banner');
    const sessionsEl = document.getElementById('sessions');
    const dateInput = document.getElementById('date');
    const categorySelect = document.getElementById('category');
    const reloadBtn = document.getElementById('reload');

    const dlg = document.getElementById('bookDlg');
    const dlgSession = document.getElementById('dlgSession');

    let selected = null;

    function showBanner(msg) {
        banner.style.display = 'block';
        banner.textContent = msg;
    }
    function hideBanner() {
        banner.style.display = 'none';
        banner.textContent = '';
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
            showBanner(`Failed to load sessions (${res.status}).\n${JSON.stringify(json, null, 2)}`);
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
        document.getElementById('dropin').value = 'true';
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

        const payload = {
            sessionID: Number(selected.id),
            spots: Number(document.getElementById('spots').value || '1'),
            promoCode: document.getElementById('promo').value || null,
            isDropIn: document.getElementById('dropin').value === 'true',
            customer: {
                firstName: document.getElementById('firstName').value.trim(),
                lastName: document.getElementById('lastName').value.trim(),
                email: document.getElementById('email').value.trim(),
                phone: document.getElementById('phone').value.trim() || null,
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
            showBanner(`Booking failed (${res.status}).\n${JSON.stringify(json, null, 2)}`);
            return;
        }

        dlg.close();
        showBanner(`Success: ${json?.message || 'Booking created.'}\n${JSON.stringify(json?.data ?? {}, null, 2)}`);
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
