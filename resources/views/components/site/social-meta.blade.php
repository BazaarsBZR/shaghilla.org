@php
    $socialTitle = trim((string) ($socialTitle ?? 'رابطة الشغيلة | أخبار لبنان'));
    $socialDescription = trim((string) ($socialDescription ?? 'صوت الناس وأخبار لبنان في منصة عربية واضحة، سريعة ومتجددة.'));
    $socialUrl = $socialUrl ?? request()->url();
    $socialImage = 'https://shaghilla.org/social-preview.png';
@endphp

<meta name="description" content="{{ $socialDescription }}" />
<link rel="canonical" href="{{ $socialUrl }}" />
<meta property="og:locale" content="ar_LB" />
<meta property="og:type" content="website" />
<meta property="og:site_name" content="رابطة الشغيلة" />
<meta property="og:title" content="{{ $socialTitle }}" />
<meta property="og:description" content="{{ $socialDescription }}" />
<meta property="og:url" content="{{ $socialUrl }}" />
<meta property="og:image" content="{{ $socialImage }}" />
<meta property="og:image:secure_url" content="{{ $socialImage }}" />
<meta property="og:image:type" content="image/png" />
<meta property="og:image:width" content="1200" />
<meta property="og:image:height" content="630" />
<meta property="og:image:alt" content="شعار رابطة الشغيلة" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $socialTitle }}" />
<meta name="twitter:description" content="{{ $socialDescription }}" />
<meta name="twitter:image" content="{{ $socialImage }}" />

