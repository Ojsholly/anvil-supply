<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — Anvil Supply</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:ui-sans-serif,system-ui,sans-serif; background:#0B0E12;
               min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .card { width:380px; background:#0F141B; border:1px solid #22303F;
                border-radius:14px; padding:36px 32px; box-shadow:0 24px 60px rgba(0,0,0,.55); }
        .brand { font-family:ui-monospace,monospace; font-size:13px; letter-spacing:.14em;
                 color:#38BDF8; text-transform:uppercase; margin-bottom:8px; }
        h1 { font-size:22px; font-weight:700; color:#E6EDF5; margin-bottom:24px; }
        label { display:block; font-size:13px; color:#7A8AA0; margin-bottom:6px; }
        input { width:100%; padding:10px 14px; border-radius:9px; border:1px solid #22303F;
                background:#141B24; color:#E6EDF5; font-size:15px; margin-bottom:18px; outline:none; }
        input:focus { border-color:#38BDF8; }
        button { width:100%; padding:11px; border:none; border-radius:9px; cursor:pointer;
                 background:#38BDF8; color:#0B0E12; font-size:15px; font-weight:700; }
        .err { color:#F87171; font-size:13px; margin:-10px 0 14px; }
    </style>
</head>
<body>
    <form class="card" method="POST" action="{{ route('login') }}">
        @csrf
        <div class="brand">Anvil Supply Co.</div>
        <h1>Sign in to authorize access</h1>
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email')<div class="err">{{ $message }}</div>@enderror
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>
        <button type="submit">Sign in</button>
    </form>
</body>
</html>
