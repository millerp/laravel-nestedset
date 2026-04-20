<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use \Kalnoy\Nestedset\NodeTrait, SoftDeletes;

    protected $fillable = ['name', 'parent_id'];

    public $timestamps = false;

    public static function resetActionsPerformed()
    {
        static::$actionsPerformed = 0;
    }
}
