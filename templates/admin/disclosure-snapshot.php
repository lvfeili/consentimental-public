<?php
/**
 * @var array<string, mixed>|null $snapshot
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Admin\ConsentLogPage;

$back = admin_url('admin.php?page=' . ConsentLogPage::PAGE_SLUG);
?>
<div class="wrap cmp-wrap">
	<h1><?php esc_html_e('Disclosure snapshot', 'consentimental'); ?></h1>
	<p><a href="<?php echo esc_url($back); ?>"><?php esc_html_e('Back to consent log', 'consentimental'); ?></a></p>

	<?php if (null === $snapshot) : ?>
		<div class="notice notice-error inline"><p><?php esc_html_e('No snapshot with this identifier is stored. It may have been removed by retention, or never existed.', 'consentimental'); ?></p></div>
	<?php else : ?>
		<?php $content = $snapshot['content']; ?>
		<?php if (!$snapshot['intact']) : ?>
			<div class="notice notice-error inline"><p><?php esc_html_e('The stored content no longer matches its identifier. It has been modified outside this plugin and should not be relied on.', 'consentimental'); ?></p></div>
		<?php endif; ?>
		<table class="form-table" role="presentation">
			<tr><th><?php esc_html_e('Identifier (SHA-256)', 'consentimental'); ?></th><td><code style="overflow-wrap: anywhere;"><?php echo esc_html((string) $snapshot['snapshot_hash']); ?></code></td></tr>
			<tr><th><?php esc_html_e('Configuration version', 'consentimental'); ?></th><td><?php echo esc_html((string) $snapshot['config_version']); ?></td></tr>
			<tr><th><?php esc_html_e('Configuration published (UTC)', 'consentimental'); ?></th><td><?php echo esc_html($snapshot['config_published_at'] ?: __('Unknown — published before version history was recorded', 'consentimental')); ?></td></tr>
			<tr><th><?php esc_html_e('Captured (UTC)', 'consentimental'); ?></th><td><?php echo esc_html((string) $snapshot['captured_at']); ?> — <?php echo esc_html(ConsentLogPage::source_label((string) $snapshot['source'])); ?></td></tr>
			<tr><th><?php esc_html_e('Language', 'consentimental'); ?></th><td><?php echo esc_html(trim(($content['language']['locale'] ?? '') . ' ' . ($content['language']['code'] ?? '') . ' (' . ($content['language']['direction'] ?? 'ltr') . ')')); ?></td></tr>
		</table>

		<h2><?php esc_html_e('Banner', 'consentimental'); ?></h2>
		<dl class="cmp-snapshot">
			<dt><?php esc_html_e('Title', 'consentimental'); ?></dt><dd><?php echo esc_html((string) ($content['banner']['title'] ?? '')); ?></dd>
			<dt><?php esc_html_e('Text', 'consentimental'); ?></dt><dd><?php echo esc_html((string) ($content['banner']['text'] ?? '')); ?></dd>
			<dt><?php esc_html_e('Settings dialog title', 'consentimental'); ?></dt><dd><?php echo esc_html((string) ($content['banner']['customize_title'] ?? '')); ?></dd>
			<dt><?php esc_html_e('Settings dialog text', 'consentimental'); ?></dt><dd><?php echo esc_html((string) ($content['banner']['customize_text'] ?? '')); ?></dd>
			<dt><?php esc_html_e('Choice labels', 'consentimental'); ?></dt><dd><?php echo esc_html(implode(' · ', (array) ($content['banner']['labels'] ?? []))); ?></dd>
			<dt><?php esc_html_e('Policy links', 'consentimental'); ?></dt>
			<dd>
				<?php if ([] === ($content['banner']['policy_links'] ?? [])) : ?>
					<?php esc_html_e('None shown', 'consentimental'); ?>
				<?php else : ?>
					<?php foreach ($content['banner']['policy_links'] as $link) : ?>
						<?php echo esc_html($link['label'] . ': ' . $link['url']); ?><br />
					<?php endforeach; ?>
				<?php endif; ?>
			</dd>
		</dl>

		<h2><?php esc_html_e('Categories and services', 'consentimental'); ?></h2>
		<?php foreach ((array) ($content['categories'] ?? []) as $category) : ?>
			<h3><?php echo esc_html($category['label']); ?> <code><?php echo esc_html($category['slug']); ?></code><?php echo $category['required'] ? ' — ' . esc_html__('always active', 'consentimental') : ''; ?></h3>
			<p><?php echo esc_html($category['description']); ?></p>
			<?php $in_category = array_filter((array) ($content['services'] ?? []), static fn ($service) => $service['category'] === $category['slug']); ?>
			<?php if ([] !== $in_category) : ?>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e('Service', 'consentimental'); ?></th><th><?php esc_html_e('Provider', 'consentimental'); ?></th><th><?php esc_html_e('Purpose', 'consentimental'); ?></th><th><?php esc_html_e('Cookies', 'consentimental'); ?></th><th><?php esc_html_e('Privacy policy', 'consentimental'); ?></th></tr></thead>
					<tbody>
						<?php foreach ($in_category as $service) : ?>
							<tr>
								<td><?php echo esc_html($service['name']); ?></td>
								<td><?php echo esc_html($service['provider']); ?></td>
								<td><?php echo esc_html($service['purpose']); ?></td>
								<td><?php echo esc_html(implode(', ', array_map(static fn ($cookie) => trim($cookie['name'] . ' ' . ('' !== $cookie['duration'] ? '(' . $cookie['duration'] . ')' : '')), $service['cookies']))); ?></td>
								<td><?php echo esc_html($service['privacy_policy_url']); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endforeach; ?>

		<h2><?php esc_html_e('Terms', 'consentimental'); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: number of days, 2: basic|advanced */
				esc_html__('Decision valid for %1$d days. Google Consent Mode: %2$s.', 'consentimental'),
				(int) ($content['terms']['consent_expiry_days'] ?? 0),
				esc_html((string) ($content['terms']['consent_mode_type'] ?? '')),
			);
			?>
		</p>

		<details>
			<summary><?php esc_html_e('Stored content (canonical JSON)', 'consentimental'); ?></summary>
			<pre style="white-space: pre-wrap; overflow-wrap: anywhere;"><?php echo esc_html((string) wp_json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
		</details>
	<?php endif; ?>
</div>
