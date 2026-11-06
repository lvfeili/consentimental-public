<?php
/**
 * @var array{scanned_at: int, pages_scanned: int, matched: array<string, array{service: \Consentimental\Services\Service, pages: string[]}>, unknown_domains: array<string, string[]>}|null $results
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Admin\ServicesPage;

$matched_active = [];
$matched_inactive = [];

if (null !== $results) {
	foreach ($results['matched'] as $entry) {
		if ($entry['service']->active) {
			$matched_active[] = $entry;
		} else {
			$matched_inactive[] = $entry;
		}
	}
}
?>
<div class="wrap cmp-wrap">
	<h1><?php esc_html_e('Scan site', 'consentimental'); ?></h1>

	<?php if (isset($_GET['updated'])) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Saved.', 'consentimental'); ?></p></div>
	<?php endif; ?>

	<p>
		<?php esc_html_e('Fetches your own published pages/posts (up to 40) and checks each one\'s HTML against every registered service\'s known domains and patterns — the same detection Blocking\\DomRewriter uses live, run here for discovery instead of enforcement. It won\'t catch something injected purely by client-side JavaScript after the page loads. Nothing is activated or created automatically: review the results below and confirm each one.', 'consentimental'); ?>
	</p>

	<form method="post">
		<?php wp_nonce_field('cmp_scanner', 'cmp_scanner_nonce'); ?>
		<button type="submit" name="cmp_run_scan" value="1" class="button button-primary">
			<?php echo null === $results ? esc_html__('Scan site', 'consentimental') : esc_html__('Scan again', 'consentimental'); ?>
		</button>
	</form>

	<?php if (null === $results) : ?>
		<p style="margin-top: 16px;"><?php esc_html_e('No scan has been run yet.', 'consentimental'); ?></p>
		<?php return; ?>
	<?php endif; ?>

	<p style="margin-top: 16px; color: #646970;">
		<?php
		printf(
			/* translators: 1: number of pages scanned, 2: date and time */
			esc_html__('Last scan checked %1$d page(s) on %2$s.', 'consentimental'),
			(int) $results['pages_scanned'],
			esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $results['scanned_at'])),
		);
		?>
	</p>

	<h2><?php esc_html_e('Found, not yet active', 'consentimental'); ?></h2>
	<?php if ([] === $matched_inactive) : ?>
		<p><?php esc_html_e('Nothing — every service the scan matched is already active.', 'consentimental'); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Service', 'consentimental'); ?></th>
					<th><?php esc_html_e('Category', 'consentimental'); ?></th>
					<th><?php esc_html_e('Found on', 'consentimental'); ?></th>
					<th><?php esc_html_e('Action', 'consentimental'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($matched_inactive as $entry) : ?>
					<tr>
						<td><?php echo esc_html($entry['service']->name); ?></td>
						<td><code><?php echo esc_html($entry['service']->category); ?></code></td>
						<td>
							<?php foreach ($entry['pages'] as $page_url) : ?>
								<div><a href="<?php echo esc_url($page_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($page_url); ?></a></div>
							<?php endforeach; ?>
						</td>
						<td>
							<form method="post">
								<?php wp_nonce_field('cmp_scanner', 'cmp_scanner_nonce'); ?>
								<input type="hidden" name="id" value="<?php echo esc_attr($entry['service']->id); ?>" />
								<button type="submit" name="cmp_scanner_activate" value="1" class="button button-primary">
									<?php esc_html_e('Activate', 'consentimental'); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e('Found, already active', 'consentimental'); ?></h2>
	<?php if ([] === $matched_active) : ?>
		<p><?php esc_html_e('Nothing yet.', 'consentimental'); ?></p>
	<?php else : ?>
		<p><?php echo esc_html(implode(', ', array_map(static fn (array $e): string => $e['service']->name, $matched_active))); ?></p>
	<?php endif; ?>

	<h2><?php esc_html_e('Unrecognized domains', 'consentimental'); ?></h2>
	<p class="description"><?php esc_html_e('Script/iframe/image sources found on your site that don\'t match any registered service. Some of these may be harmless (a CDN, a font host) — check before adding.', 'consentimental'); ?></p>
	<?php if ([] === $results['unknown_domains']) : ?>
		<p><?php esc_html_e('Nothing unrecognized found.', 'consentimental'); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Domain', 'consentimental'); ?></th>
					<th><?php esc_html_e('Found on', 'consentimental'); ?></th>
					<th><?php esc_html_e('Action', 'consentimental'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($results['unknown_domains'] as $domain => $pages) : ?>
					<tr>
						<td><code><?php echo esc_html($domain); ?></code></td>
						<td>
							<?php foreach ($pages as $page_url) : ?>
								<div><a href="<?php echo esc_url($page_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($page_url); ?></a></div>
							<?php endforeach; ?>
						</td>
						<td>
							<a
								class="button"
								href="<?php echo esc_url(admin_url('admin.php?page=' . ServicesPage::PAGE_SLUG . '&action=add&domain=' . rawurlencode($domain))); ?>"
							>
								<?php esc_html_e('Add as custom service', 'consentimental'); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
