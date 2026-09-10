@php
    $items = [
        ['route' => 'public-money.index', 'active' => 'public-money.*', 'label' => 'المال العام', 'type' => 'money'],
        ['route' => 'government-tenders.index', 'active' => 'government-tenders.*', 'label' => 'المناقصات الحكومية', 'type' => 'tenders'],
        ['route' => 'financial-status.index', 'active' => 'financial-status.*', 'label' => 'الوضع المالي', 'type' => 'status'],
    ];
@endphp

<div class="sh-data-nav-shell">
    <nav class="sh-data-nav" aria-label="أقسام البيانات العامة">
        @foreach ($items as $item)
            <a
                data-prefetch-page
                href="{{ route($item['route']) }}"
                @class(['is-active' => request()->routeIs($item['active'])])
                @if(request()->routeIs($item['active'])) aria-current="page" @endif
            >
                <span class="sh-data-nav-icon" aria-hidden="true">
                    @if ($item['type'] === 'money')
                        <svg viewBox="0 0 24 24"><path d="M5 7.5C5 6.12 8.13 5 12 5s7 1.12 7 2.5S15.87 10 12 10 5 8.88 5 7.5Zm0 4c0 1.38 3.13 2.5 7 2.5s7-1.12 7-2.5M5 15.5C5 16.88 8.13 18 12 18s7-1.12 7-2.5V7.5M5 7.5v8"/></svg>
                    @elseif ($item['type'] === 'tenders')
                        <svg viewBox="0 0 24 24"><path d="M7 3.5h7l4 4V20H7V3.5Zm7 0v4h4M10 12h5m-5 3h5"/></svg>
                    @else
                        <svg viewBox="0 0 24 24"><path d="M4 18.5h16M6.5 16V11m5 5V6m5 10V9"/></svg>
                    @endif
                </span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>

@once
    <style>
        .sh-data-nav-shell {
            position: relative;
            z-index: 35;
            padding: 10px 16px;
            border-bottom: 1px solid #e1e8e7;
            background: rgba(248, 251, 249, .94);
            backdrop-filter: blur(14px);
        }
        .sh-data-nav {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            width: min(720px, 100%);
            margin-inline: auto;
            padding: 5px;
            border: 1px solid #dce7e3;
            border-radius: 17px;
            background: #fff;
            box-shadow: 0 9px 24px rgba(16, 39, 53, .06);
        }
        .sh-data-nav a {
            display: flex;
            min-width: 0;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 12px;
            color: #596973;
            font-size: 13px;
            font-weight: 900;
            line-height: 1.35;
            text-align: center;
            text-decoration: none;
            transition: color 160ms ease, background 160ms ease, box-shadow 160ms ease, transform 160ms ease;
        }
        .sh-data-nav a:hover {
            color: #0b704f;
            background: #f0f7f3;
        }
        .sh-data-nav a.is-active {
            color: #fff;
            background: linear-gradient(135deg, #087c55, #12656a);
            box-shadow: 0 8px 18px rgba(8, 124, 85, .2);
            transform: translateY(-1px);
        }
        .sh-data-nav-icon {
            display: grid;
            width: 26px;
            height: 26px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 9px;
            color: #087c55;
            background: #e8f5ef;
        }
        .sh-data-nav-icon svg {
            width: 17px;
            height: 17px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .sh-data-nav a.is-active .sh-data-nav-icon {
            color: #fff;
            background: rgba(255, 255, 255, .16);
        }
        @media (max-width: 560px) {
            .sh-data-nav-shell { padding: 8px; }
            .sh-data-nav { gap: 3px; padding: 4px; border-radius: 14px; }
            .sh-data-nav a { min-height: 50px; gap: 5px; padding: 6px 4px; font-size: 10px; }
            .sh-data-nav-icon { width: 23px; height: 23px; border-radius: 7px; }
            .sh-data-nav-icon svg { width: 15px; height: 15px; }
        }
    </style>
@endonce
