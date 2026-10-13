<?php

namespace Cronqvist\Api\Models;

use Cronqvist\Api\Models\Concerns\SerializesDatesAsZulu;
use Illuminate\Database\Eloquent\Model;

abstract class ApiModel extends Model
{
    use SerializesDatesAsZulu;
}
