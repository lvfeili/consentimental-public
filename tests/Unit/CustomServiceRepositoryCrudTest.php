<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\Service;
use PHPUnit\Framework\TestCase;

final class CustomServiceRepositoryCrudTest extends TestCase
{
    private CustomServiceRepository $repo;

    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];
        $this->repo = new CustomServiceRepository();
    }

    public function test_save_one_adds_a_new_service(): void
    {
        $this->repo->save_one(Service::from_array(['id' => 'my-tracker', 'name' => 'My Tracker', 'category' => 'analytics']));

        $found = $this->repo->find('my-tracker');
        self::assertNotNull($found);
        self::assertSame('My Tracker', $found->name);
    }

    public function test_save_one_replaces_an_existing_service_with_the_same_id(): void
    {
        $this->repo->save_one(Service::from_array(['id' => 'my-tracker', 'name' => 'Old Name', 'category' => 'analytics']));
        $this->repo->save_one(Service::from_array(['id' => 'my-tracker', 'name' => 'New Name', 'category' => 'marketing']));

        $all = $this->repo->all();
        self::assertCount(1, $all);
        self::assertSame('New Name', $this->repo->find('my-tracker')->name);
        self::assertSame('marketing', $this->repo->find('my-tracker')->category);
    }

    public function test_delete_removes_only_the_targeted_service(): void
    {
        $this->repo->save_one(Service::from_array(['id' => 'keep-me', 'category' => 'analytics']));
        $this->repo->save_one(Service::from_array(['id' => 'remove-me', 'category' => 'analytics']));

        $this->repo->delete('remove-me');

        self::assertNull($this->repo->find('remove-me'));
        self::assertNotNull($this->repo->find('keep-me'));
    }

    public function test_find_returns_null_for_unknown_id(): void
    {
        self::assertNull($this->repo->find('nope'));
    }
}
