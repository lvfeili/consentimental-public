<?php
/**
 * @var string $banner_title
 * @var string $banner_text
 * @var string $customize_text
 * @var string $banner_logo_url
 * @var string $floating_button_icon
 * @var string $floating_button_icon_emoji
 * @var string $floating_button_icon_image_url
 * @var array<string, array<string, string>> $floating_button_positions
 * @var string $banner_color_bg
 * @var string $banner_color_text
 * @var string $banner_color_muted
 * @var string $banner_color_border
 * @var string $banner_color_primary
 * @var string $banner_color_primary_text
 * @var string $banner_color_focus
 * @var string $banner_radius
 * @var string $banner_position
 * @var string $banner_font_family
 * @var string $banner_font_size
 * @var string $button_shape
 * @var string $banner_logo_width
 * @var string $custom_css
 * @var array<string, mixed> $preview_content see templates/admin/partials/banner-preview.php
 * @var array<string, mixed> $preview_settings
 * @var bool $preview_has_fallback_policy
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Frontend\BannerStyle;
use Consentimental\Frontend\FloatingButtonIcon;

/**
 * @param array<string, string> $args
 */
$color_field = static function (string $id, string $name, string $var, string $value, string $label) {
	?>
	<tr>
		<th><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label></th>
		<td>
			<input
				type="text"
				id="<?php echo esc_attr($id); ?>"
				name="<?php echo esc_attr($name); ?>"
				class="cmp-color-field"
				data-cmp-var="<?php echo esc_attr($var); ?>"
				value="<?php echo esc_attr($value); ?>"
				data-default-color=""
			/>
		</td>
	</tr>
	<?php
};
?>
<div class="wrap cmp-wrap">
	<h1><?php esc_html_e('Banner', 'consentimental'); ?></h1>

	<?php if (isset($_GET['updated'])) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Saved.', 'consentimental'); ?></p></div>
	<?php endif; ?>

	<p>
		<?php esc_html_e('Leave a field blank to use the plugin\'s built-in default.', 'consentimental'); ?>
	</p>

	<div class="cmp-banner-layout cmp-with-preview">
		<div class="cmp-banner-layout__main">
			<form method="post">
				<?php wp_nonce_field('cmp_banner_save', 'cmp_banner_nonce'); ?>
				<h2><?php esc_html_e('Wording', 'consentimental'); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="cmp-banner-logo"><?php esc_html_e('Logo', 'consentimental'); ?></label></th>
						<td>
							<img
								id="cmp-banner-logo-preview"
								src="<?php echo esc_url($banner_logo_url); ?>"
								alt=""
								style="max-height: 48px; max-width: 200px; display: <?php echo '' !== $banner_logo_url ? 'block' : 'none'; ?>; margin-bottom: 8px;"
							/>
							<input type="hidden" id="cmp-banner-logo" name="banner_logo_url" value="<?php echo esc_attr($banner_logo_url); ?>" />
							<p>
								<button type="button" id="cmp-banner-logo-choose" class="button">
									<?php esc_html_e('Choose logo', 'consentimental'); ?>
								</button>
								<button type="button" id="cmp-banner-logo-remove" class="button" style="<?php echo '' === $banner_logo_url ? 'display:none;' : ''; ?>">
									<?php esc_html_e('Remove logo', 'consentimental'); ?>
								</button>
							</p>
							<p class="description"><?php esc_html_e('Optional. Shown at the top of the banner and the privacy settings dialog.', 'consentimental'); ?></p>
							<p>
								<label for="cmp-banner-logo-width"><?php esc_html_e('Width', 'consentimental'); ?></label>
								<input type="number" id="cmp-banner-logo-width" name="banner_logo_width" class="small-text cmp-style-field" data-cmp-var="--cmp-logo-width" data-cmp-unit="px" min="20" max="400" value="<?php echo esc_attr($banner_logo_width); ?>" placeholder="160" /> px
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="cmp-banner-title"><?php esc_html_e('Banner title', 'consentimental'); ?></label></th>
						<td>
							<input type="text" id="cmp-banner-title" name="banner_title" class="regular-text cmp-preview-text" data-cmp-preview-target="title" value="<?php echo esc_attr($banner_title); ?>" placeholder="<?php esc_attr_e('We value your privacy', 'consentimental'); ?>" />
						</td>
					</tr>
					<tr>
						<th><label for="cmp-banner-text"><?php esc_html_e('Banner text', 'consentimental'); ?></label></th>
						<td>
							<textarea id="cmp-banner-text" name="banner_text" class="large-text cmp-preview-text" data-cmp-preview-target="text" rows="4" placeholder="<?php esc_attr_e('We use cookies and similar technologies for necessary site functionality and, with your consent, for functional, analytics and marketing purposes. You can change your choice at any time.', 'consentimental'); ?>"><?php echo esc_textarea($banner_text); ?></textarea>
						</td>
					</tr>
					<tr>
						<th><label for="cmp-customize-text"><?php esc_html_e('Customize dialog text', 'consentimental'); ?></label></th>
						<td>
							<textarea id="cmp-customize-text" name="customize_text" class="large-text" rows="4" placeholder="<?php esc_attr_e('Choose which categories of cookies and similar technologies you allow, or select individual services below each category for more specific control. Necessary technologies cannot be switched off as they are required for the site to work.', 'consentimental'); ?>"><?php echo esc_textarea($customize_text); ?></textarea>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e('Floating button icon', 'consentimental'); ?></th>
						<td>
							<?php
							$icon_options = [
								'cookie' => __('Cookie', 'consentimental'),
								'fingerprint' => __('Fingerprint', 'consentimental'),
								'gear' => __('Gear', 'consentimental'),
								'emoji' => __('Custom emoji', 'consentimental'),
								'image' => __('Custom image', 'consentimental'),
							];
							?>
							<div class="cmp-icon-options" style="display: flex; flex-wrap: wrap; gap: 16px;">
								<?php foreach ($icon_options as $value => $label) : ?>
									<label class="cmp-icon-option" style="display: flex; align-items: center; gap: 6px;">
										<input type="radio" name="floating_button_icon" value="<?php echo esc_attr($value); ?>" <?php checked($floating_button_icon, $value); ?> data-cmp-toggle-icon />
										<?php if (in_array($value, ['cookie', 'fingerprint', 'gear'], true)) : ?>
											<span class="cmp-icon-option__preview" style="display: inline-flex; width: 22px; height: 22px;"><?php echo FloatingButtonIcon::render($value, '', ''); ?></span>
										<?php endif; ?>
										<?php echo esc_html($label); ?>
									</label>
								<?php endforeach; ?>
							</div>

							<p data-cmp-icon-row="emoji" style="margin-top: 8px;">
								<input type="text" id="cmp-icon-emoji" name="floating_button_icon_emoji" class="regular-text" maxlength="8" value="<?php echo esc_attr($floating_button_icon_emoji); ?>" placeholder="🔔" />
								<span class="description"><?php esc_html_e('Any single emoji or short text.', 'consentimental'); ?></span>
							</p>

							<div data-cmp-icon-row="image" style="margin-top: 8px;">
								<img
									id="cmp-icon-image-preview"
									src="<?php echo esc_url($floating_button_icon_image_url); ?>"
									alt=""
									style="max-height: 48px; max-width: 48px; display: <?php echo '' !== $floating_button_icon_image_url ? 'block' : 'none'; ?>; margin-bottom: 8px;"
								/>
								<input type="hidden" id="cmp-icon-image" name="floating_button_icon_image_url" value="<?php echo esc_attr($floating_button_icon_image_url); ?>" />
								<p>
									<button type="button" id="cmp-icon-image-choose" class="button">
										<?php esc_html_e('Choose image', 'consentimental'); ?>
									</button>
									<button type="button" id="cmp-icon-image-remove" class="button" style="<?php echo '' === $floating_button_icon_image_url ? 'display:none;' : ''; ?>">
										<?php esc_html_e('Remove image', 'consentimental'); ?>
									</button>
								</p>
								<p class="description"><?php esc_html_e('PNG, JPG, WebP, or SVG (SVG only appears here if your site already allows SVG uploads). Shown at 24×24px, so a square image works best.', 'consentimental'); ?></p>
							</div>
						</td>
					</tr>
					<?php foreach (['desktop' => __('Desktop', 'consentimental'), 'mobile' => __('Mobile (600px and narrower)', 'consentimental')] as $device => $device_label) : ?>
						<tr>
							<th scope="row"><?php echo esc_html(sprintf(__('Floating button position — %s', 'consentimental'), $device_label)); ?></th>
							<td>
								<p>
									<label for="cmp-floating-<?php echo esc_attr($device); ?>-side"><?php esc_html_e('Side', 'consentimental'); ?></label>
									<select id="cmp-floating-<?php echo esc_attr($device); ?>-side" name="floating_button_<?php echo esc_attr($device); ?>_side">
										<option value="right" <?php selected($floating_button_positions[$device]['side'], 'right'); ?>><?php esc_html_e('Right', 'consentimental'); ?></option>
										<option value="left" <?php selected($floating_button_positions[$device]['side'], 'left'); ?>><?php esc_html_e('Left', 'consentimental'); ?></option>
									</select>
								</p>
								<p>
									<label for="cmp-floating-<?php echo esc_attr($device); ?>-edge"><?php esc_html_e('Distance from selected side', 'consentimental'); ?></label>
									<input type="number" class="small-text" id="cmp-floating-<?php echo esc_attr($device); ?>-edge" name="floating_button_<?php echo esc_attr($device); ?>_edge" min="0" max="1000" value="<?php echo esc_attr($floating_button_positions[$device]['edge']); ?>" /> px
								</p>
								<p>
									<label for="cmp-floating-<?php echo esc_attr($device); ?>-bottom"><?php esc_html_e('Distance from bottom', 'consentimental'); ?></label>
									<input type="number" class="small-text" id="cmp-floating-<?php echo esc_attr($device); ?>-bottom" name="floating_button_<?php echo esc_attr($device); ?>_bottom" min="0" max="1000" value="<?php echo esc_attr($floating_button_positions[$device]['bottom']); ?>" /> px
								</p>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e('Appearance', 'consentimental'); ?></h2>
				<table class="form-table">
					<?php
					$color_field('cmp-color-bg', 'banner_color_bg', '--cmp-color-bg', $banner_color_bg, __('Background', 'consentimental'));
					$color_field('cmp-color-text', 'banner_color_text', '--cmp-color-text', $banner_color_text, __('Text', 'consentimental'));
					$color_field('cmp-color-muted', 'banner_color_muted', '--cmp-color-muted', $banner_color_muted, __('Muted text', 'consentimental'));
					$color_field('cmp-color-border', 'banner_color_border', '--cmp-color-border', $banner_color_border, __('Border', 'consentimental'));
					$color_field('cmp-color-primary', 'banner_color_primary', '--cmp-color-primary', $banner_color_primary, __('Primary button background', 'consentimental'));
					$color_field('cmp-color-primary-text', 'banner_color_primary_text', '--cmp-color-primary-text', $banner_color_primary_text, __('Primary button text', 'consentimental'));
					$color_field('cmp-color-focus', 'banner_color_focus', '--cmp-color-focus', $banner_color_focus, __('Focus outline', 'consentimental'));
					?>
					<tr>
						<th><label for="cmp-banner-position"><?php esc_html_e('Position', 'consentimental'); ?></label></th>
						<td>
							<select id="cmp-banner-position" name="banner_position" data-cmp-position-field>
								<option value="bottom_bar" <?php selected($banner_position, 'bottom_bar'); ?>><?php esc_html_e('Bottom bar', 'consentimental'); ?></option>
								<option value="top_bar" <?php selected($banner_position, 'top_bar'); ?>><?php esc_html_e('Top bar', 'consentimental'); ?></option>
								<option value="centered_modal" <?php selected($banner_position, 'centered_modal'); ?>><?php esc_html_e('Centered modal', 'consentimental'); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="cmp-banner-radius"><?php esc_html_e('Corner radius', 'consentimental'); ?></label></th>
						<td>
							<input type="number" id="cmp-banner-radius" name="banner_radius" class="small-text cmp-style-field" data-cmp-var="--cmp-radius" data-cmp-unit="px" min="0" max="40" value="<?php echo esc_attr($banner_radius); ?>" placeholder="8" /> px
						</td>
					</tr>
					<tr>
						<th><label for="cmp-button-shape"><?php esc_html_e('Button shape', 'consentimental'); ?></label></th>
						<td>
							<select id="cmp-button-shape" name="button_shape" data-cmp-shape-field>
								<option value="rounded" <?php selected($button_shape, 'rounded'); ?>><?php esc_html_e('Rounded (matches corner radius)', 'consentimental'); ?></option>
								<option value="pill" <?php selected($button_shape, 'pill'); ?>><?php esc_html_e('Pill', 'consentimental'); ?></option>
								<option value="square" <?php selected($button_shape, 'square'); ?>><?php esc_html_e('Square', 'consentimental'); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="cmp-banner-font-family"><?php esc_html_e('Font family', 'consentimental'); ?></label></th>
						<td>
							<input type="text" id="cmp-banner-font-family" name="banner_font_family" class="regular-text cmp-style-field" data-cmp-var="--cmp-font-family" value="<?php echo esc_attr($banner_font_family); ?>" placeholder="Georgia, serif" />
							<p class="description"><?php esc_html_e('Any valid CSS font-family value. Leave blank to match the site\'s default UI font stack.', 'consentimental'); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="cmp-banner-font-size"><?php esc_html_e('Font size', 'consentimental'); ?></label></th>
						<td>
							<input type="number" id="cmp-banner-font-size" name="banner_font_size" class="small-text cmp-style-field" data-cmp-var="--cmp-font-size" data-cmp-unit="px" min="11" max="24" value="<?php echo esc_attr($banner_font_size); ?>" placeholder="15" /> px
						</td>
					</tr>
					<tr>
						<th><label for="cmp-custom-css"><?php esc_html_e('Custom CSS', 'consentimental'); ?></label></th>
						<td>
							<textarea id="cmp-custom-css" name="custom_css" class="large-text code" data-cmp-custom-css rows="8" placeholder="<?php esc_attr_e('.cmp-banner__panel { ... }', 'consentimental'); ?>"><?php echo esc_textarea($custom_css); ?></textarea>
							<p class="description"><?php esc_html_e('Raw CSS, applied after everything above (and after this preview\'s own styles) — so it can override any of it. Only available to administrators.', 'consentimental'); ?></p>
						</td>
					</tr>
				</table>

				<p>
					<button type="submit" name="cmp_save_banner" value="1" class="button button-primary">
						<?php esc_html_e('Save', 'consentimental'); ?>
					</button>
				</p>
			</form>
		</div>

		<?php require __DIR__ . '/partials/banner-preview.php'; ?>
	</div>
