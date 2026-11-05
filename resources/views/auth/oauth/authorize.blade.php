<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorize — Anvil Supply</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:ui-sans-serif,system-ui,sans-serif; background:#0B0E12;
               min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .card { width:420px; background:#0F141B; border:1px solid #22303F;
                border-radius:14px; padding:36px 32px; box-shadow:0 24px 60px rgba(0,0,0,.55); }
        .brand { font-family:ui-monospace,monospace; font-size:13px; letter-spacing:.14em;
                 color:#38BDF8; text-transform:uppercase; margin-bottom:8px; }
        h1 { font-size:22px; font-weight:700; color:#E6EDF5; margin-bottom:6px; }
        .sub { font-size:14px; color:#7A8AA0; margin-bottom:22px; }
        .sub strong { color:#E6EDF5; font-weight:600; }
        .scopes { background:#141B24; border:1px solid #22303F; border-radius:9px;
                  padding:14px 16px; margin-bottom:26px; }
        .scopes .label { font-size:11px; letter-spacing:.1em; text-transform:uppercase;
                         color:#7A8AA0; margin-bottom:8px; }
        .scope { font-family:ui-monospace,monospace; font-size:14px; color:#A7F3D0; }
        .row { display:flex; gap:12px; }
        button { flex:1; padding:11px; border:none; border-radius:9px; cursor:pointer;
                 font-size:15px; font-weight:700; }
        .yes { background:#38BDF8; color:#0B0E12; }
        .no { background:transparent; color:#7A8AA0; border:1px solid #22303F; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">Anvil Supply Co.</div>
        <h1>Authorization request</h1>
        <p class="sub"><strong>{{ $client->name ?? $client->id }}</strong> wants to access your account.</p>

        <div class="scopes">
            <div class="label">Requested permissions</div>
            @foreach ($scopes as $scope)
                <div class="scope">{{ $scope->id }} — {{ $scope->description }}</div>
            @endforeach
        </div>

        <div class="row">
            <form method="POST" action="/oauth/authorize">
                @csrf
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="yes">Authorize</button>
            </form>
            <form method="POST" action="/oauth/authorize">
                @csrf
                @method('DELETE')
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="no">Deny</button>
            </form>
        </div>
    </div>
</body>
</html>
