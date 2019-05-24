<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DomainFeeder extends Model
{
    protected $connection = "domain_feeder";
    protected $table      = "domain_feeder";
    public $timestamps    = false;
}
