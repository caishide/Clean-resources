<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Plan - 会员计划模型
 *
 * 管理系统的会员订阅计划
 */
class Plan extends Model
{
    use GlobalStatus;
}
