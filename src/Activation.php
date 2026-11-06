<?php

declare(strict_types=1);

namespace Consentimental;

use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\ConsentLogRepository;
use Consentimental\Consent\DisclosureSnapshotRepository;
use Consentimental\Consent\Settings;
use Consentimental\Services\CustomServiceRepository;

if (!defined('ABSPATH')) {
	exit;
}

final class Activation
{
	public static function activate(): void
	{
		(new CategoryRepository())->seed_defaults();
		(new CustomServiceRepository())->seed_defaults();
		(new Settings())->seed_defaults();
		(new ConsentLogRepository())->create_table();
		(new DisclosureSnapshotRepository())->create_table();
	}

	public static function deactivate(): void
	{
		wp_clear_scheduled_hook(ConsentLogRepository::CLEANUP_HOOK);
		wp_clear_scheduled_hook(\Consentimental\Diagnostics\ProxyHealth::HOOK);
	}
}
