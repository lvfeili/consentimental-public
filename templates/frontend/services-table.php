<?php
/**
 * @var \Consentimental\Services\Service[] $services
 * @var int $consent_log_retention_days
 * @var bool $consent_log_enabled
 * @var array<string, string> $category_labels
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}
?>
<table class="cmp-services-table">
	<caption class="screen-reader-text"><?php esc_html_e('Services and cookies used on this site', 'consentimental'); ?></caption>
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e('Service', 'consentimental'); ?></th>
			<th scope="col"><?php esc_html_e('Provider', 'consentimental'); ?></th>
			<th scope="col"><?php esc_html_e('Purpose', 'consentimental'); ?></th>
			<th scope="col"><?php esc_html_e('Category', 'consentimental'); ?></th>
			<th scope="col"><?php esc_html_e('Cookies', 'consentimental'); ?></th>
			<th scope="col"><?php esc_html_e('Duration', 'consentimental'); ?></th>
			<th scope="col"><?php esc_html_e('Privacy policy', 'consentimental'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ([] === $services) : ?>
			<tr>
				<td colspan="7"><?php esc_html_e('No services are currently configured.', 'consentimental'); ?></td>
			</tr>
		<?php endif; ?>
		<?php foreach ($services as $service) : ?>
			<tr>
				<td><?php echo esc_html($service->name); ?></td>
				<td><?php echo esc_html($service->provider); ?></td>
				<td><?php echo esc_html($service->purpose); ?></td>
				<td><?php echo esc_html($category_labels[$service->category] ?? $service->category); ?></td>
				<td><?php echo esc_html(implode(', ', array_column($service->cookies, 'name'))); ?></td>
				<td><?php echo esc_html(implode(', ', array_unique(array_column($service->cookies, 'duration')))); ?></td>
				<td>
					<?php if ('' !== $service->privacy_policy_url) : ?>
						<a href="<?php echo esc_url($service->privacy_policy_url); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e('View', 'consentimental'); ?>
						</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php if ($consent_log_enabled) : ?>
<p class="cmp-services-table__consent-log-notice">
	<?php
	printf(
		/* translators: %d: number of days consent records are kept */
		esc_html__('When you make a cookie consent choice on this site, we record your anonymous consent ID, the choice you made, and the time, so we can demonstrate that consent was given if asked. This record is kept for up to %d days and is not shared with third parties.', 'consentimental'),
		(int) $consent_log_retention_days,
	);
	?>
</p>
<?php endif; ?>
