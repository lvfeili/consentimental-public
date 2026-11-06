<?php
/**
 * @var string $step
 * @var array<string, string> $labels
 * @var string|null $previous_step
 * @var string|null $next_step
 * @var callable(string, array=): string $step_url
 * @var string $done
 * @var array{scanned_at: int, pages_scanned: int, matched: array<string, array{service: \Consentimental\Services\Service, pages: string[]}>, unknown_domains: array<string, string[]>}|null $scan
 * @var \Consentimental\Consent\Category[] $categories
 * @var int $config_version
 * @var array<string, array{service: \Consentimental\Services\Service, detected: bool, confirmed: bool}> $service_states
 * @var \Consentimental\Diagnostics\Finding[] $findings
 * @var bool $pending
 * @var bool $meaningful
 * @var int $privacy_policy_page_id
 * @var int $chosen_policy_page_id
 * @var int $cookie_policy_page_id
 * @var string $banner_title
 * @var string $banner_text
 * @var string $preview_url
 * @var array<string, array<string, string>> $verification_steps
 * @var array<string, mixed> $preview_content see templates/admin/partials/banner-preview.php
 * @var array<string, mixed> $preview_settings
 * @var bool $preview_has_fallback_policy
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Admin\AdminMenu;
use Consentimental\Admin\ServicesPage;
use Consentimental\Admin\StatusPage;
use Consentimental\Diagnostics\Finding;
use Consentimental\Diagnostics\VerificationLog;

$format_time = static fn (int $time): string => wp_date(get_option('date_format') . ' ' . get_option('time_format'), $time);
$step_number = array_search($step, array_keys($labels), true) + 1;
$notices = [
	'scanned' => __('Scan finished. Review what it found below.', 'consentimental'),
	'saved' => __('Saved. Service and category changes are staged until you publish.', 'consentimental'),
	'published' => __('Published. Visitors now see this configuration.', 'consentimental'),
	'published_bumped' => __('Published. Because services or categories changed, visitors who already decided will be asked again.', 'consentimental'),
];
?>
<div class="wrap cmp-wrap cmp-setup">
	<h1><?php esc_html_e('Guided setup', 'consentimental'); ?></h1>

	<p>
		<?php esc_html_e('Six steps from an unconfigured site to a verified banner. Nothing you change here reaches visitors until the publish step.', 'consentimental'); ?>
		<a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminMenu::PAGE_SLUG)); ?>"><?php esc_html_e('Skip to all settings', 'consentimental'); ?></a>
	</p>

	<nav aria-label="<?php esc_attr_e('Setup steps', 'consentimental'); ?>">
		<ol class="cmp-steps">
			<?php foreach ($labels as $key => $label) : ?>
				<li>
					<a href="<?php echo esc_url($step_url($key)); ?>" <?php echo $key === $step ? 'aria-current="step"' : ''; ?>><?php echo esc_html($label); ?></a>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>

	<?php if (isset($_GET['recorded'])) : ?>
		<div class="notice notice-success" role="status"><p><?php esc_html_e('Verification result recorded.', 'consentimental'); ?></p></div>
	<?php endif; ?>

	<?php if (isset($notices[$done])) : ?>
		<div class="notice notice-success" role="status"><p><?php echo esc_html($notices[$done]); ?></p></div>
	<?php endif; ?>

	<h2 id="cmp-step-heading" tabindex="-1">
		<?php
		/* translators: 1: step number, 2: total steps, 3: step name */
		printf(esc_html__('Step %1$d of %2$d: %3$s', 'consentimental'), (int) $step_number, count($labels), esc_html($labels[$step]));
		?>
	</h2>

	<?php if ('discover' === $step) : ?>
		<p><?php esc_html_e('The scan fetches up to 40 of your published pages as a logged-out visitor would, and looks for known services and external hosts in the HTML.', 'consentimental'); ?></p>
		<p><strong><?php esc_html_e('A scan finds likely services. It does not show that tracking is blocked at runtime', 'consentimental'); ?></strong>
			<?php esc_html_e('— anything added later by JavaScript, pages behind a login, and checkout steps are not seen. The verify step covers runtime behaviour.', 'consentimental'); ?></p>

		<?php if (null !== $scan) : ?>
			<div class="cmp-panel">
				<p>
					<?php
					printf(
						/* translators: 1: date/time, 2: number of pages */
						esc_html__('Last scan: %1$s, %2$d pages.', 'consentimental'),
						esc_html($format_time($scan['scanned_at'])),
						(int) $scan['pages_scanned'],
					);
					?>
				</p>
				<ul>
					<li>
						<?php
						/* translators: %d: number of services */
						printf(esc_html__('%d known services detected', 'consentimental'), count($scan['matched']));
						?>
					</li>
					<li>
						<?php
						/* translators: %d: number of hosts */
						printf(esc_html__('%d unrecognized external hosts', 'consentimental'), count($scan['unknown_domains']));
						?>
					</li>
				</ul>
			</div>
		<?php endif; ?>

		<form method="post" class="cmp-actions">
			<?php wp_nonce_field('cmp_setup', 'cmp_setup_nonce'); ?>
			<input type="hidden" name="cmp_setup_step" value="discover" />
			<button type="submit" class="button button-primary"><?php null === $scan ? esc_html_e('Scan my site', 'consentimental') : esc_html_e('Scan again', 'consentimental'); ?></button>
			<span class="description"><?php esc_html_e('This can take up to a minute.', 'consentimental'); ?></span>
		</form>

	<?php elseif ('review' === $step) : ?>
		<p><?php esc_html_e('Confirming a service lists it in the banner so visitors can allow it. Unconfirmed services stay blocked wherever the plugin recognises them, but visitors cannot allow them.', 'consentimental'); ?></p>
		<p><?php esc_html_e('Check each purpose and category. The category decides which banner switch covers the service.', 'consentimental'); ?></p>

		<?php if ([] === $service_states) : ?>
			<p><?php esc_html_e('No services detected or confirmed yet. Run a scan, or add services manually.', 'consentimental'); ?></p>
		<?php else : ?>
			<form method="post">
				<?php wp_nonce_field('cmp_setup', 'cmp_setup_nonce'); ?>
				<input type="hidden" name="cmp_setup_step" value="review" />
				<table class="widefat striped cmp-stack">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e('Service and purpose', 'consentimental'); ?></th>
							<th scope="col"><?php esc_html_e('Finding', 'consentimental'); ?></th>
							<th scope="col"><?php esc_html_e('Category', 'consentimental'); ?></th>
							<th scope="col"><?php esc_html_e('Confirmed', 'consentimental'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($service_states as $id => $state) : ?>
							<?php $service = $state['service']; ?>
							<tr data-cmp-service-row="<?php echo esc_attr($id); ?>">
								<td data-label="<?php esc_attr_e('Service and purpose', 'consentimental'); ?>">
									<strong><?php echo esc_html($service->name); ?></strong>
									<?php if ('' !== $service->provider) : ?>
										— <?php echo esc_html($service->provider); ?>
									<?php endif; ?>
									<br />
									<?php echo '' !== $service->purpose ? esc_html($service->purpose) : '<em>' . esc_html__('No purpose entered', 'consentimental') . '</em>'; ?>
								</td>
								<td data-label="<?php esc_attr_e('Finding', 'consentimental'); ?>">
									<?php
									echo esc_html(match (true) {
										$state['detected'] && $state['confirmed'] => __('Detected by scan, confirmed by you', 'consentimental'),
										$state['detected'] => __('Detected by scan, not confirmed', 'consentimental'),
										default => __('Confirmed by you, not detected by scan (unverified)', 'consentimental'),
									});
									?>
								</td>
								<td data-label="<?php esc_attr_e('Category', 'consentimental'); ?>">
									<label class="screen-reader-text" for="cmp-setup-category-<?php echo esc_attr($id); ?>">
										<?php
										/* translators: %s: service name */
										printf(esc_html__('Category for %s', 'consentimental'), esc_html($service->name));
										?>
									</label>
									<select id="cmp-setup-category-<?php echo esc_attr($id); ?>" name="services[<?php echo esc_attr($id); ?>][category]">
										<?php foreach ($categories as $category) : ?>
											<option value="<?php echo esc_attr($category->slug); ?>" <?php selected($service->category, $category->slug); ?>><?php echo esc_html($category->label); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td data-label="<?php esc_attr_e('Confirmed', 'consentimental'); ?>">
									<label>
										<input type="checkbox" name="services[<?php echo esc_attr($id); ?>][confirmed]" value="1" <?php checked($state['confirmed']); ?> />
										<?php esc_html_e('In use', 'consentimental'); ?>
										<span class="screen-reader-text"><?php echo esc_html(' — ' . $service->name); ?></span>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="cmp-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e('Save decisions', 'consentimental'); ?></button>
					<a href="<?php echo esc_url(admin_url('admin.php?page=' . ServicesPage::PAGE_SLUG)); ?>"><?php esc_html_e('Edit purposes, cookies and domains on the Services screen', 'consentimental'); ?></a>
				</p>
			</form>
		<?php endif; ?>

		<?php if (null !== $scan && [] !== $scan['unknown_domains']) : ?>
			<h3><?php esc_html_e('Unrecognized external hosts', 'consentimental'); ?></h3>
			<p><?php esc_html_e('These hosts are not matched by any service, so the plugin does not block them. Decide for each one whether it needs consent.', 'consentimental'); ?></p>
			<ul>
				<?php foreach (array_keys($scan['unknown_domains']) as $host) : ?>
					<li>
						<code><?php echo esc_html($host); ?></code> —
						<a href="<?php echo esc_url(admin_url('admin.php?page=' . ServicesPage::PAGE_SLUG . '&action=add&domain=' . rawurlencode($host))); ?>">
							<?php
							/* translators: %s: host name */
							printf(esc_html__('Create a service for %s', 'consentimental'), esc_html($host));
							?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

	<?php elseif ('problems' === $step) : ?>
		<p><?php esc_html_e('These checks run against the configuration you are about to publish. They show whether it is consistent and complete, not whether trackers are blocked in a browser.', 'consentimental'); ?></p>
		<?php
		$summary = Finding::summarize($findings);
		require __DIR__ . '/partials/summary.php';
		require __DIR__ . '/partials/findings.php';
		?>
		<p><a class="button" href="<?php echo esc_url($step_url('problems')); ?>"><?php esc_html_e('Check again', 'consentimental'); ?></a></p>

	<?php elseif ('banner' === $step) : ?>
		<p><?php esc_html_e('Banner wording and the policy link take effect as soon as you save. Leave a text field empty to use the built-in wording, which is translated into the visitor’s language where a translation is available.', 'consentimental'); ?></p>
		<div class="cmp-with-preview">
		<form method="post" class="cmp-panel">
			<?php wp_nonce_field('cmp_setup', 'cmp_setup_nonce'); ?>
			<input type="hidden" name="cmp_setup_step" value="banner" />
			<p>
				<label for="cmp-setup-title"><?php esc_html_e('Banner title', 'consentimental'); ?></label><br />
				<input type="text" id="cmp-setup-title" name="banner_title" class="large-text" data-cmp-preview-target="title" value="<?php echo esc_attr($banner_title); ?>" placeholder="<?php esc_attr_e('We value your privacy', 'consentimental'); ?>" />
			</p>
			<p>
				<label for="cmp-setup-text"><?php esc_html_e('Banner text', 'consentimental'); ?></label><br />
				<textarea id="cmp-setup-text" name="banner_text" rows="4" class="large-text" data-cmp-preview-target="text" aria-describedby="cmp-setup-text-help"><?php echo esc_textarea($banner_text); ?></textarea>
				<span id="cmp-setup-text-help" class="description"><?php esc_html_e('Say what you use optional technologies for, and that visitors can change their choice at any time.', 'consentimental'); ?></span>
			</p>
			<p>
				<label for="cmp-setup-policy"><?php esc_html_e('Privacy policy page linked from the banner', 'consentimental'); ?></label><br />
				<?php
				$wp_policy = (int) get_option('wp_page_for_privacy_policy', 0);
				$policy_select = wp_dropdown_pages([
					'name' => 'privacy_policy_page_id',
					'id' => 'cmp-setup-policy',
					'selected' => $chosen_policy_page_id,
					'show_option_none' => $wp_policy > 0
						/* translators: %s: page title */
						? sprintf(__('Use the page set in Settings → Privacy (%s)', 'consentimental'), get_the_title($wp_policy))
						: __('Use the page set in Settings → Privacy (none set)', 'consentimental'),
					'option_none_value' => '0',
					'post_status' => 'publish',
					'echo' => 0,
				]);
				echo str_replace('<select ', '<select data-cmp-preview-policy="' . esc_attr__('Privacy policy', 'consentimental') . '" ', (string) $policy_select); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_dropdown_pages() output.
				?>
			</p>
			<p>
				<?php if ($cookie_policy_page_id > 0 && 'publish' === get_post_status($cookie_policy_page_id)) : ?>
					<?php esc_html_e('Your cookie policy page is published and is linked from the banner as well.', 'consentimental'); ?>
				<?php else : ?>
					<?php esc_html_e('Optional: a cookie policy page listing every service and cookie can be generated on the Services screen.', 'consentimental'); ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=' . ServicesPage::PAGE_SLUG)); ?>"><?php esc_html_e('Go to Services', 'consentimental'); ?></a>
				<?php endif; ?>
			</p>
			<?php if (0 === $privacy_policy_page_id || 'publish' !== get_post_status($privacy_policy_page_id)) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e('No published privacy policy page is linked yet. Visitors will not be able to reach your full privacy information from the banner.', 'consentimental'); ?></p></div>
			<?php endif; ?>
			<p class="cmp-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e('Save banner', 'consentimental'); ?></button>
				<a href="<?php echo esc_url(admin_url('admin.php?page=consentimental-banner')); ?>"><?php esc_html_e('Colours, position, logo and more on the Banner screen', 'consentimental'); ?></a>
			</p>
		</form>
		<?php require __DIR__ . '/partials/banner-preview.php'; ?>
		</div>

	<?php elseif ('publish' === $step) : ?>
		<p>
			<a class="button" href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener">
				<?php esc_html_e('Preview the banner', 'consentimental'); ?>
				<span class="screen-reader-text"><?php esc_html_e('(opens in a new tab)', 'consentimental'); ?></span>
			</a>
		</p>
		<p class="description"><?php esc_html_e('The preview opens your home page with the banner shown and your staged services and categories in the settings dialog. Choices made in the preview are not saved, and blocking on that page still follows the published configuration.', 'consentimental'); ?></p>

		<?php if (!$pending) : ?>
			<p><?php esc_html_e('Nothing is waiting to be published. The site already uses this configuration.', 'consentimental'); ?></p>
		<?php else : ?>
			<div class="cmp-panel">
				<?php if ($meaningful) : ?>
					<p><strong><?php esc_html_e('Publishing changes which services or categories visitors are asked about. Visitors who already decided will see the banner again.', 'consentimental'); ?></strong></p>
				<?php else : ?>
					<p><?php esc_html_e('The staged changes are cosmetic. Existing decisions stay valid.', 'consentimental'); ?></p>
				<?php endif; ?>
				<form method="post" class="cmp-actions">
					<?php wp_nonce_field('cmp_setup', 'cmp_setup_nonce'); ?>
					<input type="hidden" name="cmp_setup_step" value="publish" />
					<button type="submit" class="button button-primary"><?php esc_html_e('Publish configuration', 'consentimental'); ?></button>
					<a href="<?php echo esc_url(admin_url('admin.php?page=consentimental-publish')); ?>"><?php esc_html_e('Review details or discard', 'consentimental'); ?></a>
				</form>
			</div>
		<?php endif; ?>

	<?php elseif ('verify' === $step) : ?>
		<p><?php esc_html_e('Only a check in a real browser shows whether trackers wait for consent on your site. Follow these steps in a private window, with the browser developer tools open on the Network and Storage (Application) tabs.', 'consentimental'); ?></p>
		<ol>
			<?php foreach ($verification_steps[VerificationLog::TYPE_BROWSER] as $instruction) : ?>
				<li><?php echo esc_html($instruction); ?></li>
			<?php endforeach; ?>
		</ol>
		<p>
			<?php esc_html_e('While logged in, the “CMP Debug” panel on your site shows the current consent state and which elements were blocked or released on that page. Use it to understand results, not instead of the private-window check.', 'consentimental'); ?>
		</p>
		<p><?php esc_html_e('Then check in each vendor’s own dashboard that data arrives after consent, and does not arrive after a rejection. Record both results:', 'consentimental'); ?></p>
		<?php
		$verification_type = VerificationLog::TYPE_BROWSER;
		?>
		<h3><?php esc_html_e('Browser check', 'consentimental'); ?></h3>
		<?php require __DIR__ . '/partials/verification-form.php'; ?>
		<?php $verification_type = VerificationLog::TYPE_VENDOR; ?>
		<h3><?php esc_html_e('Vendor check', 'consentimental'); ?></h3>
		<?php require __DIR__ . '/partials/verification-form.php'; ?>

		<form method="post" class="cmp-actions">
			<?php wp_nonce_field('cmp_setup', 'cmp_setup_nonce'); ?>
			<input type="hidden" name="cmp_setup_step" value="complete" />
			<button type="submit" class="button"><?php esc_html_e('Finish and go to Status', 'consentimental'); ?></button>
		</form>
	<?php endif; ?>

	<p class="cmp-actions">
		<?php if (null !== $previous_step) : ?>
			<a class="button" href="<?php echo esc_url($step_url($previous_step)); ?>"><?php
				/* translators: %s: step name */
				printf(esc_html__('Previous: %s', 'consentimental'), esc_html($labels[$previous_step]));
				?></a>
		<?php endif; ?>
		<?php if (null !== $next_step) : ?>
			<a class="button button-secondary" href="<?php echo esc_url($step_url($next_step)); ?>"><?php
				/* translators: %s: step name */
				printf(esc_html__('Next: %s', 'consentimental'), esc_html($labels[$next_step]));
				?></a>
		<?php endif; ?>
	</p>

	<?php if ('' !== $done) : ?>
		<script>document.getElementById('cmp-step-heading').focus();</script>
	<?php endif; ?>
</div>
