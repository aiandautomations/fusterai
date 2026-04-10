<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $unsubscribed ?? false ? 'Unsubscribed' : 'Unsubscribe' }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f9fafb; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; border-radius: 12px; padding: 40px 48px; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06); }
        h1 { font-size: 20px; font-weight: 600; color: #111; margin: 0 0 8px; }
        p  { font-size: 14px; color: #6b7280; line-height: 1.6; margin: 0 0 24px; }
        .btn { display: inline-block; background: #ef4444; color: #fff; border: none; border-radius: 8px; padding: 10px 24px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; }
        .btn:hover { background: #dc2626; }
        .success { color: #16a34a; font-size: 32px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="card">
        @if($unsubscribed ?? false)
            <div class="success">✓</div>
            <h1>You've been unsubscribed</h1>
            <p>{{ $customer->name }}, you won't receive any more emails from us. You can safely close this page.</p>
        @else
            <h1>Unsubscribe</h1>
            <p>Clicking below will stop all future emails to <strong>{{ $customer->email }}</strong>.</p>
            <form method="POST" action="{{ request()->fullUrl() }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn">Unsubscribe me</button>
            </form>
        @endif
    </div>
</body>
</html>
