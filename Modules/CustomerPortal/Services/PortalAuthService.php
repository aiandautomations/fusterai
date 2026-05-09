<?php

namespace Modules\CustomerPortal\Services;

use App\Domains\Customer\Models\Customer;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\CustomerPortal\Notifications\MagicLinkNotification;

class PortalAuthService
{
    public function sendMagicLink(Workspace $workspace, string $email): void
    {
        $customer = Customer::resolveOrCreate($workspace->id, $email);

        DB::table('portal_login_tokens')
            ->where('customer_id', $customer->id)
            ->delete();

        $plain = Str::random(64);

        DB::table('portal_login_tokens')->insert([
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
            'token' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes(60),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::send($customer, new MagicLinkNotification($workspace, $plain));
    }

    public function verify(Workspace $workspace, string $plain): ?Customer
    {
        $record = DB::table('portal_login_tokens')
            ->where('workspace_id', $workspace->id)
            ->where('token', hash('sha256', $plain))
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return null;
        }

        DB::table('portal_login_tokens')->where('id', $record->id)->delete();

        return Customer::find($record->customer_id);
    }
}
