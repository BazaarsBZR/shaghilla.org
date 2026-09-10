<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>الوضع المالي | شغيلة</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>@include('pages.financial-status._styles')</style>
</head>
<body class="financial-status-shell">
    <x-site.header />
    @yield('content')
    @includeIf('components.site.footer')
</body>
</html>
