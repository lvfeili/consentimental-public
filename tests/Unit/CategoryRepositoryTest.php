<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Consent\Category;
use Consentimental\Consent\CategoryRepository;
use PHPUnit\Framework\TestCase;

final class CategoryRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];
    }

    public function test_seed_defaults_creates_four_default_categories(): void
    {
        $repository = new CategoryRepository();
        $repository->seed_defaults();

        $categories = $repository->all();

        self::assertCount(4, $categories);
        self::assertSame(
            ['necessary', 'functional', 'analytics', 'marketing'],
            array_map(static fn (Category $category): string => $category->slug, $categories),
        );
    }

    public function test_necessary_category_is_required_and_enabled_by_default(): void
    {
        $repository = new CategoryRepository();
        $repository->seed_defaults();

        $necessary = $repository->find('necessary');

        self::assertNotNull($necessary);
        self::assertTrue($necessary->required);
        self::assertTrue($necessary->default_enabled);
    }

    public function test_optional_categories_default_to_disabled(): void
    {
        $repository = new CategoryRepository();
        $repository->seed_defaults();

        foreach (['functional', 'analytics', 'marketing'] as $slug) {
            $category = $repository->find($slug);

            self::assertNotNull($category);
            self::assertFalse($category->required);
            self::assertFalse($category->default_enabled);
        }
    }

    public function test_category_required_flag_cannot_be_overridden_to_disabled(): void
    {
        $tampered = Category::from_array([
            'slug' => 'necessary',
            'label' => 'Necessary',
            'required' => true,
            'default_enabled' => false,
        ]);

        self::assertTrue($tampered->default_enabled);
    }

    public function test_save_persists_category_order_and_relabeling(): void
    {
        $repository = new CategoryRepository();
        $repository->seed_defaults();

        $categories = $repository->all();
        $categories[1]->label = 'Renamed';
        $repository->save($categories);

        $reloaded = (new CategoryRepository())->find('functional');

        self::assertSame('Renamed', $reloaded->label);
    }
}
