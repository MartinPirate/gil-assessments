<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $document_type
 * @property string $series
 * @property int $next_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|DocumentSeries newModelQuery()
 * @method static Builder<static>|DocumentSeries newQuery()
 * @method static Builder<static>|DocumentSeries query()
 * @method static Builder<static>|DocumentSeries whereCreatedAt($value)
 * @method static Builder<static>|DocumentSeries whereDocumentType($value)
 * @method static Builder<static>|DocumentSeries whereId($value)
 * @method static Builder<static>|DocumentSeries whereNextNumber($value)
 * @method static Builder<static>|DocumentSeries whereSeries($value)
 * @method static Builder<static>|DocumentSeries whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class DocumentSeries extends Model
{
    protected $table = 'document_series';

    protected $fillable = ['document_type', 'series', 'next_number'];
}
