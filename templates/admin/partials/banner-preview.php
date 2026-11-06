<?php
/**
 * @var array{title: string, text: string, labels: array<string, string>, policy_links: list<array{label: string, url: string}>} $preview_content
 *      BannerContent::resolve(false): stored wording with built-in defaults
 * @var array<string, mixed> $preview_settings Settings::all()
 * @var bool $preview_has_fallback_policy whether Settings → Privacy names a published page
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Frontend\BannerStyle;

$logo_url = (string) ($preview_settings['banner_logo_url'] ?? '');
$style_css = BannerStyle::css_variables($preview_settings);
?>
<div class="cmp-banner-preview">
	<h2 id="cmp-preview-heading"><?php esc_html_e('Preview', 'consentimental'); ?></h2>
	<div
		id="cmp-preview-viewport"
		class="cmp-preview-viewport"
		data-cmp-position="<?php echo esc_attr(BannerStyle::position($preview_settings)); ?>"
		role="img"
		aria-labelledby="cmp-preview-heading"
		aria-describedby="cmp-preview-note"
	>
		<div class="cmp-banner__panel" id="cmp-preview-panel">
			<img
				id="cmp-preview-logo"
				class="cmp-banner__logo"
				src="<?php echo esc_url($logo_url); ?>"
				alt=""
				<?php echo '' === $logo_url ? 'hidden' : ''; ?>
			/>
			<h3 class="cmp-banner__title" id="cmp-preview-title" data-cmp-default="<?php echo esc_attr(__('We value your privacy', 'consentimental')); ?>"><?php echo esc_html($preview_content['title']); ?></h3>
			<p class="cmp-banner__text" id="cmp-preview-text" data-cmp-default="<?php echo esc_attr(__('We use cookies and similar technologies for necessary site functionality and, with your consent, for functional, analytics and marketing purposes. You can change your choice at any time.', 'consentimental')); ?>"><?php echo esc_html($preview_content['text']); ?></p>
			<p class="cmp-policy-links" id="cmp-preview-policy-links" data-cmp-fallback-policy="<?php echo $preview_has_fallback_policy ? '1' : '0'; ?>">
				<?php foreach ($preview_content['policy_links'] as $link) : ?>
					<a href="<?php echo esc_url($link['url']); ?>" tabindex="-1" <?php echo __('Privacy policy', 'consentimental') === $link['label'] ? 'data-cmp-privacy-link' : ''; ?>><?php echo esc_html($link['label']); ?></a>
				<?php endforeach; ?>
			</p>
			<div class="cmp-banner__actions">
				<button type="button" class="cmp-btn cmp-btn--secondary" tabindex="-1"><?php echo esc_html($preview_content['labels']['reject_all']); ?></button>
				<button type="button" class="cmp-btn cmp-btn--secondary" tabindex="-1"><?php echo esc_html($preview_content['labels']['customize']); ?></button>
				<button type="button" class="cmp-btn cmp-btn--primary" tabindex="-1"><?php echo esc_html($preview_content['labels']['accept_all']); ?></button>
			</div>
		</div>
	</div>
	<p class="description" id="cmp-preview-note"><?php esc_html_e('Updates as you type, using the site’s real banner styles and your saved appearance. The floating button is not shown here.', 'consentimental'); ?></p>
	<?php if ('' !== $style_css) : ?>
		<style id="cmp-preview-saved-style"><?php echo $style_css; // Sanitized by BannerStyle. ?></style>
	<?php endif; ?>
	<style id="cmp-preview-custom-css"><?php echo BannerStyle::custom_css($preview_settings); // Administrator CSS, as on the frontend. ?></style>
</div>
