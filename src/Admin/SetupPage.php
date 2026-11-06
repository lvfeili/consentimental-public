<?php

declare(strict_types=1);

namespace Consentimental\Admin;

use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\DraftManager;
use Consentimental\Consent\Settings;
use Consentimental\Diagnostics\ConfigurationChecks;
use Consentimental\Diagnostics\SiteScanner;
use Consentimental\Diagnostics\StatusReport;
use Consentimental\Diagnostics\VerificationLog;
use Consentimental\Frontend\BannerContent;
use Consentimental\Scripts\CustomScriptRepository;
use Consentimental\Services\ServiceActivator;
use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class SetupPage
{
	public const PAGE_SLUG = 'consentimental-setup';
	public const STATE_OPTION = 'cmp_setup';

	public const STEPS = ['discover', 'review', 'problems', 'banner', 'publish', 'verify'];

	public function __construct(
		private readonly SiteScanner $scanner,
		private readonly ServiceRegistry $services_draft,
		private readonly ServiceActivator $activator_draft,
		private readonly CategoryRepository $categories_draft,
		private readonly CustomScriptRepository $scripts_draft,
		private readonly DraftManager $draft,
		private readonly Settings $settings,
		private readonly BannerContent $content,
	) {
	}

	/**
	 * @return array<string, string>
	 */
	public static function step_labels(): array
	{
		return [
			'discover' => __('Discover', 'consentimental'),
			'review' => __('Review services', 'consentimental'),
			'problems' => __('Resolve problems', 'consentimental'),
			'banner' => __('Banner and policy', 'consentimental'),
			'publish' => __('Preview and publish', 'consentimental'),
			'verify' => __('Verify', 'consentimental'),
		];
	}

	public function register(): void
	{
		add_action('admin_menu', $this->add_menu(...), 11);
		add_action('admin_init', $this->maybe_handle_form(...));
		add_action('admin_enqueue_scripts', function (string $hook): void {
			if ('consentimental_page_' . self::PAGE_SLUG === $hook && 'banner' === $this->current_step()) {
				BannerPage::enqueue_preview_assets();
			}
		});
	}

	private function add_menu(): void
	{
		add_submenu_page(
			parent_slug: AdminMenu::PAGE_SLUG,
			page_title: __('Guided setup', 'consentimental'),
			menu_title: __('Guided setup', 'consentimental'),
			capability: 'manage_options',
			menu_slug: self::PAGE_SLUG,
			callback: $this->render(...),
			position: 1,
		);
	}

	private function current_step(): string
	{
		$step = sanitize_key((string) wp_unslash($_GET['step'] ?? ''));

		return in_array($step, self::STEPS, true) ? $step : self::STEPS[0];
	}

	private function step_url(string $step, array $args = []): string
	{
		return add_query_arg(array_merge(['page' => self::PAGE_SLUG, 'step' => $step], $args), admin_url('admin.php'));
	}

	private function maybe_handle_form(): void
	{
		if (!isset($_POST['cmp_setup_nonce']) || !current_user_can('manage_options')) {
			return;
		}

		check_admin_referer('cmp_setup', 'cmp_setup_nonce');

		$step = sanitize_key((string) wp_unslash($_POST['cmp_setup_step'] ?? ''));

		switch ($step) {
			case 'discover':
				if (function_exists('set_time_limit')) {
					@set_time_limit(120);
				}

				ScannerPage::store($this->scanner->scan());
				$this->go('discover', ['done' => 'scanned']);
				break;

			case 'review':
				$this->save_review();
				$this->go('review', ['done' => 'saved']);
				break;

			case 'banner':
				$page_id = absint(wp_unslash($_POST['privacy_policy_page_id'] ?? 0));
				$this->settings->save([
					'banner_title' => sanitize_text_field((string) wp_unslash($_POST['banner_title'] ?? '')),
					'banner_text' => sanitize_textarea_field((string) wp_unslash($_POST['banner_text'] ?? '')),
					'privacy_policy_page_id' => $page_id > 0 && 'page' === get_post_type($page_id) ? $page_id : 0,
				]);
				$this->go('banner', ['done' => 'saved']);
				break;

			case 'publish':
				$result = $this->draft->publish();
				$this->go('publish', ['done' => $result['version_bumped'] ? 'published_bumped' : 'published']);
				break;

			case 'complete':
				update_option(self::STATE_OPTION, ['completed_at' => time(), 'config_version' => $this->settings->config_version()], false);
				wp_safe_redirect(admin_url('admin.php?page=' . StatusPage::PAGE_SLUG));
				exit;
		}
	}

	private function save_review(): void
	{
		$rows = (array) wp_unslash($_POST['services'] ?? []);
		$categories = array_map(static fn ($category) => $category->slug, $this->categories_draft->all());

		foreach ($rows as $id => $row) {
			$id = sanitize_key((string) $id);
			$row = (array) $row;

			if (null === $this->services_draft->get($id)) {
				continue;
			}

			$category = sanitize_key((string) ($row['category'] ?? ''));

			if (in_array($category, $categories, true)) {
				$this->activator_draft->set_category($id, $category);
			}

			$this->activator_draft->set_active($id, !empty($row['confirmed']));
		}
	}

	private function go(string $step, array $args = []): void
	{
		wp_safe_redirect($this->step_url($step, $args));

		exit;
	}

	private function render(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$step = $this->current_step();
		$labels = self::step_labels();
		$index = (int) array_search($step, self::STEPS, true);
		$previous_step = self::STEPS[$index - 1] ?? null;
		$next_step = self::STEPS[$index + 1] ?? null;
		$step_url = $this->step_url(...);
		$done = sanitize_key((string) wp_unslash($_GET['done'] ?? ''));
		$scan = ScannerPage::load($this->services_draft);
		$categories = $this->categories_draft->all();
		$config_version = $this->settings->config_version();

		$service_states = StatusReport::service_states($this->services_draft->all(), null === $scan ? [] : array_keys($scan['matched']));

		$findings = ConfigurationChecks::run(
			$categories,
			$this->services_draft->all(),
			$this->scripts_draft->all(),
			BannerContent::has_published_policy($this->settings),
			false,
			$config_version,
			time(),
			admin_url('admin.php?page=' . ServicesPage::PAGE_SLUG),
			admin_url('admin.php?page=consentimental-scripts'),
			$this->step_url('banner'),
		);
		$findings = array_merge($findings, StatusReport::discovery_findings(
			null === $scan ? null : [
				'scanned_at' => $scan['scanned_at'],
				'pages_scanned' => $scan['pages_scanned'],
				'unconfirmed' => array_values(array_map(static fn ($entry) => $entry['service']->name, array_filter($scan['matched'], static fn ($entry) => !$entry['service']->active))),
				'unknown_hosts' => array_keys($scan['unknown_domains']),
			],
			time(),
			$this->step_url('discover'),
		));

		$pending = $this->draft->has_pending();
		$meaningful = $this->draft->is_meaningful();
		$privacy_policy_page_id = BannerContent::privacy_policy_page_id($this->settings);
		$chosen_policy_page_id = (int) $this->settings->get('privacy_policy_page_id');
		$cookie_policy_page_id = (int) $this->settings->get('cookie_policy_page_id');
		$banner_title = (string) $this->settings->get('banner_title');
		$banner_text = (string) $this->settings->get('banner_text');
		$preview_url = add_query_arg('cmp_preview', '1', home_url('/'));
		$verification_steps = VerificationLog::steps();
		[$preview_content, $preview_settings, $preview_has_fallback_policy] = BannerPage::preview_data($this->settings, $this->content);

		require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/setup.php';
	}

}
