<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ConversationRead extends Model
{
    public $timestamps    = false;
    public $incrementing  = false;

    protected $table    = 'conversation_reads';
    protected $keyType  = 'string';
    protected $fillable = ['user_id', 'conversation_id', 'last_read_at'];
    protected $casts    = ['last_read_at' => 'datetime'];

    /**
     * Upsert a read record — avoids Eloquent's auto-increment assumption on
     * a composite-PK table.
     */
    public static function markRead(int $userId, int $conversationId): void
    {
        DB::table('conversation_reads')->upsert(
            [['user_id' => $userId, 'conversation_id' => $conversationId, 'last_read_at' => now()]],
            ['user_id', 'conversation_id'],
            ['last_read_at'],
        );
    }
}
