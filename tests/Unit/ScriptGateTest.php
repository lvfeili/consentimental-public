<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Scripts\ScriptGate;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class ScriptGateTest extends TestCase
{
    private ServiceRegistry $services;
    private ScriptGate $gate;

    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];
        $GLOBALS['cmp_test_hooks'] = [];

        $this->services = new ServiceRegistry(new CustomServiceRepository());
        $this->services->register(['id' => 'google-analytics', 'category' => 'analytics']);

        $this->gate = new ScriptGate($this->services);
        $this->gate->register_hooks();
    }

    public function test_registered_handle_is_neutralized_by_category(): void
    {
        $this->gate->register('my-analytics-script', 'analytics');

        $attributes = apply_filters('wp_script_attributes', ['id' => 'my-analytics-script-js', 'src' => 'https://example.com/a.js']);

        self::assertSame('text/plain', $attributes['type']);
        self::assertSame('analytics', $attributes['data-cmp-category']);
        self::assertArrayNotHasKey('data-cmp-service', $attributes);
    }

    public function test_registered_handle_by_service_id_tags_both_category_and_service(): void
    {
        $this->gate->register('ga-handle', 'google-analytics');

        $attributes = apply_filters('wp_script_attributes', ['id' => 'ga-handle-js', 'src' => 'https://example.com/a.js']);

        self::assertSame('text/plain', $attributes['type']);
        self::assertSame('analytics', $attributes['data-cmp-category']);
        self::assertSame('google-analytics', $attributes['data-cmp-service']);
    }

    public function test_unregistered_handle_is_left_untouched(): void
    {
        $original = ['id' => 'jquery-js', 'src' => 'https://example.com/jquery.js'];

        $attributes = apply_filters('wp_script_attributes', $original);

        self::assertSame($original, $attributes);
    }

    public function test_inline_script_before_and_after_positions_are_both_gated(): void
    {
        $this->gate->register('my-handle', 'marketing');

        $before = apply_filters('wp_inline_script_attributes', ['id' => 'my-handle-js-before'], 'console.log(1)');
        $after = apply_filters('wp_inline_script_attributes', ['id' => 'my-handle-js-after'], 'console.log(2)');

        self::assertSame('text/plain', $before['type']);
        self::assertSame('marketing', $before['data-cmp-category']);
        self::assertSame('text/plain', $after['type']);
        self::assertSame('marketing', $after['data-cmp-category']);
    }

    public function test_a_module_scripts_original_type_is_preserved_for_release(): void
    {
        $this->gate->register('my-module', 'analytics');

        $attributes = apply_filters('wp_script_attributes', [
            'id' => 'my-module-js',
            'src' => 'https://example.com/a.js',
            'type' => 'module',
        ]);

        self::assertSame('text/plain', $attributes['type']);
        self::assertSame('module', $attributes['data-cmp-original-type']);
    }

    public function test_attributes_without_an_id_are_left_untouched(): void
    {
        $original = ['src' => 'https://example.com/a.js'];

        self::assertSame($original, apply_filters('wp_script_attributes', $original));
    }
}