</div>
<script>
(function () {
	function wireImagePicker(prefix, title, extraPreviewIds) {
		var frame;
		var input = document.getElementById(prefix);
		var preview = document.getElementById(prefix + '-preview');
		var chooseBtn = document.getElementById(prefix + '-choose');
		var removeBtn = document.getElementById(prefix + '-remove');
		var extras = (extraPreviewIds || []).map(function (id) { return document.getElementById(id); }).filter(Boolean);

		if (!input || !preview || !chooseBtn || !removeBtn) {
			return;
		}

		chooseBtn.addEventListener('click', function (e) {
			e.preventDefault();

			if (!frame) {
				frame = wp.media({ title: title, library: { type: 'image' }, multiple: false });

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					input.value = attachment.url;
					preview.src = attachment.url;
					preview.style.display = 'block';
					removeBtn.style.display = '';
					extras.forEach(function (el) { el.src = attachment.url; el.style.display = 'block'; });
				});
			}

			frame.open();
		});

		removeBtn.addEventListener('click', function (e) {
			e.preventDefault();
			input.value = '';
			preview.style.display = 'none';
			removeBtn.style.display = 'none';
			extras.forEach(function (el) { el.style.display = 'none'; });
		});
	}

	wireImagePicker('cmp-banner-logo', <?php echo wp_json_encode(__('Choose a logo', 'consentimental')); ?>, ['cmp-preview-logo']);
	wireImagePicker('cmp-icon-image', <?php echo wp_json_encode(__('Choose an icon image', 'consentimental')); ?>);

	var iconRadios = document.querySelectorAll('[data-cmp-toggle-icon]');
	function updateIconRows() {
		var checked = document.querySelector('[data-cmp-toggle-icon]:checked');
		var type = checked ? checked.value : 'cookie';
		document.querySelectorAll('[data-cmp-icon-row]').forEach(function (row) {
			row.style.display = row.getAttribute('data-cmp-icon-row') === type ? '' : 'none';
		});
	}
	iconRadios.forEach(function (r) { r.addEventListener('change', updateIconRows); });
	updateIconRows();
})();
</script>
