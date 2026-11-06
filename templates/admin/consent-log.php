<?php
/**
 * @var list<array<string, mixed>> $items
 * @var int $total
 * @var int $total_pages
 * @var int $page
 * @var string $consent_id
 * @var bool $consent_log_enabled
 * @var int $consent_log_retention_days
 * @var list<array<string, mixed>> $recent_snapshots
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap cmp-wrap">
	<h1><?php esc_html_e('Consent log', 'consentimental'); ?></h1>

	<p>
		<?php esc_html_e('Every recorded consent decision, newest first. Each visitor is identified only by their anonymous consent_id (the value in their cmp_consent cookie) — search for one to see its full history and prove what was agreed to and when.', 'consentimental'); ?>
	</p>

	<p class="description">
		<?php esc_html_e('This is a record of what the visitor’s own browser reported, over an anonymous, unauthenticated endpoint by design (no account or login is required to give or withdraw consent). Treat it as the visitor’s self-reported assertion, not cryptographically authenticated evidence — the same caveat that applies to any cookie-based consent record, on any CMP.', 'consentimental'); ?>
	</p>

	<?php if (isset($_GET['settings_updated'])) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Saved.', 'consentimental'); ?></p></div>
	<?php endif; ?>

	<form method="post" style="margin-bottom: 20px;">
		<?php wp_nonce_field('cmp_consent_log_settings', 'cmp_consent_log_settings_nonce'); ?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e('Record consent decisions', 'consentimental'); ?></th>
				<td>
					<label>
						<input type="checkbox" name="consent_log_enabled" value="1" <?php checked($consent_log_enabled); ?> />
						<?php esc_html_e('Keep a server-side record of each consent decision, for proof of consent', 'consentimental'); ?>
					</label>
					<p class="description">
						<?php esc_html_e('Off by default would mean no way to prove consent later if ever asked — this plugin keeps it on by default, but it’s never mandatory: turn it off if this site has no need to demonstrate consent after the fact.', 'consentimental'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-consent-log-retention"><?php esc_html_e('Retention', 'consentimental'); ?></label></th>
				<td>
					<input type="number" id="cmp-consent-log-retention" name="consent_log_retention_days" min="1" class="small-text" value="<?php echo esc_attr((string) $consent_log_retention_days); ?>" />
					<?php esc_html_e('days', 'consentimental'); ?>
					<p class="description"><?php esc_html_e('Records older than this are deleted by a daily WordPress cron job. Disclosure snapshots are kept while any retained record references them; unreferenced snapshots older than this are deleted too, except the newest one per language. Disabling recording or deactivating the plugin deletes nothing.', 'consentimental'); ?></p>
				</td>
			</tr>
		</table>
		<p>
			<button type="submit" name="cmp_save_consent_log_settings" value="1" class="button button-primary">
				<?php esc_html_e('Save', 'consentimental'); ?>
			</button>
		</p>
	</form>

	<?php if (!$consent_log_enabled) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e('Recording is currently off — no new decisions are being logged. Existing records below are unaffected.', 'consentimental'); ?></p></div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr(\Consentimental\Admin\ConsentLogPage::PAGE_SLUG); ?>" />
		<p class="search-box">
			<label class="screen-reader-text" for="cmp-consent-id-search"><?php esc_html_e('Search by consent ID', 'consentimental'); ?></label>
			<input type="text" id="cmp-consent-id-search" name="consent_id" value="<?php echo esc_attr($consent_id); ?>" placeholder="<?php esc_attr_e('consent_id…', 'consentimental'); ?>" />
			<input type="submit" class="button" value="<?php esc_attr_e('Search', 'consentimental'); ?>" />
			<?php if ('' !== $consent_id) : ?>
				<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . \Consentimental\Admin\ConsentLogPage::PAGE_SLUG)); ?>"><?php esc_html_e('Clear', 'consentimental'); ?></a>
			<?php endif; ?>
		</p>
	</form>

	<?php $export_url = static fn (string $format): string => wp_nonce_url(add_query_arg(['action' => 'cmp_export_consent_log', 'format' => $format, 'consent_id' => $consent_id], admin_url('admin-post.php')), 'cmp_export_consent_log'); ?>
	<p>
		<a class="button" href="<?php echo esc_url($export_url('csv')); ?>"><?php '' !== $consent_id ? esc_html_e('Export these records (CSV)', 'consentimental') : esc_html_e('Export all records (CSV)', 'consentimental'); ?></a>
		<a class="button" href="<?php echo esc_url($export_url('json')); ?>"><?php esc_html_e('Export with disclosure snapshots (JSON)', 'consentimental'); ?></a>
	</p>

	<?php if (isset($_GET['erased'])) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Records deleted.', 'consentimental'); ?></p></div>
	<?php endif; ?>

	<?php if ([] === $items) : ?>
		<p><?php esc_html_e('No consent records found.', 'consentimental'); ?></p>
	<?php else : ?>
		<?php if ('' !== $consent_id) : ?>
			<form method="post" onsubmit="return confirm('<?php echo esc_js(__('Permanently delete every record for this consent ID? This cannot be undone, and should only be done to fulfil a visitor\'s erasure request — it removes your ability to prove what this visitor consented to.', 'consentimental')); ?>');" style="margin: 12px 0;">
				<?php wp_nonce_field('cmp_consent_log_erase', 'cmp_consent_log_erase_nonce'); ?>
				<input type="hidden" name="consent_id" value="<?php echo esc_attr($consent_id); ?>" />
				<button type="submit" name="cmp_delete_consent_records" value="1" class="button">
					<?php esc_html_e('Delete all records for this consent ID (right to erasure)', 'consentimental'); ?>
				</button>
			</form>
		<?php endif; ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Recorded (UTC)', 'consentimental'); ?></th>
					<th><?php esc_html_e('Consent ID', 'consentimental'); ?></th>
					<th><?php esc_html_e('Action', 'consentimental'); ?></th>
					<th><?php esc_html_e('Categories', 'consentimental'); ?></th>
					<th><?php esc_html_e('Config v.', 'consentimental'); ?></th>
					<th><?php esc_html_e('Disclosure shown', 'consentimental'); ?></th>
					<th><?php esc_html_e('IP (anonymized)', 'consentimental'); ?></th>
					<th><?php esc_html_e('Page', 'consentimental'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($items as $item) : ?>
					<?php
					$categories_summary = implode(', ', array_map(
						static fn (string $slug, $enabled): string => $slug . ($enabled ? ':on' : ':off'),
						array_keys($item['categories']),
						$item['categories'],
					));
					?>
					<tr>
						<td><?php echo esc_html((string) $item['recorded_at']); ?></td>
						<td>
							<a href="<?php echo esc_url(add_query_arg(['page' => \Consentimental\Admin\ConsentLogPage::PAGE_SLUG, 'consent_id' => (string) $item['consent_id']], admin_url('admin.php'))); ?>">
								<code><?php echo esc_html((string) $item['consent_id']); ?></code>
							</a>
						</td>
						<td><?php echo esc_html((string) $item['action']); ?></td>
						<td><code><?php echo esc_html('' === $categories_summary ? '—' : $categories_summary); ?></code></td>
						<td><?php echo esc_html((string) $item['config_version']); ?></td>
						<td>
							<?php if (!empty($item['disclosure_hash'])) : ?>
								<a href="<?php echo esc_url(add_query_arg(['page' => \Consentimental\Admin\ConsentLogPage::PAGE_SLUG, 'snapshot' => (string) $item['disclosure_hash']], admin_url('admin.php'))); ?>"><code><?php echo esc_html(substr((string) $item['disclosure_hash'], 0, 12)); ?></code></a>
								<?php echo esc_html((string) ($item['language'] ?? '')); ?>
							<?php else : ?>
								<?php esc_html_e('Unknown', 'consentimental'); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html((string) ($item['ip_anonymized'] ?? '—')); ?></td>
						<td>
							<?php if (!empty($item['page_url'])) : ?>
								<a href="<?php echo esc_url((string) $item['page_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html((string) $item['page_url']); ?></a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ($total_pages > 1) : ?>
			<p class="tablenav-pages" style="margin-top: 12px;">
				<?php
				printf(
					/* translators: 1: current page, 2: total pages */
					esc_html__('Page %1$d of %2$d', 'consentimental'),
					(int) $page,
					(int) $total_pages,
				);
				?>
				&nbsp;
				<?php if ($page > 1) : ?>
					<a class="button" href="<?php echo esc_url(add_query_arg(['page' => \Consentimental\Admin\ConsentLogPage::PAGE_SLUG, 'consent_id' => $consent_id, 'paged' => $page - 1], admin_url('admin.php'))); ?>"><?php esc_html_e('Previous', 'consentimental'); ?></a>
				<?php endif; ?>
				<?php if ($page < $total_pages) : ?>
					<a class="button" href="<?php echo esc_url(add_query_arg(['page' => \Consentimental\Admin\ConsentLogPage::PAGE_SLUG, 'consent_id' => $consent_id, 'paged' => $page + 1], admin_url('admin.php'))); ?>"><?php esc_html_e('Next', 'consentimental'); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<h2><?php esc_html_e('Disclosure snapshots', 'consentimental'); ?></h2>
	<p><?php esc_html_e('Each snapshot is an immutable copy of what the banner disclosed: wording, buttons, categories, services, purposes, policy links and language. A new one is stored whenever any of that changes, including a changed translation. Records made before snapshots existed show "Unknown"; they are not reconstructed from current settings.', 'consentimental'); ?></p>
	<?php if ([] === $recent_snapshots) : ?>
		<p><?php esc_html_e('No snapshots stored yet. One is stored when configuration is published, when banner settings change, and when the banner is first shown in a language.', 'consentimental'); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Snapshot', 'consentimental'); ?></th>
					<th><?php esc_html_e('Config v.', 'consentimental'); ?></th>
					<th><?php esc_html_e('Language', 'consentimental'); ?></th>
					<th><?php esc_html_e('First stored', 'consentimental'); ?></th>
					<th><?php esc_html_e('Captured (UTC)', 'consentimental'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($recent_snapshots as $row) : ?>
					<tr>
						<td><a href="<?php echo esc_url(add_query_arg(['page' => \Consentimental\Admin\ConsentLogPage::PAGE_SLUG, 'snapshot' => (string) $row['snapshot_hash']], admin_url('admin.php'))); ?>"><code><?php echo esc_html(substr((string) $row['snapshot_hash'], 0, 12)); ?></code></a></td>
						<td><?php echo esc_html((string) $row['config_version']); ?></td>
						<td><?php echo esc_html(trim($row['locale'] . ' ' . $row['language_code'])); ?></td>
						<td><?php echo esc_html(\Consentimental\Admin\ConsentLogPage::source_label((string) $row['source'])); ?></td>
						<td><?php echo esc_html((string) $row['captured_at']); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
