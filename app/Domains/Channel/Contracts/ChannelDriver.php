<?php

namespace App\Domains\Channel\Contracts;

use App\Domains\Conversation\Models\Thread;

interface ChannelDriver
{
    public function send(Thread $thread): void;
}
