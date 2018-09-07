<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ChessTable extends Model
{
    protected $table = 'chess_table';
    public $timestamps = false;

    public function blackUserInfo()
    {
        return $this->hasOne('App\User', 'id', 'user_black');
    }

    public function whiteUserInfo()
    {
        return $this->hasOne('App\User', 'id', 'user_white');
    }
}
