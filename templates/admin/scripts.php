<?php
/**
 * @var \Consentimental\Scripts\CustomScript[] $scripts
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Admin\ScriptsPage;

$add_url = admin_url('admin.php?page=' . ScriptsPage::PAGE_SLUG . '&action=add');
?>
<div class="wrap cmp-wrap">
	<h1>
		<?php esc_html_e('Scripts', 'consentimental'); ?>
		<a href="<?php echo esc_url($add_url); ?>" class="page-title-action"><?php esc_html_e('Add script', 'consentimental'); ?></a>
	</h1>

	<?php if (isset($_GET['updated'])) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Saved.', 'consentimental'); ?></p></div>
	<?php endif; ?>
	<?php if (isset($_GET['version_bumped'])) : ?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e('This adds, removes, or materially changes a script, which counts as a meaningful change: once published, visitors who already consented will be asked to decide again on their next visit. Nothing changes for visitors until you publish this change.', 'consentimental'); ?></p>
		</div>
	<?php endif; ?>

	<p>
		<?php esc_html_e('A simple way to add tracking or other third-party code without a full Google Tag Manager container. The plugin outputs and consent-gates it itself — it will not load or execute until the chosen category/service has consent.', 'consentimental'); ?>
	</p>

	<?php if ([] === $scripts) : ?>
		<p><?php esc_html_e('No custom scripts yet.', 'consentimental'); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Name', 'consentimental'); ?></th>
					<th><?php esc_html_e('Type', 'consentimental'); ?></th>
					<th><?php esc_html_e('Placement', 'consentimental'); ?></th>
					<th><?php esc_html_e('Category', 'consentimental'); ?></th>
					<th><?php esc_html_e('Condition', 'consentimental'); ?></th>
					<th><?php esc_html_e('Actions', 'consentimental'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($scripts as $script) : ?>
					<tr>
						<td><?php echo esc_html($script->name); ?></td>
						<td>
							<?php echo esc_html($script->type); ?>
							<?php if ($script->proxy) : ?>
								<span class="dashicons dashicons-shield" title="<?php esc_attr_e('Proxied through this site (ad-block bypass)', 'consentimental'); ?>"></span>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html($script->placement); ?></code></td>
						<td><code><?php echo esc_html($script->category); ?></code></td>
						<td>
							<code><?php echo esc_html($script->condition_type); ?></code>
							<?php if ('' !== $script->condition_value) : ?>
								: <code><?php echo esc_html($script->condition_value); ?></code>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url(admin_url('admin.php?page=' . ScriptsPage::PAGE_SLUG . '&action=edit&id=' . rawurlencode($script->id))); ?>">
								<?php esc_html_e('Edit', 'consentimental'); ?>
							</a>
							&nbsp;
							<form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js(__('Delete this script?', 'consentimental')); ?>');">
								<?php wp_nonce_field('cmp_scripts_save', 'cmp_scripts_nonce'); ?>
								<input type="hidden" name="id" value="<?php echo esc_attr($script->id); ?>" />
								<button type="submit" name="cmp_delete_script" value="1" class="button-link-delete">
									<?php esc_html_e('Delete', 'consentimental'); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
