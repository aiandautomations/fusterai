<?php

namespace App\Http\Controllers;

use App\Domains\Conversation\Models\Thread;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    private const GIF = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x00\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";

    public function pixel(string $token): Response
    {
        $thread = Thread::where('tracking_token', $token)->first();

        if ($thread && $thread->opened_at === null) {
            $thread->update(['opened_at' => now()]);
        }

        return response(self::GIF, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
