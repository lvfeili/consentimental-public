<?php
/**
 * @var array<string, bool> $pending section => has a pending draft
 * @var bool $meaningful whether the staged changes require renewed consent
 * @var array<string, array{0: int, 1: int}> $counts section => [live count, draft count]
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

$section_labels = [
	'categories' => __('Categories', 'consentimental'),
	'custom_services' => __('Custom services', 'consentimental'),
	'service_overrides' => __('Built-in service overrides (category/activation/domains)', 'consentimental'),
	'custom_scripts' => __('Scripts', 'consentimental'),
];

$has_pending = in_array(true, $pending, true);
?>
<div class="wrap cmp-wrap">
	<h1><?php esc_html_e('Review & Publish', 'consentimental'); ?></h1>

	<p>
		<?php esc_html_e('Changes made on the Categories, Services and Scripts screens are staged here first — nothing on this site changes for a visitor, and no other admin screen shows the change, until you publish it.', 'consentimental'); ?>
	</p>

	<?php if (isset($_GET['published'])) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php if ('1' === ($_GET['version_bumped'] ?? '')) : ?>
					<?php esc_html_e('Published. This included a meaningful change, so visitors who already consented will be asked to decide again.', 'consentimental'); ?>
				<?php else : ?>
					<?php esc_html_e('Published. Nothing published required renewed consent.', 'consentimental'); ?>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if (isset($_GET['discarded'])) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Staged changes discarded.', 'consentimental'); ?></p></div>
	<?php endif; ?>

	<?php if (!$has_pending) : ?>
		<p><?php esc_html_e('Nothing staged right now — Categories, Services and Scripts all match what is currently published.', 'consentimental'); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Section', 'consentimental'); ?></th>
					<th><?php esc_html_e('Status', 'consentimental'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($section_labels as $section => $label) : ?>
					<?php if (!($pending[$section] ?? false)) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<tr>
						<td><?php echo esc_html($label); ?></td>
						<td>
							<?php if (isset($counts[$section])) : ?>
								<?php
								printf(
									/* translators: 1: currently published count, 2: staged count */
									esc_html__('Staged (%1$d published → %2$d staged)', 'consentimental'),
									(int) $counts[$section][0],
									(int) $counts[$section][1],
								);
								?>
							<?php else : ?>
								<?php esc_html_e('Staged', 'consentimental'); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p style="margin-top: 16px;">
			<?php if ($meaningful) : ?>
				<strong style="color: #b32d2e;">
					<?php esc_html_e('This includes a meaningful change (brief section 7): publishing will require visitors who already consented to decide again on their next visit.', 'consentimental'); ?>
				</strong>
			<?php else : ?>
				<?php esc_html_e('Nothing staged so far requires renewed consent — these look like cosmetic changes only.', 'consentimental'); ?>
			<?php endif; ?>
		</p>

		<form method="post" style="display:inline-block; margin-right: 8px;">
			<?php wp_nonce_field('cmp_publish', 'cmp_publish_nonce'); ?>
			<button type="submit" name="cmp_publish" value="1" class="button button-primary">
				<?php esc_html_e('Publish now', 'consentimental'); ?>
			</button>
		</form>

		<form method="post" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_js(__('Discard all staged changes? This cannot be undone.', 'consentimental')); ?>');">
			<?php wp_nonce_field('cmp_publish', 'cmp_publish_nonce'); ?>
			<button type="submit" name="cmp_discard" value="1" class="button">
				<?php esc_html_e('Discard changes', 'consentimental'); ?>
			</button>
		</form>
	<?php endif; ?>
</div>
