<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use PHPUnit\Framework\TestCase;

final class PrivacySettingsLinkTest extends TestCase
{
    public function test_default_link_is_a_button_with_open_settings_attribute(): void
    {
        $html = \cmp_privacy_settings_link();

        self::assertStringContainsString('<button', $html);
        self::assertStringContainsString('data-cmp-open-settings', $html);
        self::assertStringContainsString('Privacy settings', $html);
    }

    public function test_custom_text_and_class_are_escaped_and_used(): void
    {
        $html = \cmp_privacy_settings_link(['text' => 'Cookie settings', 'class' => 'my-class']);

        self::assertStringContainsString('>Cookie settings<', $html);
        self::assertStringContainsString('class="my-class"', $html);
    }

    public function test_text_is_escaped_against_markup_injection(): void
    {
        $html = \cmp_privacy_settings_link(['text' => '<script>alert(1)</script>']);

        self::assertStringNotContainsString('<script>', $html);
    }
}
