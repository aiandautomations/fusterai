<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>How did we do?</title>
<style>
  body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .wrapper { max-width: 520px; margin: 40px auto; padding: 0 16px; }
  .card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
  .header { background: #18181b; padding: 28px 32px; }
  .header h1 { margin: 0; color: #fff; font-size: 18px; font-weight: 600; }
  .body { padding: 32px; text-align: center; }
  .body p { margin: 0 0 8px; color: #52525b; font-size: 15px; line-height: 1.5; }
  .subject { font-size: 13px; color: #a1a1aa; margin-bottom: 28px !important; }
  .buttons { display: flex; gap: 16px; justify-content: center; margin: 28px 0; }
  .btn { display: inline-block; padding: 14px 32px; border-radius: 8px; font-size: 22px; text-decoration: none; border: 2px solid transparent; transition: opacity .15s; }
  .btn-good { background: #f0fdf4; border-color: #86efac; }
  .btn-bad  { background: #fef2f2; border-color: #fca5a5; }
  .footer { padding: 20px 32px; border-top: 1px solid #f4f4f5; text-align: center; }
  .footer p { margin: 0; font-size: 12px; color: #a1a1aa; }
</style>
</head>
<body>
  <div class="wrapper">
    <div class="card">
      <div class="header">
        <h1>{{ $conversation->mailbox?->name ?? config('app.name') }} Support</h1>
      </div>
      <div class="body">
        <p>Hi {{ $conversation->customer?->name ?? 'there' }},</p>
        <p>Your support request has been resolved. We'd love to know how we did!</p>
        <p class="subject">Re: {{ $conversation->subject }}</p>

        <div class="buttons">
          <a href="{{ $goodUrl }}" class="btn btn-good">👍 Good</a>
          <a href="{{ $badUrl }}"  class="btn btn-bad">👎 Bad</a>
        </div>

        <p style="font-size:13px;color:#a1a1aa;">This link expires in 7 days.</p>
      </div>
      <div class="footer">
        <p>Powered by <a href="{{ config('app.url') }}" style="color:#a1a1aa;">{{ config('app.name') }}</a></p>
      </div>
    </div>
  </div>
</body>
</html>
