<?php

namespace FakeIpastore\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * FakeIpastore\Models\SignRequest
 *
 * @property int $id
 * @property string|null $udid
 * @property string|null $icon
 * @property string|null $bid
 * @property string|null $name
 * @property string|null $aid
 * @property string|null $cert
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereAid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereBid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereCert($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereUdid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\FakeIpastore\Models\SignRequest whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SignRequest extends Model
{
    const STATUS_NEW = 0;
    const STATUS_REQUEST_SENT = 1;
    const STATUS_DOWNLOADED = 2;
    const STATUS_SIGNING = 3;
    const STATUS_DONE = 100;

    protected $guarded = [];
}
