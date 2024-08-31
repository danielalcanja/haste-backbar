<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CmsnAgent extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public function transaction()
    {
        return $this->belongsTo(\App\Transaction::class,'transaction_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
}
