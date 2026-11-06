<?php
/**
 * @var \Consentimental\Services\Service[] $services
 * @var string[] $override_ids
 * @var array<string, string> $linked_scripts service id => script id
 * @var int $cookie_policy_page_id
 * @var bool $cookie_policy_page_exists
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Admin\ScannerPage;
use Consentimental\Admin\ScriptsPage;
use Consentimental\Admin\ServicesPage;

$add_url = admin_url('admin.php?page=' . ServicesPage::PAGE_SLUG . '&action=add');
?>
<div class="wrap cmp-wrap">
	<h1>
		<?php esc_html_e('Services', 'consentimental'); ?>
		<a href="<?php echo esc_url($add_url); ?>" class="page-title-action"><?php esc_html_e('Add custom service', 'consentimental'); ?></a>
	</h1>

	<?php if (isset($_GET['updated'])) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Saved.', 'consentimental'); ?></p></div>
	<?php endif; ?>
	<?php if (isset($_GET['version_bumped'])) : ?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e('This adds or removes a vendor, which counts as a meaningful change: once published, visitors who already consented will be asked to decide again on their next visit. Nothing changes for visitors until you publish this change.', 'consentimental'); ?></p>
		</div>
	<?php endif; ?>

	<p>
		<?php esc_html_e('Only Active services are disclosed in the banner and count for consent. Built-in services start inactive until confirmed actually in use here — activate one manually below, or run a site scan to find what\'s really present.', 'consentimental'); ?>
		<a href="<?php echo esc_url(admin_url('admin.php?page=' . ScannerPage::PAGE_SLUG)); ?>"><?php esc_html_e('Scan site', 'consentimental'); ?></a>
	</p>

	<div class="notice notice-info">
		<p>
			<?php if ($cookie_policy_page_exists) : ?>
				<?php esc_html_e('A Cookie Policy page listing every active service and its cookies has been created.', 'consentimental'); ?>
				<a href="<?php echo esc_url((string) get_edit_post_link($cookie_policy_page_id, 'raw')); ?>"><?php esc_html_e('Edit it', 'consentimental'); ?></a>
			<?php else : ?>
				<?php esc_html_e('You can generate a Cookie Policy page that lists every active service and its cookies, using the [cmp_services] shortcode.', 'consentimental'); ?>
			<?php endif; ?>
		</p>
		<?php if (!$cookie_policy_page_exists) : ?>
			<form method="post">
				<?php wp_nonce_field('cmp_services_save', 'cmp_services_nonce'); ?>
				<button type="submit" name="cmp_create_cookie_policy_page" value="1" class="button">
					<?php esc_html_e('Create Cookie Policy page', 'consentimental'); ?>
				</button>
			</form>
		<?php endif; ?>
	</div>

	<p><?php esc_html_e('Review each service’s California sale/sharing classification in its edit form, then publish. Unreviewed services stay blocked when California controls are enabled.', 'consentimental'); ?></p>
<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e('Active', 'consentimental'); ?></th>
				<th><?php esc_html_e('Name', 'consentimental'); ?></th>
				<th><?php esc_html_e('Category', 'consentimental'); ?></th>
				<th><?php esc_html_e('Provider', 'consentimental'); ?></th>
				<th><?php esc_html_e('Source', 'consentimental'); ?></th>
				<th><?php esc_html_e('Domains / patterns', 'consentimental'); ?></th>
				<th><?php esc_html_e('Cookies', 'consentimental'); ?></th>
				<th><?php esc_html_e('Actions', 'consentimental'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($services as $service) : ?>
				<?php
				$patterns = array_merge($service->domains, $service->script_patterns, $service->iframe_patterns);
				$is_overridden = $service->built_in && in_array($service->id, $override_ids, true);
				?>
				<tr>
					<td>
						<form method="post">
							<?php wp_nonce_field('cmp_services_save', 'cmp_services_nonce'); ?>
							<input type="hidden" name="id" value="<?php echo esc_attr($service->id); ?>" />
							<button type="submit" name="cmp_toggle_active" value="<?php echo $service->active ? '0' : '1'; ?>" class="button-link">
								<?php if ($service->active) : ?>
									<span style="color:#2271b1;">●</span> <?php esc_html_e('Active', 'consentimental'); ?>
								<?php else : ?>
									<span style="color:#c3c4c7;">○</span> <?php esc_html_e('Inactive', 'consentimental'); ?>
								<?php endif; ?>
							</button>
						</form>
					</td>
					<td><?php echo esc_html($service->name); ?> <small>(<?php echo esc_html(\Consentimental\Consent\CaliforniaPolicy::labels()[$service->sale_sharing]); ?>)</small></td>
					<td><code><?php echo esc_html($service->category); ?></code></td>
					<td><?php echo esc_html($service->provider); ?></td>
					<td>
						<?php echo $service->built_in ? esc_html__('Built-in', 'consentimental') : esc_html__('Custom', 'consentimental'); ?>
						<?php if ($is_overridden) : ?>
							&mdash; <em><?php esc_html_e('overridden', 'consentimental'); ?></em>
						<?php endif; ?>
					</td>
					<td><code><?php echo $patterns ? esc_html(implode(', ', $patterns)) : '&mdash;'; ?></code></td>
					<td><?php echo $service->cookies ? esc_html(implode(', ', array_column($service->cookies, 'name'))) : '&mdash;'; ?></td>
					<td>
						<?php if ($service->built_in) : ?>
							<a href="<?php echo esc_url(admin_url('admin.php?page=' . ServicesPage::PAGE_SLUG . '&action=override&id=' . rawurlencode($service->id))); ?>">
								<?php esc_html_e('Edit', 'consentimental'); ?>
							</a>
						<?php else : ?>
							<a href="<?php echo esc_url(admin_url('admin.php?page=' . ServicesPage::PAGE_SLUG . '&action=edit&id=' . rawurlencode($service->id))); ?>">
								<?php esc_html_e('Edit', 'consentimental'); ?>
							</a>
							&nbsp;
							<form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js(__('Delete this service?', 'consentimental')); ?>');">
								<?php wp_nonce_field('cmp_services_save', 'cmp_services_nonce'); ?>
								<input type="hidden" name="id" value="<?php echo esc_attr($service->id); ?>" />
								<button type="submit" name="cmp_delete_custom_service" value="1" class="button-link-delete">
									<?php esc_html_e('Delete', 'consentimental'); ?>
								</button>
							</form>
						<?php endif; ?>
						&nbsp;
						<?php if (isset($linked_scripts[$service->id])) : ?>
							<a href="<?php echo esc_url(admin_url('admin.php?page=' . ScriptsPage::PAGE_SLUG . '&action=edit&id=' . rawurlencode($linked_scripts[$service->id]))); ?>">
								<?php esc_html_e('Manage script', 'consentimental'); ?>
							</a>
						<?php else : ?>
							<a href="<?php echo esc_url(admin_url('admin.php?page=' . ScriptsPage::PAGE_SLUG . '&action=add&service=' . rawurlencode($service->id))); ?>">
								<?php esc_html_e('Add script', 'consentimental'); ?>
							</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
