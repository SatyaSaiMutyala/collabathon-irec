<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'code'];

    public function states(): HasMany
    {
        return $this->hasMany(State::class);
    }

    /** Every city under this country, for counts without loading two levels of relation. */
    public function cities(): HasManyThrough
    {
        return $this->hasManyThrough(City::class, State::class);
    }


}
