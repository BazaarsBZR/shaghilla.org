<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>الوضع المالي | شغيلة</title>
    @include('components.site.social-meta', [
        'socialTitle' => 'الوضع المالي | رابطة الشغيلة',
        'socialDescription' => 'بيانات الوضع المالي اللبناني في عرض عربي واضح ومحدّث من رابطة الشغيلة.',
    ])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>@include('pages.financial-status._styles')</style>
</head>
<body class="financial-status-shell">
    <x-site.header />
    <x-site.data-navigation />
    @yield('content')
    @includeIf('components.site.footer')
</body>
</html>
