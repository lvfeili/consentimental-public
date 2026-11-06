<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Scripts\CustomScript;
use Consentimental\Scripts\CustomScriptRepository;
use PHPUnit\Framework\TestCase;

final class CustomScriptRepositoryTest extends TestCase
{
    private CustomScriptRepository $repo;

    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];
        $this->repo = new CustomScriptRepository();
    }

    private function script(string $id, string $name = 'Script'): CustomScript
    {
        return CustomScript::from_array(['id' => $id, 'name' => $name, 'category' => 'marketing']);
    }

    public function test_save_adds_a_new_script(): void
    {
        $this->repo->save($this->script('s1'));

        self::assertCount(1, $this->repo->all());
        self::assertSame('Script', $this->repo->get('s1')->name);
    }

    public function test_save_replaces_an_existing_script(): void
    {
        $this->repo->save($this->script('s1', 'First'));
        $this->repo->save($this->script('s1', 'Second'));

        self::assertCount(1, $this->repo->all());
        self::assertSame('Second', $this->repo->get('s1')->name);
    }

    public function test_delete(): void
    {
        $this->repo->save($this->script('s1'));
        $this->repo->save($this->script('s2'));
        $this->repo->delete('s1');

        self::assertNull($this->repo->get('s1'));
        self::assertNotNull($this->repo->get('s2'));
    }
}
