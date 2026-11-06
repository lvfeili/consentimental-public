<?php

declare(strict_types=1);

namespace Consentimental;

use Consentimental\Admin\AdminMenu;
use Consentimental\Admin\BannerPage;
use Consentimental\Admin\CategoriesPage;
use Consentimental\Admin\ConsentLogPage;
use Consentimental\Admin\ProxyHealthPage;
use Consentimental\Admin\PublishPage;
use Consentimental\Admin\ScannerPage;
use Consentimental\Admin\ScriptsPage;
use Consentimental\Admin\ServicesPage;
use Consentimental\Admin\SetupPage;
use Consentimental\Admin\StatusPage;
use Consentimental\Api\ConsentController;
use Consentimental\Blocking\DomRewriter;
use Consentimental\Blocking\OutputBuffer;
use Consentimental\Consent\CachePurger;
use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\ConsentLogRepository;
use Consentimental\Consent\ConsentManager;
use Consentimental\Consent\ConsentRecorder;
use Consentimental\Consent\DisclosureRecorder;
use Consentimental\Consent\DisclosureSnapshotRepository;
use Consentimental\Consent\DraftManager;
use Consentimental\Consent\Settings;
use Consentimental\Diagnostics\DebugPanel;
use Consentimental\Diagnostics\ProxyHealth;
use Consentimental\Diagnostics\SiteScanner;
use Consentimental\Diagnostics\StatusReport;
use Consentimental\Diagnostics\VerificationLog;
use Consentimental\Frontend\Banner;
use Consentimental\Frontend\BannerContent;
use Consentimental\Frontend\BlockingAssets;
use Consentimental\Frontend\ConsentBootstrap;
use Consentimental\Frontend\ServicesBlock;
use Consentimental\Frontend\ServicesShortcode;
use Consentimental\Frontend\Shortcodes;
use Consentimental\Integrations\ConsentMode;
use Consentimental\Integrations\GoogleServices;
use Consentimental\Integrations\MultilingualStrings;
use Consentimental\Scripts\CustomScriptRepository;
use Consentimental\Scripts\ScriptGate;
use Consentimental\Scripts\ScriptManager;
use Consentimental\Scripts\ScriptProxy;
use Consentimental\Services\BuiltInServiceLibrary;
use Consentimental\Services\CookieDatabaseLookup;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\FirstPartyServices;
use Consentimental\Services\ServiceActivator;
use Consentimental\Services\ServiceOverrideRepository;
use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class Plugin
{
	private static ?self $instance = null;

	private readonly Settings $settings;
	private readonly CategoryRepository $categories;
	private readonly ServiceRegistry $services;
	private readonly ConsentManager $consent;
	private readonly ScriptGate $script_gate;
	private readonly ConsentMode $consent_mode;
	private readonly ServiceOverrideRepository $service_overrides;
	private readonly CustomScriptRepository $custom_scripts;
	private readonly CustomServiceRepository $custom_services;
	private readonly CookieDatabaseLookup $cookie_database;
	private readonly MultilingualStrings $multilingual;
	private readonly ConsentLogRepository $consent_log;
	private readonly DisclosureSnapshotRepository $snapshots;
	private readonly DraftManager $draft_manager;
	private readonly CategoryRepository $categories_draft;
	private readonly CustomServiceRepository $custom_services_draft;
	private readonly ServiceOverrideRepository $service_overrides_draft;
	private readonly CustomScriptRepository $custom_scripts_draft;
	private ?ServiceRegistry $services_draft = null;

	private function __construct()
	{
		$this->settings = new Settings();
		$this->categories = new CategoryRepository();
		$this->custom_services = new CustomServiceRepository();
		$this->services = new ServiceRegistry($this->custom_services);
		$this->consent = new ConsentManager($this->categories, $this->services, $this->settings);
		$this->script_gate = new ScriptGate($this->services);
		$this->consent_mode = new ConsentMode($this->settings);
		$this->service_overrides = new ServiceOverrideRepository();
		$this->custom_scripts = new CustomScriptRepository();
		$this->cookie_database = new CookieDatabaseLookup(CMP_PLUGIN_DIR . 'assets/data/open-cookie-database.json');
		$this->multilingual = new MultilingualStrings();
		$this->consent_log = new ConsentLogRepository();
		$this->snapshots = new DisclosureSnapshotRepository();

		$this->draft_manager = new DraftManager($this->settings);
		$this->categories_draft = new CategoryRepository(
			DraftManager::draft_option_name(CategoryRepository::OPTION_NAME),
			CategoryRepository::OPTION_NAME,
		);
		$this->custom_services_draft = new CustomServiceRepository(
			DraftManager::draft_option_name(CustomServiceRepository::OPTION_NAME),
			CustomServiceRepository::OPTION_NAME,
		);
		$this->service_overrides_draft = new ServiceOverrideRepository(
			DraftManager::draft_option_name(ServiceOverrideRepository::OPTION_NAME),
			ServiceOverrideRepository::OPTION_NAME,
		);
		$this->custom_scripts_draft = new CustomScriptRepository(
			DraftManager::draft_option_name(CustomScriptRepository::OPTION_NAME),
			CustomScriptRepository::OPTION_NAME,
		);
	}

	public static function instance(): self
	{
		return self::$instance ??= new self();
	}

	public function boot(): void
	{
		load_plugin_textdomain('consentimental', false, dirname(plugin_basename(CMP_PLUGIN_FILE)) . '/languages');

		$this->register_built_ins($this->services, $this->service_overrides->all());

		$this->consent_log->maybe_upgrade();
		$this->snapshots->maybe_upgrade();
		$this->consent_log->register($this->settings);
		add_action(ConsentLogRepository::CLEANUP_HOOK, function (): void {
			$this->snapshots->purge_unreferenced(max(1, (int) $this->settings->get('consent_log_retention_days')), $this->consent_log->table_name());
		}, 20);
		(new ConsentRecorder($this->consent_log, $this->settings))->register();
		(new CachePurger())->register();
		add_action('save_post', [FirstPartyServices::class, 'flush_cache']);

		$banner_content = new BannerContent($this->categories, $this->settings, $this->services, $this->multilingual);
		$disclosures = new DisclosureRecorder($this->snapshots, $this->settings, $this->multilingual, $this->fresh_live_content(...));
		$disclosures->register();

		(new ConsentController($this->categories, $this->services, $this->settings, $this->consent, $this->snapshots))->register();
		$proxy = new ScriptProxy($this->custom_scripts);
		$proxy->register();
		$proxy_health = new ProxyHealth($this->custom_scripts, $proxy);
		$proxy_health->register();
		(new Shortcodes())->register();

		$services_shortcode = new ServicesShortcode($this->services, $this->categories, $this->multilingual, $this->settings);
		$services_shortcode->register();
		(new ServicesBlock($services_shortcode))->register();

		if (is_admin()) {
			$this->services_draft = new ServiceRegistry($this->custom_services_draft);
			$this->register_built_ins($this->services_draft, $this->service_overrides_draft->all());
			$service_activator_draft = new ServiceActivator($this->services_draft, $this->service_overrides_draft, $this->custom_services_draft, $this->draft_manager);

			(new AdminMenu($this->categories, $this->services, $this->settings, $this->draft_manager))->register();
			(new CategoriesPage($this->categories_draft, $this->draft_manager))->register();
			(new ServicesPage($this->services_draft, $this->custom_services_draft, $this->service_overrides_draft, $this->categories_draft, $this->settings, $this->custom_scripts_draft, $service_activator_draft, $this->cookie_database, $this->draft_manager))->register();
			(new ScriptsPage($this->custom_scripts_draft, $this->categories_draft, $this->services_draft, $this->draft_manager))->register();
			(new BannerPage($this->settings, $banner_content))->register();
			(new ScannerPage(new SiteScanner($this->services_draft), $service_activator_draft, $this->services_draft))->register();
			(new SetupPage(new SiteScanner($this->services_draft), $this->services_draft, $service_activator_draft, $this->categories_draft, $this->custom_scripts_draft, $this->draft_manager, $this->settings, $banner_content))->register();
			(new ConsentLogPage($this->consent_log, $this->settings, $this->snapshots))->register();
			(new ProxyHealthPage($proxy_health))->register();

			$verifications = new VerificationLog();
			$status_report = new StatusReport($this->categories, $this->services, $this->custom_scripts, $this->settings, $this->draft_manager, $proxy_health, $verifications);
			(new StatusPage($status_report, $verifications, $this->settings))->register();
			(new PublishPage($this->draft_manager, $this->categories, $this->categories_draft, $this->custom_services, $this->custom_services_draft, $this->custom_scripts, $this->custom_scripts_draft))->register();
		} else {
			$preview = isset($_GET['cmp_preview']) && current_user_can('manage_options');

			if ($preview) {
				nocache_headers();
				$preview_registry = new ServiceRegistry($this->custom_services_draft);
				$this->register_built_ins($preview_registry, $this->service_overrides_draft->all());
				$banner_content = new BannerContent($this->categories_draft, $this->settings, $preview_registry, $this->multilingual);
			}

			(new ConsentBootstrap($this->categories, $this->settings, $this->services, $this->multilingual, $banner_content, $preview ? null : $disclosures, $preview))->register();
			$this->consent_mode->register();
			(new Banner($this->settings, $banner_content, $preview))->register();
			(new BlockingAssets())->register();
			$this->script_gate->register_hooks();
			(new OutputBuffer(new DomRewriter($this->services)))->register();
			(new DebugPanel())->register();
			(new ScriptManager($this->custom_scripts))->register();
		}
	}

	private function fresh_live_content(): BannerContent
	{
		$registry = new ServiceRegistry(new CustomServiceRepository());
		$this->register_built_ins($registry, (new ServiceOverrideRepository())->all());

		return new BannerContent(new CategoryRepository(), $this->settings, $registry, $this->multilingual);
	}

	public function snapshots(): DisclosureSnapshotRepository
	{
		return $this->snapshots;
	}

	public function consent_log(): ConsentLogRepository
	{
		return $this->consent_log;
	}

	/**
	 * @param array<string, array{category?: string, domains?: string[], script_patterns?: string[], iframe_patterns?: string[], active?: bool}> $overrides
	 */
	private function register_built_ins(ServiceRegistry $registry, array $overrides): void
	{
		(new FirstPartyServices($this->settings))->register($registry);
		(new GoogleServices($this->consent_mode))->register($registry);
		(new BuiltInServiceLibrary())->register($registry);

		foreach ($overrides as $service_id => $override) {
			$registry->apply_override($service_id, $override);
		}

		$known = array_map(static fn ($service) => $service->id, $registry->all());

		do_action('cmp_register_services', $registry);

		foreach ($overrides as $service_id => $override) {
			if (!in_array($service_id, $known, true)) {
				$registry->apply_override($service_id, $override);
			}
		}
	}

	public function settings(): Settings
	{
		return $this->settings;
	}

	public function categories(): CategoryRepository
	{
		return $this->categories;
	}

	public function services(): ServiceRegistry
	{
		return $this->services;
	}

	public function consent(): ConsentManager
	{
		return $this->consent;
	}

	public function script_gate(): ScriptGate
	{
		return $this->script_gate;
	}

	public function consent_mode(): ConsentMode
	{
		return $this->consent_mode;
	}
}
