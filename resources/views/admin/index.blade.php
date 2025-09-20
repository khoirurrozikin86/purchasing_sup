@extends('admin.admin_dashboard')

@section('admin')
    <style>
        /* ===== Theme ===== */
        :root {
            --ink: #F4F7FF;
            --muted: #A8B9D6;
            --navy: #0F1B2D;
            --navy2: #182B46;
            --shadow: 0 10px 26px rgba(0, 0, 0, .22);

            /* Palet kartu */
            --pr1: #2E56A7;
            --pr2: #4C78E5;
            /* PR  (biru)   */
            --po1: #5B3EA8;
            --po2: #8A78F0;
            /* PO  (ungu)   */
            --pd1: #1B8A86;
            --pd2: #2CC0B9;
            /* Pending (teal)*/
        }

        /* ===== Hero ===== */
        .hero {
            border-radius: 18px;
            padding: 22px;
            background: linear-gradient(135deg, var(--navy), var(--navy2));
            color: var(--ink);
            box-shadow: var(--shadow);
            margin-bottom: 18px;
        }

        .hero-time {
            font-size: clamp(32px, 6vw, 64px);
            font-weight: 900;
            line-height: 1
        }

        .hero-date {
            color: var(--muted);
            font-weight: 600
        }

        .chips {
            display: flex;
            gap: 10px;
            flex-wrap: wrap
        }

        .chip {
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .10);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            transition: .2s;
        }

        .chip:hover {
            background: rgba(255, 255, 255, .18);
            transform: translateY(-2px)
        }

        /* ===== Grid ===== */
        .section {
            display: grid;
            gap: 16px
        }

        @media(min-width:992px) {
            .section-3 {
                grid-template-columns: repeat(3, 1fr)
            }

            .section-2 {
                grid-template-columns: repeat(2, 1fr)
            }
        }

        /* ===== Ring KPI (bukan card) ===== */
        .metric {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 16px;
            border-radius: 14px;
            color: #eaf1ff;
            text-decoration: none;
            box-shadow: var(--shadow);
            transition: transform .25s ease, box-shadow .25s ease;
            border: 1px solid rgba(255, 255, 255, .10);
        }

        .metric:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, .28)
        }

        /* Tema per kartu */
        .metric--pr {
            background: linear-gradient(135deg, var(--pr1), var(--pr2));
        }

        .metric--po {
            background: linear-gradient(135deg, var(--po1), var(--po2));
        }

        .metric--pend {
            background: linear-gradient(135deg, var(--pd1), var(--pd2));
        }

        .metric .title {
            font-size: .82rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .85);
            font-weight: 800
        }

        .metric .desc {
            margin-top: 4px;
            font-size: .92rem;
            color: rgba(255, 255, 255, .75)
        }

        .ring {
            position: relative;
            width: 110px;
            height: 110px;
            flex: 0 0 auto
        }

        .ring svg {
            width: 110px;
            height: 110px
        }

        .ring .bg {
            stroke: rgba(255, 255, 255, .30)
        }

        .ring .fg {
            stroke-linecap: round;
            transition: stroke-dashoffset .9s ease
        }

        .ring .center {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            font-weight: 900;
            font-size: 22px
        }

        .ring .center small {
            display: block;
            font-size: 11px;
            color: rgba(255, 255, 255, .86);
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase
        }

        /* ===== Pill bars ===== */
        .pill {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 18px;
            border-radius: 16px;
            color: #eef3ff;
            text-decoration: none;
            box-shadow: var(--shadow);
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .pill:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, .28)
        }

        .pill .label {
            font-size: .82rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .84);
            font-weight: 800
        }

        .pill .value {
            font-weight: 900;
            font-size: 34px
        }

        .pill.req {
            background: linear-gradient(135deg, #1F4460 0%, #2F80A1 100%)
        }

        .pill.pur {
            background: linear-gradient(135deg, #3B46C9 0%, #586BFF 100%)
        }

        /* Mini helpers */
        .fadeUp {
            transform: translateY(12px);
            opacity: 0;
            animation: fadeUp .6s ease forwards
        }

        @keyframes fadeUp {
            to {
                transform: translateY(0);
                opacity: 1
            }
        }

        .pulse {
            animation: pulse .4s ease
        }

        @keyframes pulse {
            50% {
                transform: scale(1.06)
            }
        }
    </style>

    <div class="page-content">
        <div class="container">

            {{-- HERO --}}
            <div class="hero d-flex flex-wrap justify-content-between align-items-end">
                <div>
                    <div id="timenow" class="hero-time">00:00:00</div>
                    <div id="datenow" class="hero-date">–</div>
                </div>
                <div class="chips">
                    <a href="{{ route('add.purchaserequest') }}" class="chip">Tambah PR</a>
                    <a href="#" class="chip">Tambah PO</a>
                    <a href="{{ route('all.purchaserequest') }}" class="chip">Daftar PR</a>
                    <a href="{{ route('all.purchaseorder') }}" class="chip">Daftar PO</a>
                </div>
            </div>

            {{-- ROW 1: 3 ring KPI sejajar --}}
            <div class="section section-3">
                {{-- PR --}}
                <a href="{{ route('all.purchaserequest') }}" class="metric metric--pr fadeUp" style="animation-delay:.05s">
                    <div class="ring">
                        <svg viewBox="0 0 120 120" aria-hidden="true">
                            <defs>
                                <linearGradient id="grad-pr" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#2E56A7" />
                                    <stop offset="100%" stop-color="#4C78E5" />
                                </linearGradient>
                            </defs>
                            <circle class="bg" cx="60" cy="60" r="56" fill="none" stroke-width="10" />
                            <circle class="fg" cx="60" cy="60" r="56" fill="none"
                                stroke="url(#grad-pr)" stroke-width="10" stroke-dasharray="351.86"
                                stroke-dashoffset="351.86" />
                        </svg>
                        <div class="center"><span id="v-pr">0</span><small>Request PR</small></div>
                    </div>
                    <div>
                        <div class="title">Request Document (PR)</div>
                        <div class="desc">Total PR terdaftar</div>
                    </div>
                </a>

                {{-- PO --}}
                <a href="{{ route('all.purchaseorder') }}" class="metric metric--po fadeUp" style="animation-delay:.10s">
                    <div class="ring">
                        <svg viewBox="0 0 120 120" aria-hidden="true">
                            <defs>
                                <linearGradient id="grad-po" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#5B3EA8" />
                                    <stop offset="100%" stop-color="#8A78F0" />
                                </linearGradient>
                            </defs>
                            <circle class="bg" cx="60" cy="60" r="56" fill="none" stroke-width="10" />
                            <circle class="fg" cx="60" cy="60" r="56" fill="none"
                                stroke="url(#grad-po)" stroke-width="10" stroke-dasharray="351.86"
                                stroke-dashoffset="351.86" />
                        </svg>
                        <div class="center"><span id="v-po">0</span><small>Purchase PO</small></div>
                    </div>
                    <div>
                        <div class="title">Purchase Document (PO)</div>
                        <div class="desc">Total PO dibuat</div>
                    </div>
                </a>

                {{-- Pending --}}
                <a href="{{ route('all.purchaserequestwaiting') }}" class="metric metric--pend fadeUp"
                    style="animation-delay:.15s">
                    <div class="ring">
                        <svg viewBox="0 0 120 120" aria-hidden="true">
                            <defs>
                                <linearGradient id="grad-pend" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#1B8A86" />
                                    <stop offset="100%" stop-color="#2CC0B9" />
                                </linearGradient>
                            </defs>
                            <circle class="bg" cx="60" cy="60" r="56" fill="none" stroke-width="10" />
                            <circle class="fg" cx="60" cy="60" r="56" fill="none"
                                stroke="url(#grad-pend)" stroke-width="10" stroke-dasharray="351.86"
                                stroke-dashoffset="351.86" />
                        </svg>
                        <div class="center"><span id="v-pend">0</span><small>Pending PR</small></div>
                    </div>
                    <div>
                        <div class="title">Pending Document (PR)</div>
                        <div class="desc">Menunggu approval</div>
                    </div>
                </a>
            </div>

            {{-- ROW 2: Pill bars --}}
            <div class="section section-2" style="margin-top:14px">
                <a href="{{ route('all.purchaserequest') }}" class="pill req fadeUp" style="animation-delay:.22s">
                    <div>
                        <div class="label">Request by Item</div>
                        <div class="small" style="opacity:.8">Jumlah baris permintaan</div>
                    </div>
                    <div class="value" id="v-req-item">0</div>
                </a>

                <a href="{{ route('all.purchaseorder') }}" class="pill pur fadeUp" style="animation-delay:.26s">
                    <div>
                        <div class="label">Purchase by Item</div>
                        <div class="small" style="opacity:.8">Jumlah baris pembelian</div>
                    </div>
                    <div class="value" id="v-pur-item">0</div>
                </a>
            </div>

        </div>
    </div>

    <script>
        // === Clock ===
        function pad(n) {
            return n < 10 ? '0' + n : n
        }

        function tick() {
            const now = new Date();
            document.getElementById('timenow').textContent =
                `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
            document.getElementById('datenow').textContent =
                now.toLocaleDateString('id-ID', {
                    weekday: 'long',
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric'
                });
        }
        tick();
        setInterval(tick, 1000);

        // === Rings ===
        const CIRC = 351.86; // 2πr (r=56)
        function setRing(el, value, share) {
            el.textContent = Number(value || 0).toLocaleString('id-ID');
            const circle = el.closest('.ring').querySelector('.fg');
            const offset = CIRC * (1 - Math.max(0, Math.min(1, share)));
            circle.style.strokeDashoffset = offset.toFixed(2);
            el.classList.add('pulse');
            setTimeout(() => el.classList.remove('pulse'), 300);
        }

        // === Data ===
        $(function() {
            const API = {
                prDoc: "{{ route('get.purchaserequestcountPR') }}",
                poDoc: "{{ route('get.purchaseordercountPO') }}",
                pendPR: "{{ route('get.purchaserequestcountPending') }}",
                reqItem: "{{ route('get.purchaserequestcount') }}",
                purItem: "{{ route('get.purchaseordercount') }}"
            };
            const n = x => Number((x && x.request) != null ? x.request : 0) || 0;

            function hydrate() {
                $.when(
                    $.get(API.prDoc), $.get(API.poDoc), $.get(API.pendPR),
                    $.get(API.reqItem), $.get(API.purItem)
                ).done(function(a, b, c, d, e) {
                    const pr = n(a[0]),
                        po = n(b[0]),
                        pend = n(c[0]);
                    const total = Math.max(1, pr + po + pend);

                    setRing(document.getElementById('v-pr'), pr, pr / total);
                    setRing(document.getElementById('v-po'), po, po / total);
                    setRing(document.getElementById('v-pend'), pend, pend / total);

                    $('#v-req-item').text(n(d[0]).toLocaleString('id-ID')).addClass('pulse');
                    setTimeout(() => $('#v-req-item').removeClass('pulse'), 300);
                    $('#v-pur-item').text(n(e[0]).toLocaleString('id-ID')).addClass('pulse');
                    setTimeout(() => $('#v-pur-item').removeClass('pulse'), 300);
                }).fail(function() {
                    ['v-pr', 'v-po', 'v-pend', 'v-req-item', 'v-pur-item'].forEach(id => $('#' + id).text(
                        '—'));
                });
            }

            hydrate();
            setInterval(hydrate, 30000); // refresh tiap 30 detik
        });
    </script>
@endsection
