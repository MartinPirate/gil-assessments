<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSeries extends Model
{
    protected $table = 'document_series';

    protected $fillable = ['document_type', 'series', 'next_number'];
}
