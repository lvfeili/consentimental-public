<?php
/**
 * @var \Consentimental\Consent\Category[] $categories
 * @var string[] $protected_slugs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap cmp-wrap">
	<h1><?php esc_html_e('Categories', 'consentimental'); ?></h1>

	<?php if (isset($_GET['updated'])) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Saved.', 'consentimental'); ?></p></div>
	<?php endif; ?>
	<?php if (isset($_GET['version_bumped'])) : ?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e('This adds or removes a category, which counts as a meaningful change: once published, visitors who already consented will be asked to decide again on their next visit. Nothing changes for visitors until you publish this change.', 'consentimental'); ?></p>
		</div>
	<?php endif; ?>

	<p>
		<?php esc_html_e('Necessary, Functional, Analytics and Marketing cannot be deleted — every built-in service is registered against one of these. Their order, label and description can still be changed. Adding or removing a category asks visitors to make a new consent decision; renaming or re-describing one does not.', 'consentimental'); ?>
	</p>

	<form method="post">
		<?php wp_nonce_field('cmp_categories_save', 'cmp_categories_nonce'); ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width: 70px;"><?php esc_html_e('Order', 'consentimental'); ?></th>
					<th><?php esc_html_e('Label', 'consentimental'); ?></th>
					<th><?php esc_html_e('Slug', 'consentimental'); ?></th>
					<th><?php esc_html_e('Description', 'consentimental'); ?></th>
					<th><?php esc_html_e('Required', 'consentimental'); ?></th>
					<th><?php esc_html_e('Actions', 'consentimental'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($categories as $index => $category) : ?>
					<?php $is_protected = in_array($category->slug, $protected_slugs, true); ?>
					<tr>
						<td>
							<input type="hidden" name="slug[]" value="<?php echo esc_attr($category->slug); ?>" />
							<input type="number" name="order[]" value="<?php echo esc_attr((string) $category->order); ?>" class="small-text" />
						</td>
						<td><input type="text" name="label[]" value="<?php echo esc_attr($category->label); ?>" class="regular-text" required /></td>
						<td><code><?php echo esc_html($category->slug); ?></code></td>
						<td><input type="text" name="description[]" value="<?php echo esc_attr($category->description); ?>" class="large-text" /></td>
						<td>
							<input
								type="checkbox"
								name="required[<?php echo esc_attr((string) $index); ?>]"
								value="1"
								<?php checked($category->required); ?>
								<?php disabled('necessary' === $category->slug); ?>
							/>
						</td>
						<td>
							<?php if (!$is_protected) : ?>
								<button type="submit" form="cmp-delete-<?php echo esc_attr($category->slug); ?>" class="button-link-delete">
									<?php esc_html_e('Delete', 'consentimental'); ?>
								</button>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="submit" name="cmp_save_categories" value="1" class="button button-primary">
				<?php esc_html_e('Save order & details', 'consentimental'); ?>
			</button>
		</p>
	</form>

	<?php foreach ($categories as $category) : ?>
		<?php if (!in_array($category->slug, $protected_slugs, true)) : ?>
			<form method="post" id="cmp-delete-<?php echo esc_attr($category->slug); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this category? Any service assigned to it will need reassigning.', 'consentimental')); ?>');">
				<?php wp_nonce_field('cmp_categories_save', 'cmp_categories_nonce'); ?>
				<input type="hidden" name="slug" value="<?php echo esc_attr($category->slug); ?>" />
				<input type="hidden" name="cmp_delete_category" value="1" />
			</form>
		<?php endif; ?>
	<?php endforeach; ?>

	<h2><?php esc_html_e('Add category', 'consentimental'); ?></h2>
	<form method="post">
		<?php wp_nonce_field('cmp_categories_save', 'cmp_categories_nonce'); ?>
		<table class="form-table">
			<tr>
				<th><label for="cmp-new-slug"><?php esc_html_e('Slug', 'consentimental'); ?></label></th>
				<td>
					<input type="text" id="cmp-new-slug" name="new_slug" class="regular-text" pattern="[a-z0-9_-]+" required />
					<p class="description"><?php esc_html_e('Lowercase letters, numbers, dashes and underscores only — this is the identifier services register against, and cannot be changed later.', 'consentimental'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-new-label"><?php esc_html_e('Label', 'consentimental'); ?></label></th>
				<td><input type="text" id="cmp-new-label" name="new_label" class="regular-text" required /></td>
			</tr>
			<tr>
				<th><label for="cmp-new-description"><?php esc_html_e('Description', 'consentimental'); ?></label></th>
				<td><input type="text" id="cmp-new-description" name="new_description" class="large-text" /></td>
			</tr>
		</table>
		<p>
			<button type="submit" name="cmp_add_category" value="1" class="button button-primary">
				<?php esc_html_e('Add category', 'consentimental'); ?>
			</button>
		</p>
	</form>
</div>
