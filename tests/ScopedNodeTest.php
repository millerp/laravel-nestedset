<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Kalnoy\Nestedset\NestedSet;
use PHPUnit\Framework\TestCase;

class ScopedNodeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $schema = Capsule::schema();

        $schema->dropIfExists('menu_items');

        Capsule::disableQueryLog();

        $schema->create('menu_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('menu_id');
            $table->string('title')->nullable();
            NestedSet::columns($table);
        });

        Capsule::enableQueryLog();
    }

    protected function setUp(): void
    {
        $data = include __DIR__.'/data/menu_items.php';

        Capsule::table('menu_items')->insert($data);

        Capsule::flushQueryLog();

        MenuItem::resetActionsPerformed();

        date_default_timezone_set('America/Denver');
    }

    protected function tearDown(): void
    {
        Capsule::table('menu_items')->truncate();
    }

    public function assertTreeNotBroken($menuId)
    {
        $this->assertFalse(MenuItem::scoped(['menu_id' => $menuId])->isBroken());
    }

    public function test_not_broken()
    {
        $this->assertTreeNotBroken(1);
        $this->assertTreeNotBroken(2);
    }

    public function test_moving_node_not_affecting_other_menu()
    {
        $node = MenuItem::where('menu_id', '=', 1)->first();

        $node->down();

        $node = MenuItem::where('menu_id', '=', 2)->first();

        $this->assertEquals(1, $node->getLft());
    }

    public function test_scoped()
    {
        $node = MenuItem::scoped(['menu_id' => 2])->first();

        $this->assertEquals(3, $node->getKey());
    }

    public function test_siblings()
    {
        $node = MenuItem::find(1);

        $result = $node->getSiblings();

        $this->assertEquals(1, $result->count());
        $this->assertEquals(2, $result->first()->getKey());

        $result = $node->getNextSiblings();

        $this->assertEquals(2, $result->first()->getKey());

        $node = MenuItem::find(2);

        $result = $node->getPrevSiblings();

        $this->assertEquals(1, $result->first()->getKey());
    }

    public function test_descendants()
    {
        $node = MenuItem::find(2);

        $result = $node->getDescendants();

        $this->assertEquals(1, $result->count());
        $this->assertEquals(5, $result->first()->getKey());

        $node = MenuItem::scoped(['menu_id' => 1])->with('descendants')->find(2);

        $result = $node->descendants;

        $this->assertEquals(1, $result->count());
        $this->assertEquals(5, $result->first()->getKey());
    }

    public function test_ancestors()
    {
        $node = MenuItem::find(5);

        $result = $node->getAncestors();

        $this->assertEquals(1, $result->count());
        $this->assertEquals(2, $result->first()->getKey());

        $node = MenuItem::scoped(['menu_id' => 1])->with('ancestors')->find(5);

        $result = $node->ancestors;

        $this->assertEquals(1, $result->count());
        $this->assertEquals(2, $result->first()->getKey());
    }

    public function test_depth()
    {
        $node = MenuItem::scoped(['menu_id' => 1])->withDepth()->where('id', '=', 5)->first();

        $this->assertEquals(1, $node->depth);

        $node = MenuItem::find(2);

        $result = $node->children()->withDepth()->get();

        $this->assertEquals(1, $result->first()->depth);
    }

    public function test_save_as_root()
    {
        $node = MenuItem::find(5);

        $node->saveAsRoot();

        $this->assertEquals(5, $node->getLft());
        $this->assertEquals(null, $node->parent_id);

        $this->assertOtherScopeNotAffected();
    }

    public function test_insertion()
    {
        $node = MenuItem::create(['menu_id' => 1, 'parent_id' => 5]);

        $this->assertEquals(5, $node->parent_id);
        $this->assertEquals(5, $node->getLft());

        $this->assertOtherScopeNotAffected();
    }

    public function test_insertion_to_parent_from_other_scope()
    {
        $this->expectException(ModelNotFoundException::class);

        $node = MenuItem::create(['menu_id' => 2, 'parent_id' => 5]);
    }

    public function test_deletion()
    {
        $node = MenuItem::find(2)->delete();

        $node = MenuItem::find(1);

        $this->assertEquals(2, $node->getRgt());

        $this->assertOtherScopeNotAffected();
    }

    public function test_moving()
    {
        $node = MenuItem::find(1);
        $this->assertTrue($node->down());

        $this->assertOtherScopeNotAffected();
    }

    protected function assertOtherScopeNotAffected()
    {
        $node = MenuItem::find(3);

        $this->assertEquals(1, $node->getLft());
    }

    // Commented, cause there is no assertion here and otherwise the test is marked as risky in PHPUnit 7.
    // What's the purpose of this method? @todo: remove/update?
    /*public function testRebuildsTree()
    {
        $data = [];
        MenuItem::scoped([ 'menu_id' => 2 ])->rebuildTree($data);
    }*/

    public function test_appending_to_another_scope_fails()
    {
        $this->expectException(LogicException::class);

        $a = MenuItem::find(1);
        $b = MenuItem::find(3);

        $a->appendToNode($b)->save();
    }

    public function test_inserting_before_another_scope_fails()
    {
        $this->expectException(LogicException::class);

        $a = MenuItem::find(1);
        $b = MenuItem::find(3);

        $a->insertAfterNode($b);
    }

    public function test_eager_loading_ancestors_with_scope()
    {
        $filteredNodes = MenuItem::where('title', 'menu item 3')->with(['ancestors'])->get();

        $this->assertEquals(2, $filteredNodes->find(5)->ancestors[0]->id);
        $this->assertEquals(4, $filteredNodes->find(6)->ancestors[0]->id);
    }

    public function test_eager_loading_descendants_with_scope()
    {
        $filteredNodes = MenuItem::where('title', 'menu item 2')->with(['descendants'])->get();

        $this->assertEquals(5, $filteredNodes->find(2)->descendants[0]->id);
        $this->assertEquals(6, $filteredNodes->find(4)->descendants[0]->id);
    }
}
