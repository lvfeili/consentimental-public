<?php

declare(strict_types=1);
namespace Consentimental\Tests;
use Consentimental\Scripts\ProxyRoutes;
use Consentimental\Scripts\CustomScript;
use Consentimental\Scripts\CustomScriptRepository;
use Consentimental\Scripts\ScriptProxy;
use PHPUnit\Framework\TestCase;

final class ProxyRoutesTest extends TestCase
{
    public function test_explicit_routes_are_exact_and_deduplicated(): void
    {
        self::assertSame(['stats/collect' => ['POST', 'GET']], ProxyRoutes::parse("POST /stats/collect\nGET /stats/collect\nPOST /stats/collect"));
        self::assertSame([], ProxyRoutes::parse(''));
    }
    public function test_unsafe_or_ambiguous_routes_are_rejected(): void
    {
        foreach (['POST https://example.com/send', 'GET /*', 'GET /../send', 'GET /%2e/send', 'DELETE /send', 'GET /send?x=1', 'GET //send'] as $route) {
            try { ProxyRoutes::parse($route); self::fail('Accepted unsafe route: ' . $route); }
            catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
    }
    public function test_new_scripts_do_not_infer_vendor_endpoints(): void
    {
        $script = CustomScript::from_array(['id' => 'x', 'proxy' => true, 'url' => 'https://example.com/script.js']);
        self::assertSame('', $script->proxy_paths);
        self::assertFalse(ScriptProxy::allows_request($script, 'api/send', 'POST'));
        self::assertTrue(ScriptProxy::allows_request($script, 'script.js', 'GET'));
    }
    public function test_existing_saved_scripts_keep_old_rules_and_can_explicitly_clear_them(): void
    {
        $GLOBALS['cmp_test_options']['cmp_legacy_test'] = [['id' => 'x', 'proxy' => true, 'url' => 'https://example.com/script.js']];
        $repo = new CustomScriptRepository('cmp_legacy_test');
        $script = $repo->get('x');
        self::assertSame('POST /api/send', $script->proxy_paths);
        $script->proxy_paths = '';
        $repo->save($script);
        self::assertSame('', $repo->get('x')->proxy_paths);
        unset($GLOBALS['cmp_test_options']['cmp_legacy_test']);
    }
}
