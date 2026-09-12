<?php

namespace App\Models;

use Database\Factories\MatchDayFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $match_day_id
 * @property string $name
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MatchDay $matchDay
 */
#[Fillable(['match_day_id', 'name', 'position'])]
class MatchDayField extends Model
{
    /** @use HasFactory<MatchDayFieldFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<MatchDay, $this>
     */
    public function matchDay(): BelongsTo
    {
        return $this->belongsTo(MatchDay::class);
    }
}
