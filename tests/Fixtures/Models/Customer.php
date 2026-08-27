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
}
