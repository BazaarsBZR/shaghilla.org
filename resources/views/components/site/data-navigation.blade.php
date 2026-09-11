@php
    $items = [
        ['route' => 'home', 'active' => 'home', 'label' => 'الرئيسية', 'description' => 'آخر الأخبار', 'type' => 'home'],
        ['route' => 'public-money.index', 'active' => 'public-money.*', 'label' => 'المال العام', 'description' => 'الإنفاق والعقود', 'type' => 'money'],
        ['route' => 'financial-status.index', 'active' => 'financial-status.*', 'label' => 'الوضع المالي', 'description' => 'الدين والإيرادات', 'type' => 'status'],
        ['route' => 'government-tenders.index', 'active' => 'government-tenders.*', 'label' => 'المناقصات الحكومية', 'description' => 'الفرص والمواعيد', 'type' => 'tenders'],
    ];
@endphp

<div class="sh-data-nav-shell">
    <nav class="sh-data-nav" aria-label="الأقسام الرئيسية">
        @foreach ($items as $item)
            @php($isActive = request()->routeIs($item['active']))
            <a
                data-prefetch-page
                href="{{ route($item['route']) }}"
                @class(['is-active' => $isActive])
                @if($isActive) aria-current="page" @endif
            >
                <span class="sh-data-nav-icon" aria-hidden="true">
                    @if ($item['type'] === 'home')
                        <svg viewBox="0 0 24 24"><path d="m4 10 8-6.5 8 6.5v9.5h-6v-6h-4v6H4V10Z"/></svg>
                    @elseif ($item['type'] === 'money')
                        <svg viewBox="0 0 24 24"><path d="M5 7.5C5 6.12 8.13 5 12 5s7 1.12 7 2.5S15.87 10 12 10 5 8.88 5 7.5Zm0 4c0 1.38 3.13 2.5 7 2.5s7-1.12 7-2.5M5 15.5C5 16.88 8.13 18 12 18s7-1.12 7-2.5V7.5M5 7.5v8"/></svg>
                    @elseif ($item['type'] === 'tenders')
                        <svg viewBox="0 0 24 24"><path d="M7 3.5h7l4 4V20H7V3.5Zm7 0v4h4M10 12h5m-5 3h5"/></svg>
                    @else
                        <svg viewBox="0 0 24 24"><path d="M4 18.5h16M6.5 16V11m5 5V6m5 10V9"/></svg>
                    @endif
                </span>
                <span class="sh-data-nav-copy">
                    <strong>{{ $item['label'] }}</strong>
                    <small>{{ $item['description'] }}</small>
                </span>
                <span class="sh-data-nav-arrow" aria-hidden="true">←</span>
            </a>
        @endforeach
    </nav>
</div>

@once
    <style>
        .sh-data-nav-shell {
            position: relative;
            z-index: 35;
            padding: 12px 16px;
            border-block: 1px solid #dce5e7;
            background:
                linear-gradient(90deg, rgba(19, 122, 87, .05), transparent 34%, rgba(208, 45, 49, .035)),
                rgba(248, 250, 251, .96);
            backdrop-filter: blur(14px);
        }
        .sh-data-nav {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 7px;
            width: min(1120px, 100%);
            margin-inline: auto;
            padding: 6px;
            border: 1px solid #d9e3e5;
            border-radius: 22px;
            background: rgba(255, 255, 255, .92);
            box-shadow: 0 14px 38px rgba(11, 40, 50, .08);
        }
        .sh-data-nav a {
            position: relative;
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr) 18px;
            min-width: 0;
            min-height: 66px;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            overflow: hidden;
            border: 1px solid transparent;
            border-radius: 16px;
            color: #536873;
            text-align: start;
            text-decoration: none;
            transition: border-color 160ms ease, color 160ms ease, background 160ms ease, box-shadow 160ms ease, transform 160ms ease;
        }
        .sh-data-nav a::before {
            position: absolute;
            inset-block: 14px;
            inset-inline-start: 0;
            width: 3px;
            border-radius: 999px;
            background: #df2f32;
            content: '';
            opacity: 0;
            transform: scaleY(.45);
            transition: opacity 160ms ease, transform 160ms ease;
        }
        .sh-data-nav a:hover {
            border-color: #d9e5e2;
            color: #0b5f4a;
            background: #f6faf8;
            transform: translateY(-1px);
        }
        .sh-data-nav a:focus-visible {
            outline: 3px solid rgba(19, 122, 87, .22);
            outline-offset: 2px;
        }
        .sh-data-nav a.is-active {
            border-color: #163c46;
            color: #fff;
            background: linear-gradient(135deg, #0b2733, #124c4d 68%, #17654f);
            box-shadow: 0 10px 24px rgba(8, 42, 50, .18);
        }
        .sh-data-nav a.is-active::before {
            opacity: 1;
            transform: scaleY(1);
        }
        .sh-data-nav-icon {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: 13px;
            color: #137a57;
            background: #e8f4ef;
        }
        .sh-data-nav-icon svg {
            width: 21px;
            height: 21px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .sh-data-nav-copy {
            display: flex;
            min-width: 0;
            flex-direction: column;
            gap: 2px;
        }
        .sh-data-nav-copy strong {
            overflow: hidden;
            font-size: .9rem;
            font-weight: 900;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sh-data-nav-copy small {
            overflow: hidden;
            color: #84939a;
            font-size: .74rem;
            font-weight: 700;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sh-data-nav-arrow {
            color: #a2b0b5;
            font-size: 1rem;
            transition: transform 160ms ease;
        }
        .sh-data-nav a:hover .sh-data-nav-arrow { transform: translateX(-2px); }
        .sh-data-nav a.is-active .sh-data-nav-icon {
            color: #fff;
            background: rgba(255, 255, 255, .13);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .12);
        }
        .sh-data-nav a.is-active .sh-data-nav-copy small,
        .sh-data-nav a.is-active .sh-data-nav-arrow { color: rgba(255, 255, 255, .68); }

        @media (max-width: 780px) {
            .sh-data-nav-shell { padding: 8px; }
            .sh-data-nav {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 5px;
                padding: 5px;
                border-radius: 18px;
            }
            .sh-data-nav a {
                grid-template-columns: 34px minmax(0, 1fr);
                min-height: 54px;
                gap: 8px;
                padding: 7px 8px;
                border-radius: 13px;
            }
            .sh-data-nav-icon { width: 34px; height: 34px; border-radius: 10px; }
            .sh-data-nav-icon svg { width: 18px; height: 18px; }
            .sh-data-nav-copy strong { font-size: .78rem; }
            .sh-data-nav-copy small { display: none; }
            .sh-data-nav-arrow { display: none; }
        }
        @media (max-width: 390px) {
            .sh-data-nav-copy strong { font-size: .72rem; }
            .sh-data-nav a { grid-template-columns: 30px minmax(0, 1fr); gap: 6px; }
            .sh-data-nav-icon { width: 30px; height: 30px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .sh-data-nav a,
            .sh-data-nav a::before,
            .sh-data-nav-arrow { transition: none; }
        }
    </style>
@endonce
