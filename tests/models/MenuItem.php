<?php

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;

class MenuItem extends Model
{
    use NodeTrait;

    public $timestamps = false;

    protected $fillable = ['menu_id', 'parent_id'];

    public static function resetActionsPerformed()
    {
        static::$actionsPerformed = 0;
    }

    protected function getScopeAttributes()
    {
        return ['menu_id'];
    }
}
