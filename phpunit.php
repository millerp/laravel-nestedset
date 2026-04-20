<?php

use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;

include __DIR__.'/vendor/autoload.php';

$capsule = new Manager;
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'prfx_']);
$capsule->setEventDispatcher(new Dispatcher);
$capsule->bootEloquent();
$capsule->setAsGlobal();

include __DIR__.'/tests/models/Category.php';
include __DIR__.'/tests/models/MenuItem.php';
