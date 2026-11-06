<?php
/**
 * @var \Consentimental\Services\Service $service
 * @var \Consentimental\Consent\Category[] $categories
 * @var array{category?: string, purpose?: string, privacy_policy_url?: string, domains: string[], script_patterns: string[], iframe_patterns: string[], active?: bool}|null $override
 * @var \Consentimental\Services\Service|null $default the definition without any override
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Admin\ServicesPage;

$current_category = $override['category'] ?? $service->category;
$current_purpose = $override['purpose'] ?? $service->purpose;
$current_privacy_url = $override['privacy_policy_url'] ?? $service->privacy_policy_url;
$domains = $override['domains'] ?? $service->domains;
$script_patterns = $override['script_patterns'] ?? $service->script_patterns;
$iframe_patterns = $override['iframe_patterns'] ?? $service->iframe_patterns;
?>
<div class="wrap cmp-wrap">
	<h1>
		<?php
		printf(
			/* translators: %s: service name */
			esc_html__('Edit service: %s', 'consentimental'),
			esc_html($service->name),
		);
		?>
	</h1>

	<p>
		<?php esc_html_e('This service is defined in code (by this plugin, a theme or another plugin). Its name is fixed; everything below can be adjusted. Changes are staged until you publish.', 'consentimental'); ?>
	</p>

	<table class="form-table">
			<?php require __DIR__ . '/partials/sale-sharing-field.php'; ?>
		<tr>
			<th><?php esc_html_e('Provider', 'consentimental'); ?></th>
			<td><?php echo esc_html($service->provider); ?></td>
		</tr>
	</table>

	<form method="post">
		<?php wp_nonce_field('cmp_services_save', 'cmp_services_nonce'); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr($service->id); ?>" />

		<table class="form-table">
			<tr>
				<th><?php esc_html_e('Active', 'consentimental'); ?></th>
				<td>
					<label>
						<input type="checkbox" id="cmp-o-active" name="active" value="1" <?php checked($service->active); ?> />
						<?php esc_html_e('Disclose this service in the banner and count it for consent', 'consentimental'); ?>
					</label>
					<p class="description"><?php esc_html_e('Unchecked services are still detected and blocked wherever found (nothing is ever released without consent) — they just aren\'t shown yet. A freshly-registered built-in starts unchecked until you confirm it\'s actually in use here (the site scanner can help find these).', 'consentimental'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-o-category"><?php esc_html_e('Category', 'consentimental'); ?></label></th>
				<td>
					<select id="cmp-o-category" name="category">
						<?php foreach ($categories as $category) : ?>
							<option value="<?php echo esc_attr($category->slug); ?>" <?php selected($category->slug, $current_category); ?>>
								<?php echo esc_html($category->label); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php if ($service->category !== $current_category) : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: built-in default category label */
								esc_html__('Built-in default: %s', 'consentimental'),
								esc_html($service->category),
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-o-purpose"><?php esc_html_e('Purpose', 'consentimental'); ?></label></th>
				<td>
					<textarea id="cmp-o-purpose" name="purpose" class="large-text" rows="2" aria-describedby="cmp-o-purpose-help"><?php echo esc_textarea($current_purpose); ?></textarea>
					<p id="cmp-o-purpose-help" class="description"><?php esc_html_e('Shown to visitors under the service name. Leave empty to use the built-in text, if there is one.', 'consentimental'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-o-privacy-url"><?php esc_html_e('Privacy policy URL', 'consentimental'); ?></label></th>
				<td>
					<input type="url" id="cmp-o-privacy-url" name="privacy_policy_url" class="regular-text" value="<?php echo esc_attr($current_privacy_url); ?>" aria-describedby="cmp-o-privacy-help" />
					<p id="cmp-o-privacy-help" class="description"><?php esc_html_e('The provider’s privacy policy, linked from the banner. For a self-hosted tool, link your own privacy policy.', 'consentimental'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-o-domains"><?php esc_html_e('Domains', 'consentimental'); ?></label></th>
				<td>
					<textarea id="cmp-o-domains" name="domains" class="large-text code" rows="3"><?php echo esc_textarea(implode("\n", $domains)); ?></textarea>
					<p class="description"><?php esc_html_e('One per line. Matches anywhere in a script/iframe/image src.', 'consentimental'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-o-script-patterns"><?php esc_html_e('Script patterns', 'consentimental'); ?></label></th>
				<td><textarea id="cmp-o-script-patterns" name="script_patterns" class="large-text code" rows="2"><?php echo esc_textarea(implode("\n", $script_patterns)); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="cmp-o-iframe-patterns"><?php esc_html_e('Iframe patterns', 'consentimental'); ?></label></th>
				<td><textarea id="cmp-o-iframe-patterns" name="iframe_patterns" class="large-text code" rows="2"><?php echo esc_textarea(implode("\n", $iframe_patterns)); ?></textarea></td>
			</tr>
		</table>

		<p>
			<button type="submit" name="cmp_save_override" value="1" class="button button-primary">
				<?php esc_html_e('Save', 'consentimental'); ?>
			</button>
			<?php if (null !== $override) : ?>
				<button type="submit" name="cmp_reset_override" value="1" class="button">
					<?php esc_html_e('Reset to built-in default', 'consentimental'); ?>
				</button>
			<?php endif; ?>
			<a href="<?php echo esc_url(admin_url('admin.php?page=' . ServicesPage::PAGE_SLUG)); ?>" class="button">
				<?php esc_html_e('Cancel', 'consentimental'); ?>
			</a>
		</p>
	</form>
</div>
