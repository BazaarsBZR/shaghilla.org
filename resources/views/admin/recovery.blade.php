<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Restore admin access | Shaghilla</title>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f4f7f6; color: #102b35; }
        main { width: min(100%, 520px); padding: 36px; border: 1px solid #d9e5e1; border-radius: 28px; background: #fff; box-shadow: 0 24px 70px rgba(16, 43, 53, .12); }
        img { display: block; width: 96px; height: auto; margin: 0 auto 18px; }
        h1 { margin: 0; text-align: center; font-size: clamp(28px, 5vw, 40px); }
        .email { margin: 12px 0 28px; text-align: center; color: #657a82; }
        label { display: block; margin: 18px 0 8px; font-weight: 800; }
        input { width: 100%; min-height: 54px; border: 1px solid #cbd9d5; border-radius: 14px; padding: 12px 15px; font: inherit; outline: none; }
        input:focus { border-color: #0d8b62; box-shadow: 0 0 0 4px rgba(13, 139, 98, .12); }
        button { width: 100%; min-height: 56px; margin-top: 24px; border: 0; border-radius: 15px; background: #df2528; color: #fff; font: inherit; font-weight: 900; cursor: pointer; }
        .error { margin-top: 16px; padding: 12px 14px; border-radius: 12px; background: #fff0f0; color: #a51f27; }
        .note { margin: 18px 0 0; color: #657a82; font-size: 14px; line-height: 1.6; }
    </style>
</head>
<body>
<main>
    <img src="{{ asset('website-logo.png') }}" alt="Shaghilla">
    <h1>Restore admin access</h1>
    <p class="email">{{ $email }}</p>

    <form method="post" action="{{ route('admin.recovery.update', $token) }}">
        @csrf
        <label for="password">Existing admin password</label>
        <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password" autofocus>

        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password">

        @error('password')
            <div class="error">{{ $message }}</div>
        @enderror

        <button type="submit">Restore admin access</button>
    </form>

    <p class="note">This temporary recovery page updates only the administrator account and will be disabled immediately afterward.</p>
</main>
</body>
</html>
