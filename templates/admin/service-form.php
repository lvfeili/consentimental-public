<?php
/**
 * @var \Consentimental\Services\Service|null $service
 * @var \Consentimental\Consent\Category[] $categories
 * @var array{name?: string, domains?: string} $prefill
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Admin\ServicesPage;

$is_edit = null !== $service;
$cookie_lines = $is_edit
	? implode("\n", array_map(
		static fn (array $c): string => trim(($c['name'] ?? '') . '|' . ($c['duration'] ?? '') . '|' . ($c['purpose'] ?? ''), '|'),
		$service->cookies,
	))
	: '';
?>
<div class="wrap cmp-wrap">
	<h1><?php echo $is_edit ? esc_html__('Edit service', 'consentimental') : esc_html__('Add custom service', 'consentimental'); ?></h1>

	<form method="post">
		<?php wp_nonce_field('cmp_services_save', 'cmp_services_nonce'); ?>
		<?php if ($is_edit) : ?>
			<input type="hidden" name="id" value="<?php echo esc_attr($service->id); ?>" />
		<?php endif; ?>

		<table class="form-table">
			<?php require __DIR__ . '/partials/sale-sharing-field.php'; ?>
			<tr>
				<th><?php esc_html_e('Active', 'consentimental'); ?></th>
				<td>
					<label>
						<input type="checkbox" id="cmp-active" name="active" value="1" <?php checked($service?->active ?? true); ?> />
						<?php esc_html_e('Disclose this service in the banner and count it for consent', 'consentimental'); ?>
					</label>
					<p class="description"><?php esc_html_e('Unchecked services are still detected and blocked wherever found (nothing is ever released without consent) — they just aren\'t shown yet.', 'consentimental'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-name"><?php esc_html_e('Name', 'consentimental'); ?></label></th>
				<td><input type="text" id="cmp-name" name="name" class="regular-text" required value="<?php echo esc_attr($service?->name ?? ($prefill['name'] ?? '')); ?>" /></td>
			</tr>
			<tr>
				<th><label for="cmp-provider"><?php esc_html_e('Provider', 'consentimental'); ?></label></th>
				<td><input type="text" id="cmp-provider" name="provider" class="regular-text" value="<?php echo esc_attr($service?->provider ?? ''); ?>" /></td>
			</tr>
			<tr>
				<th><label for="cmp-purpose"><?php esc_html_e('Purpose', 'consentimental'); ?></label></th>
				<td><textarea id="cmp-purpose" name="purpose" class="large-text" rows="2"><?php echo esc_textarea($service?->purpose ?? ''); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="cmp-category"><?php esc_html_e('Category', 'consentimental'); ?></label></th>
				<td>
					<select id="cmp-category" name="category">
						<?php foreach ($categories as $category) : ?>
							<option value="<?php echo esc_attr($category->slug); ?>" <?php selected($service?->category ?? '', $category->slug); ?>>
								<?php echo esc_html($category->label); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-privacy-url"><?php esc_html_e('Privacy policy URL', 'consentimental'); ?></label></th>
				<td><input type="url" id="cmp-privacy-url" name="privacy_policy_url" class="regular-text" value="<?php echo esc_attr($service?->privacy_policy_url ?? ''); ?>" /></td>
			</tr>
			<tr>
				<th><label for="cmp-domains"><?php esc_html_e('Domains', 'consentimental'); ?></label></th>
				<td>
					<textarea id="cmp-domains" name="domains" class="large-text code" rows="3"><?php echo esc_textarea(implode("\n", $service?->domains ?? (isset($prefill['domains']) ? [$prefill['domains']] : []))); ?></textarea>
					<p class="description"><?php esc_html_e('One per line. Matches anywhere in a script/iframe/image src, e.g. analytics.example.com', 'consentimental'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-script-patterns"><?php esc_html_e('Script patterns', 'consentimental'); ?></label></th>
				<td>
					<textarea id="cmp-script-patterns" name="script_patterns" class="large-text code" rows="2"><?php echo esc_textarea(implode("\n", $service?->script_patterns ?? [])); ?></textarea>
					<p class="description"><?php esc_html_e('One per line. Also matched against inline <script> content, e.g. a filename like tracker.js', 'consentimental'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-iframe-patterns"><?php esc_html_e('Iframe patterns', 'consentimental'); ?></label></th>
				<td><textarea id="cmp-iframe-patterns" name="iframe_patterns" class="large-text code" rows="2"><?php echo esc_textarea(implode("\n", $service?->iframe_patterns ?? [])); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="cmp-cookies"><?php esc_html_e('Cookies', 'consentimental'); ?></label></th>
				<td>
					<textarea id="cmp-cookies" name="cookies" class="large-text code" rows="3"><?php echo esc_textarea($cookie_lines); ?></textarea>
					<p class="description"><?php esc_html_e('One per line: name|duration|purpose (duration and purpose optional), e.g. _my_tracker|30 days|Distinguishes visitors', 'consentimental'); ?></p>
				</td>
			</tr>
		</table>

		<p>
			<button type="submit" name="cmp_save_custom_service" value="1" class="button button-primary">
				<?php esc_html_e('Save', 'consentimental'); ?>
			</button>
			<a href="<?php echo esc_url(admin_url('admin.php?page=' . ServicesPage::PAGE_SLUG)); ?>" class="button">
				<?php esc_html_e('Cancel', 'consentimental'); ?>
			</a>
		</p>
	</form>
</div>
