<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ReportSubscription extends Model
{
    protected $fillable = [
        'name',
        'report_type',
        'frequency',
        'send_time',
        'send_day',
        'recipients',
        'is_active',
        'last_sent_at',
    ];

    protected $casts = [
        'recipients'   => 'array',
        'is_active'    => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    /**
     * Resolve all User models that should receive this report.
     *
     * @return \Illuminate\Support\Collection<User>
     */
    public function resolveRecipientUsers(): \Illuminate\Support\Collection
    {
        $ids = collect();

        foreach ($this->recipients ?? [] as $entry) {
            if ($entry['type'] === 'role') {
                $ids = $ids->merge(
                    User::role($entry['value'])->pluck('id')
                );
            } elseif ($entry['type'] === 'user') {
                $ids->push((int) $entry['value']);
            }
        }

        return User::whereIn('id', $ids->unique())->whereNotNull('email')->get();
    }

    /**
     * Determine whether this subscription is due to send right now.
     */
    public function isDue(): bool
    {
        $now = now();

        // Check send_time matches current hour:minute
        [$hour, $minute] = explode(':', $this->send_time);
        if ((int) $now->format('H') !== (int) $hour || (int) $now->format('i') !== (int) $minute) {
            return false;
        }

        return match ($this->frequency) {
            'daily'   => true,
            'weekly'  => (int) $now->format('N') === (int) ($this->send_day ?? 1),
            'monthly' => (int) $now->format('j') === (int) ($this->send_day ?? 1),
            default   => false,
        };
    }
}
