<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? 'المناقصات الحكومية' }} | شغيلة</title>
    @include('components.site.social-meta', [
        'socialTitle' => ($pageTitle ?? 'المناقصات الحكومية').' | رابطة الشغيلة',
        'socialDescription' => 'تابع المناقصات الحكومية في لبنان ومواعيدها وتفاصيل المشاركة عبر رابطة الشغيلة.',
    ])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>@include('pages.government-tenders._styles')</style>
</head>
<body class="government-tenders-shell">
    <x-site.header />
    <x-site.data-navigation />
    @yield('content')
    @includeIf('components.site.footer')
</body>
</html>
