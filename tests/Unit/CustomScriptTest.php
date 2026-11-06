<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Scripts\CustomScript;
use PHPUnit\Framework\TestCase;

final class CustomScriptTest extends TestCase
{
    public function test_round_trips_through_array(): void
    {
        $script = new CustomScript(
            id: 'abc',
            name: 'My Script',
            type: CustomScript::TYPE_URL,
            url: 'https://example.test/a.js',
            code: '',
            attributes: 'data-website-id="abc"',
            proxy: true,
            proxy_api_origin: 'https://www.google-analytics.com',
            placement: CustomScript::PLACEMENT_FOOTER,
            category: 'marketing',
            service: 'meta-pixel',
            condition_type: CustomScript::CONDITION_WHOLE_SITE,
            condition_value: '',
        );

        $restored = CustomScript::from_array($script->to_array());

        self::assertEquals($script->to_array(), $restored->to_array());
    }

    public function test_invalid_placement_falls_back_to_footer(): void
    {
        $script = CustomScript::from_array(['id' => 'x', 'category' => 'marketing', 'placement' => 'nonsense']);

        self::assertSame(CustomScript::PLACEMENT_FOOTER, $script->placement);
    }

    public function test_invalid_condition_type_falls_back_to_whole_site(): void
    {
        $script = CustomScript::from_array(['id' => 'x', 'category' => 'marketing', 'condition_type' => 'nonsense']);

        self::assertSame(CustomScript::CONDITION_WHOLE_SITE, $script->condition_type);
    }

    public function test_invalid_type_falls_back_to_url(): void
    {
        $script = CustomScript::from_array(['id' => 'x', 'category' => 'marketing', 'type' => 'nonsense']);

        self::assertSame(CustomScript::TYPE_URL, $script->type);
    }
}
