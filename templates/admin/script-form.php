<?php
/**
 * @var \Consentimental\Scripts\CustomScript|null $script
 * @var \Consentimental\Consent\Category[] $categories
 * @var \Consentimental\Services\Service[] $services
 * @var array{name?: string, category?: string, service?: string} $prefill
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Admin\ScriptsPage;
use Consentimental\Scripts\CustomScript;
use Consentimental\Scripts\ScriptProxy;

$is_edit = null !== $script;
?>
<div class="wrap cmp-wrap">
	<h1><?php echo $is_edit ? esc_html__('Edit script', 'consentimental') : esc_html__('Add script', 'consentimental'); ?></h1>

	<form method="post">
		<?php wp_nonce_field('cmp_scripts_save', 'cmp_scripts_nonce'); ?>
		<?php if ($is_edit) : ?>
			<input type="hidden" name="id" value="<?php echo esc_attr($script->id); ?>" />
		<?php endif; ?>

		<table class="form-table">
			<tr>
				<th><label for="cmp-s-name"><?php esc_html_e('Name', 'consentimental'); ?></label></th>
				<td><input type="text" id="cmp-s-name" name="name" class="regular-text" required value="<?php echo esc_attr($script?->name ?? ($prefill['name'] ?? '')); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e('Type', 'consentimental'); ?></th>
				<td>
					<label>
						<input type="radio" name="type" value="<?php echo esc_attr(CustomScript::TYPE_URL); ?>" <?php checked($script?->type ?? CustomScript::TYPE_URL, CustomScript::TYPE_URL); ?> data-cmp-toggle-type />
						<?php esc_html_e('External URL', 'consentimental'); ?>
					</label>
					&nbsp;&nbsp;
					<label>
						<input type="radio" name="type" value="<?php echo esc_attr(CustomScript::TYPE_INLINE); ?>" <?php checked($script?->type ?? '', CustomScript::TYPE_INLINE); ?> data-cmp-toggle-type />
						<?php esc_html_e('Inline code', 'consentimental'); ?>
					</label>
				</td>
			</tr>
			<tr data-cmp-type-row="url">
				<th><label for="cmp-s-url"><?php esc_html_e('Script URL', 'consentimental'); ?></label></th>
				<td><input type="url" id="cmp-s-url" name="url" class="large-text code" value="<?php echo esc_attr($script?->url ?? ''); ?>" /></td>
			</tr>
			<tr data-cmp-type-row="url">
				<th><label for="cmp-s-attributes"><?php esc_html_e('Additional attributes', 'consentimental'); ?></label></th>
				<td>
					<textarea id="cmp-s-attributes" name="attributes" class="large-text code" rows="3"><?php echo esc_textarea($script?->attributes ?? ''); ?></textarea>
					<p class="description"><?php esc_html_e('Optional. One per line, e.g. data-website-id="630c3f94-db3d-42af-8d5d-b9b2a7bd4966" — for scripts that need attributes beyond src, such as Umami.', 'consentimental'); ?></p>
				</td>
			</tr>
			<tr data-cmp-type-row="url">
				<th><?php esc_html_e('Ad-block bypass', 'consentimental'); ?></th>
				<td>
					<label>
						<input type="checkbox" id="cmp-s-proxy" name="proxy" value="1" <?php checked($script?->proxy ?? false); ?> />
						<?php esc_html_e('Proxy this script through this site\'s own domain', 'consentimental'); ?>
					</label>
					<p class="description">
						<?php esc_html_e('Fetches the script (and, where the vendor supports it and is configured to use it, its runtime API calls) through this site instead of the vendor\'s domain — this is what most blocklists key on. Only takes effect after consent is granted, same as an unproxied script.', 'consentimental'); ?>
						<?php if ($is_edit && $script->proxy) : ?>
							<br /><?php
							printf(
								/* translators: %s: proxy URL */
								esc_html__('Proxy URL: %s', 'consentimental'),
								'<code>' . esc_html(ScriptProxy::base_url($script->id)) . '</code>'
							);
							?>
						<?php endif; ?>
					</p>
					<p>
						<label for="cmp-s-proxy-api-origin"><?php esc_html_e('Runtime API host (optional)', 'consentimental'); ?></label><br />
						<input type="url" id="cmp-s-proxy-api-origin" name="proxy_api_origin" class="regular-text code" value="<?php echo esc_attr($script?->proxy_api_origin ?? ''); ?>" placeholder="https://www.google-analytics.com" />
					</p>
					<p class="description">
						<?php esc_html_e('Only needed if the script\'s runtime calls go to a different host than the script file itself — e.g. GA4\'s gtag.js is served from googletagmanager.com, but its collection endpoint is www.google-analytics.com. Leave blank when they\'re the same host (most self-hosted analytics tools). Requires the vendor\'s supported routing configuration; changing this host alone does not reroute browser requests. See the setup guides.', 'consentimental'); ?>
					</p>
				</td>
			</tr>
            <tr data-cmp-type-row="url">
                <th><label for="cmp-proxy-paths"><?php esc_html_e('Allowed runtime endpoints', 'consentimental'); ?></label></th>
                <td>
                    <textarea id="cmp-proxy-paths" name="proxy_paths" class="large-text code" rows="5" placeholder="POST /api/send"><?php echo esc_textarea($script?->proxy_paths ?? ''); ?></textarea>
                    <p class="description"><?php esc_html_e('One method and exact path per line. GET, HEAD, and POST only. The configured script URL already permits GET/HEAD. Empty means script-only proxying. Existing installations retain their previous paths here; review them before publishing.', 'consentimental'); ?></p>
                    <p><a href="<?php echo esc_url(admin_url('admin.php?page=consentimental-proxy-health')); ?>"><?php esc_html_e('Proxy diagnostics and setup guides', 'consentimental'); ?></a></p>
                </td>
            </tr>
			<tr data-cmp-type-row="inline">
				<th><label for="cmp-s-code"><?php esc_html_e('Code', 'consentimental'); ?></label></th>
				<td>
					<textarea id="cmp-s-code" name="code" class="large-text code" rows="6"><?php echo esc_textarea($script?->code ?? ''); ?></textarea>
					<p class="description"><?php esc_html_e('Raw JavaScript, without surrounding <script> tags. Only available to administrators.', 'consentimental'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-s-placement"><?php esc_html_e('Placement', 'consentimental'); ?></label></th>
				<td>
					<select id="cmp-s-placement" name="placement">
						<option value="<?php echo esc_attr(CustomScript::PLACEMENT_HEAD); ?>" <?php selected($script?->placement ?? '', CustomScript::PLACEMENT_HEAD); ?>><?php esc_html_e('Head', 'consentimental'); ?></option>
						<option value="<?php echo esc_attr(CustomScript::PLACEMENT_BODY_START); ?>" <?php selected($script?->placement ?? '', CustomScript::PLACEMENT_BODY_START); ?>><?php esc_html_e('Body start', 'consentimental'); ?></option>
						<option value="<?php echo esc_attr(CustomScript::PLACEMENT_FOOTER); ?>" <?php selected($script?->placement ?? CustomScript::PLACEMENT_FOOTER, CustomScript::PLACEMENT_FOOTER); ?>><?php esc_html_e('Footer', 'consentimental'); ?></option>
					</select>
					<p class="description"><?php esc_html_e('Body start requires the active theme to call wp_body_open() — most modern themes do.', 'consentimental'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-s-category"><?php esc_html_e('Consent category', 'consentimental'); ?></label></th>
				<td>
					<select id="cmp-s-category" name="category">
						<?php foreach ($categories as $category) : ?>
							<option value="<?php echo esc_attr($category->slug); ?>" <?php selected($script?->category ?? ($prefill['category'] ?? ''), $category->slug); ?>>
								<?php echo esc_html($category->label); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-s-service"><?php esc_html_e('Service (optional)', 'consentimental'); ?></label></th>
				<td>
					<select id="cmp-s-service" name="service">
						<option value=""><?php esc_html_e('— none —', 'consentimental'); ?></option>
						<?php foreach ($services as $service) : ?>
							<option value="<?php echo esc_attr($service->id); ?>" <?php selected($script?->service ?? ($prefill['service'] ?? ''), $service->id); ?>>
								<?php echo esc_html($service->name); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e('Lets a visitor grant consent for just this service via a service-level override, in addition to its category.', 'consentimental'); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cmp-s-condition-type"><?php esc_html_e('Load on', 'consentimental'); ?></label></th>
				<td>
					<select id="cmp-s-condition-type" name="condition_type">
						<option value="<?php echo esc_attr(CustomScript::CONDITION_WHOLE_SITE); ?>" <?php selected($script?->condition_type ?? CustomScript::CONDITION_WHOLE_SITE, CustomScript::CONDITION_WHOLE_SITE); ?>><?php esc_html_e('Whole site', 'consentimental'); ?></option>
						<option value="<?php echo esc_attr(CustomScript::CONDITION_URL); ?>" <?php selected($script?->condition_type ?? '', CustomScript::CONDITION_URL); ?>><?php esc_html_e('Specific URL', 'consentimental'); ?></option>
						<option value="<?php echo esc_attr(CustomScript::CONDITION_URL_PATTERN); ?>" <?php selected($script?->condition_type ?? '', CustomScript::CONDITION_URL_PATTERN); ?>><?php esc_html_e('URL pattern', 'consentimental'); ?></option>
						<option value="<?php echo esc_attr(CustomScript::CONDITION_POST_TYPE); ?>" <?php selected($script?->condition_type ?? '', CustomScript::CONDITION_POST_TYPE); ?>><?php esc_html_e('Post type', 'consentimental'); ?></option>
					</select>
					<input type="text" name="condition_value" class="regular-text" value="<?php echo esc_attr($script?->condition_value ?? ''); ?>" placeholder="<?php esc_attr_e('e.g. /contact/, /blog/*, or post', 'consentimental'); ?>" />
				</td>
			</tr>
		</table>

		<p>
			<button type="submit" name="cmp_save_script" value="1" class="button button-primary">
				<?php esc_html_e('Save', 'consentimental'); ?>
			</button>
			<a href="<?php echo esc_url(admin_url('admin.php?page=' . ScriptsPage::PAGE_SLUG)); ?>" class="button">
				<?php esc_html_e('Cancel', 'consentimental'); ?>
			</a>
		</p>
	</form>

	<script>
	(function () {
		var radios = document.querySelectorAll('[data-cmp-toggle-type]');
		function update() {
			var type = document.querySelector('[data-cmp-toggle-type]:checked').value;
			document.querySelectorAll('[data-cmp-type-row]').forEach(function (row) {
				row.style.display = row.getAttribute('data-cmp-type-row') === type ? '' : 'none';
			});
		}
		radios.forEach(function (r) { r.addEventListener('change', update); });
		update();
	})();
	</script>
</div>
