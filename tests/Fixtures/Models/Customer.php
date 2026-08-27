<?php

namespace Imazed\HelpScoutSidebar\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stand-in for the host application's customer model.
 */
class Customer extends Model
{
    protected $table = 'customers';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * The application has already declared this one off-limits.
     *
     * @var array<int, string>
     */
    protected $hidden = ['private_note'];

    /**
     * @var array<string, string>
     */
    protected $casts = ['is_active' => 'boolean'];

    /**
     * A collection-shaped value, for the `list` item type.
     *
     * @return array<int, array{when: string, summary: string}>
     */
    public function getEventsAttribute(): array
    {
        return [
            ['when' => '9 days ago', 'summary' => 'Payment failed'],
            ['when' => '4 days ago', 'summary' => 'Payment retried'],
            ['when' => '2 days ago', 'summary' => 'Removed 3 users'],
        ];
    }
}
