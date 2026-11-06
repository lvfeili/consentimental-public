<?php
/**
 * @var \Consentimental\Consent\Category[] $categories
 * @var bool $floating_button_enabled
 * @var array<string, \Consentimental\Services\Service[]> $services_by_category
 * @var array{title: string, text: string, customize_title: string, customize_text: string, labels: array<string, string>, policy_links: list<array{label: string, url: string}>} $content
 * @var string $banner_logo_url
 * @var string $floating_button_icon
 * @var string $floating_button_icon_emoji
 * @var string $floating_button_icon_image_url
 * @var string $banner_style_css
 * @var string $floating_button_css
 * @var string $banner_position
 * @var string $banner_custom_css
 * @var bool $preview administrator preview: staged content, nothing saved
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Frontend\FloatingButtonIcon;
?>
<?php
$policy_links = static function (array $links): void {
	if ([] === $links) {
		return;
	}
	?>
	<p class="cmp-policy-links">
		<?php foreach ($links as $link) : ?>
			<a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a>
		<?php endforeach; ?>
	</p>
	<?php
};
?>
<?php if ('' !== $banner_style_css || '' !== $floating_button_css || '' !== $banner_custom_css) : ?>
	<style id="cmp-banner-style"><?php echo $banner_style_css . "\n" . $floating_button_css . "\n" . $banner_custom_css; ?></style>
<?php endif; ?>
<div id="cmp-banner" class="cmp-banner" role="dialog" aria-modal="true" aria-labelledby="cmp-banner-title" aria-describedby="cmp-banner-text" data-cmp-position="<?php echo esc_attr($banner_position); ?>" hidden>
	<div class="cmp-banner__panel">
		<?php if ($preview) : ?>
			<p class="cmp-preview-note" role="note"><?php esc_html_e('Preview for administrators. Choices made here are not saved.', 'consentimental'); ?></p>
		<?php endif; ?>
		<?php if ('' !== $banner_logo_url) : ?>
			<img src="<?php echo esc_url($banner_logo_url); ?>" alt="" class="cmp-banner__logo" />
		<?php endif; ?>
		<h2 id="cmp-banner-title" class="cmp-banner__title">
			<?php echo esc_html($content['title']); ?>
		</h2>
		<p class="cmp-banner__text" id="cmp-banner-text">
			<?php echo esc_html($content['text']); ?>
		</p>
		<?php $policy_links($content['policy_links']); ?>
		<div class="cmp-banner__actions">
			<button type="button" class="cmp-btn cmp-btn--secondary" data-cmp-action="reject-all">
				<?php echo esc_html($content['labels']['reject_all']); ?>
			</button>
			<button type="button" class="cmp-btn cmp-btn--secondary" data-cmp-action="customize">
				<?php echo esc_html($content['labels']['customize']); ?>
			</button>
			<button type="button" class="cmp-btn cmp-btn--primary" data-cmp-action="accept-all">
				<?php echo esc_html($content['labels']['accept_all']); ?>
			</button>
		</div>
	</div>
</div>

<div id="cmp-customize" class="cmp-customize" role="dialog" aria-modal="true" aria-labelledby="cmp-customize-title" hidden>
	<div class="cmp-customize__panel">
		<?php if ('' !== $banner_logo_url) : ?>
			<img src="<?php echo esc_url($banner_logo_url); ?>" alt="" class="cmp-customize__logo" />
		<?php endif; ?>
		<h2 id="cmp-customize-title" class="cmp-customize__title"><?php echo esc_html($content['customize_title']); ?></h2>
		<p class="cmp-customize__text">
			<?php echo esc_html($content['customize_text']); ?>
		</p>
		<?php $policy_links($content['policy_links']); ?>
		<?php if (isset($content['labels']['sale_opt_out'])) { require __DIR__ . '/california.php'; } ?>
		<ul class="cmp-customize__categories">
			<?php foreach ($categories as $category) : ?>
				<?php $category_services = $services_by_category[$category->slug] ?? []; ?>
				<li class="cmp-customize__category">
					<div class="cmp-customize__category-header">
						<label class="cmp-toggle" for="cmp-category-<?php echo esc_attr($category->slug); ?>">
							<input
								type="checkbox"
								id="cmp-category-<?php echo esc_attr($category->slug); ?>"
								class="cmp-toggle__input"
								data-cmp-category-toggle="<?php echo esc_attr($category->slug); ?>"
								<?php checked($category->required); ?>
								<?php disabled($category->required); ?>
							/>
							<span class="cmp-toggle__track" aria-hidden="true"></span>
							<span class="cmp-toggle__label"><?php echo esc_html($category->label); ?></span>
						</label>
						<?php if ($category->required) : ?>
							<span class="cmp-customize__badge"><?php echo esc_html($content['labels']['always_active']); ?></span>
						<?php endif; ?>
					</div>
					<?php if ('' !== $category->description) : ?>
						<p class="cmp-customize__description"><?php echo esc_html($category->description); ?></p>
					<?php endif; ?>

					<?php if ([] !== $category_services) : ?>
						<details class="cmp-customize__services-details">
							<summary class="cmp-customize__services-summary">
								<?php
								printf(
									/* translators: %d: number of individual services in this category */
									esc_html(_n('%d individual service', '%d individual services', count($category_services), 'consentimental')),
									count($category_services),
								);
								?>
							</summary>
							<ul
								class="cmp-customize__services"
								role="group"
								aria-label="<?php echo esc_attr(sprintf(
									/* translators: %s: category label, e.g. "Marketing" */
									__('Individual %s services', 'consentimental'),
									$category->label,
								)); ?>"
							>
								<?php foreach ($category_services as $service) : ?>
									<li class="cmp-customize__service">
										<label class="cmp-toggle cmp-toggle--small" for="cmp-service-<?php echo esc_attr($service->id); ?>">
											<input
												type="checkbox"
												id="cmp-service-<?php echo esc_attr($service->id); ?>"
												class="cmp-toggle__input"
												data-cmp-service-toggle="<?php echo esc_attr($service->id); ?>"
												data-cmp-service-category="<?php echo esc_attr($category->slug); ?>"
												<?php checked($category->required); ?>
												<?php disabled($category->required); ?>
											/>
											<span class="cmp-toggle__track" aria-hidden="true"></span>
											<span class="cmp-toggle__label"><?php echo esc_html($service->name); ?></span>
										</label>
										<?php if ('' !== $service->purpose) : ?>
											<p class="cmp-customize__service-purpose"><?php echo esc_html($service->purpose); ?></p>
										<?php endif; ?>
										<?php if ([] !== $service->cookies) : ?>
											<p class="cmp-customize__service-cookies">
												<?php
												$cookie_labels = array_map(
													static function (array $cookie): string {
														return '' !== ($cookie['duration'] ?? '')
															? sprintf('%s (%s)', $cookie['name'], $cookie['duration'])
															: $cookie['name'];
													},
													$service->cookies,
												);
												printf(
													/* translators: %s: comma-separated list of cookie names, each optionally followed by its duration */
													esc_html__('Cookies: %s', 'consentimental'),
													esc_html(implode(', ', $cookie_labels)),
												);
												?>
											</p>
										<?php endif; ?>
										<?php if ('' !== $service->privacy_policy_url) : ?>
											<p class="cmp-customize__service-privacy">
												<a href="<?php echo esc_url($service->privacy_policy_url); ?>" target="_blank" rel="noopener noreferrer">
													<?php
													printf(
														/* translators: %s: provider name, e.g. "Google" */
														esc_html__('%s privacy policy', 'consentimental'),
														esc_html('' !== $service->provider ? $service->provider : $service->name),
													);
													?>
												</a>
											</p>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<div class="cmp-customize__actions">
			<button type="button" class="cmp-btn cmp-btn--secondary" data-cmp-action="reject-all">
				<?php echo esc_html($content['labels']['reject_all']); ?>
			</button>
			<button type="button" class="cmp-btn cmp-btn--secondary" data-cmp-action="accept-all">
				<?php echo esc_html($content['labels']['accept_all']); ?>
			</button>
			<button type="button" class="cmp-btn cmp-btn--primary" data-cmp-action="save">
				<?php echo esc_html($content['labels']['save']); ?>
			</button>
		</div>
	</div>
</div>

<div class="cmp-backdrop" data-cmp-backdrop hidden></div>

<?php if ($floating_button_enabled) : ?>
	<button
		type="button"
		id="cmp-floating-button"
		class="cmp-floating-button"
		data-cmp-open-settings
		aria-label="<?php esc_attr_e('Open privacy settings', 'consentimental'); ?>"
		hidden
	><?php echo FloatingButtonIcon::render($floating_button_icon, $floating_button_icon_emoji, $floating_button_icon_image_url); ?></button>
<?php endif; ?>

<?php if (isset($content['labels']['sale_opt_out'])) { require __DIR__ . '/california.php'; } ?>
