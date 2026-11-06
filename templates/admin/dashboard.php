<?php
/**
 * @var \Consentimental\Consent\Category[] $categories
 * @var \Consentimental\Services\Service[] $services
 * @var string $consent_mode_type
 * @var int $consent_expiry_days
 * @var bool $floating_button_enabled
 * @var bool $require_decision_before_interaction
 * @var bool $reload_on_withdrawal
 * @var bool $delete_data_on_uninstall
 * @var string $legal_framework
 * @var bool $framework_enforced
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap cmp-wrap">
	<h1><?php esc_html_e('Consentimental', 'consentimental'); ?></h1>
	<p>
		<?php esc_html_e('Known services are recognised by domain and URL pattern and blocked until consent. Recognition only covers services the plugin knows about, and a service is only offered to visitors once you confirm it is in use.', 'consentimental'); ?>
	</p>
	<p class="cmp-actions">
		<a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=consentimental-setup')); ?>"><?php esc_html_e('Guided setup', 'consentimental'); ?></a>
		<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=consentimental-status')); ?>"><?php esc_html_e('Status & verification', 'consentimental'); ?></a>
	</p>

	<h2><?php esc_html_e('Legal framework', 'consentimental'); ?></h2>
	<form method="post">
		<?php wp_nonce_field('cmp_save_framework'); ?>
		<label for="cmp-framework"><?php esc_html_e('Consent profile for all visitors', 'consentimental'); ?></label>
		<select id="cmp-framework" name="legal_framework">
			<option value="custom" <?php selected('custom', $legal_framework); ?>><?php esc_html_e('Custom (no legal profile)', 'consentimental'); ?></option>
			<option value="eu_germany" <?php selected('eu_germany', $legal_framework); ?>><?php esc_html_e('EU / Germany — GDPR and TDDDG consent safeguards', 'consentimental'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('The EU / Germany profile enforces Basic Consent Mode, a visible privacy settings button, browsing before a decision, reload on script withdrawal, and explicit choices for optional services. Only the Necessary category can be required; all other categories start unchecked. These safeguards apply worldwide without geolocation.', 'consentimental'); ?></p>
		<p class="description"><?php esc_html_e('Choose the applicable framework with your legal adviser. This profile covers consent controls, not complete legal compliance. California sale/sharing controls can be enabled separately below. Review which services are genuinely necessary, provider disclosures and international transfers. Unknown integrations still need configuration and browser verification.', 'consentimental'); ?></p>
		<p class="description"><?php esc_html_e('Saving a different profile takes effect immediately and asks visitors to decide again. Purge any external page caches. Profile rules override conflicting settings below while selected.', 'consentimental'); ?></p>
		<button type="submit" name="cmp_save_framework" value="1" class="button button-primary"><?php esc_html_e('Save framework', 'consentimental'); ?></button>
	</form>

	<h2><?php esc_html_e('California sale/sharing controls', 'consentimental'); ?></h2>
	<form method="post">
		<?php wp_nonce_field('cmp_save_california'); ?>
		<p><label><input type="checkbox" name="california_enabled" value="1" <?php checked($california_enabled); ?> /> <?php esc_html_e('Enable California sale/sharing controls for all visitors', 'consentimental'); ?></label></p>
		<p><?php esc_html_e('Adds a sale/sharing opt-out and honors Global Privacy Control alongside your consent baseline. Basic Consent Mode, a visible settings button, browsing before a decision, and script-withdrawal reloads are enforced. Unreviewed services and managed elements without a service assignment remain blocked, including after Accept all.', 'consentimental'); ?></p>
		<p><strong><?php printf(esc_html__('%d active services still need sale/sharing classification.', 'consentimental'), count($unreviewed)); ?></strong>
		<a href="<?php echo esc_url(admin_url('admin.php?page=consentimental-services')); ?>"><?php esc_html_e('Review services', 'consentimental'); ?></a></p>
		<?php if ($unreviewed) : ?><p><?php echo esc_html(implode(', ', array_map(static fn ($service) => $service->name, $unreviewed))); ?></p><?php endif; ?>
		<p><label for="cmp-rights-page"><?php esc_html_e('Privacy rights request page', 'consentimental'); ?></label></p>
		<?php wp_dropdown_pages(['name' => 'privacy_rights_page_id', 'id' => 'cmp-rights-page', 'selected' => $privacy_rights_page_id, 'post_status' => 'publish', 'show_option_none' => __('None selected', 'consentimental'), 'option_none_value' => 0]); ?>
		<p class="description"><?php esc_html_e('Link to your process for access, deletion and correction requests. Backend transfers and account-wide preferences require integration with your systems. These controls do not establish complete CCPA compliance.', 'consentimental'); ?></p>
		<p class="description"><?php esc_html_e('Changes take effect immediately and renew cookie consent without clearing sale/sharing opt-outs. Service classifications are staged separately: review and publish them. Exclude the privacy-choice REST endpoint from caching and purge external page caches.', 'consentimental'); ?></p>
		<button class="button button-primary" type="submit" name="cmp_save_california" value="1"><?php esc_html_e('Save California controls', 'consentimental'); ?></button>
	</form>

	<h2><?php esc_html_e('General settings', 'consentimental'); ?></h2>
	<?php if ($framework_enforced || $california_enabled) : ?>
		<p><strong><?php esc_html_e('The enabled privacy controls enforce the disabled settings below.', 'consentimental'); ?></strong></p>
	<?php endif; ?>
	<form method="post">
		<?php wp_nonce_field('cmp_save_general_settings'); ?>
		<table class="form-table">
			<tr>
				<th><label for="cmp-expiry-days"><?php esc_html_e('Consent expiry', 'consentimental'); ?></label></th>
				<td>
					<input type="number" id="cmp-expiry-days" name="consent_expiry_days" min="1" class="small-text" value="<?php echo esc_attr((string) $consent_expiry_days); ?>" />
					<?php esc_html_e('days', 'consentimental'); ?>
					<p class="description"><?php esc_html_e('After this many days, a visitor is asked to decide again.', 'consentimental'); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e('Floating button', 'consentimental'); ?></th>
				<td>
					<label>
						<input type="checkbox" name="floating_button_enabled" value="1" <?php checked($floating_button_enabled); ?> <?php disabled($framework_enforced || $california_enabled); ?> />
						<?php esc_html_e('Show a floating button to reopen privacy settings', 'consentimental'); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e('Before a decision is made', 'consentimental'); ?></th>
				<td>
					<label>
						<input type="checkbox" name="require_decision_before_interaction" value="1" <?php checked($require_decision_before_interaction); ?> <?php disabled($framework_enforced || $california_enabled); ?> />
						<?php esc_html_e('Dim and block the rest of the page until the visitor accepts, rejects, or customizes', 'consentimental'); ?>
					</label>
					<p class="description">
						<?php esc_html_e('When off, the banner still appears and nothing consent-dependent loads either way, but the visitor can read and interact with the rest of the page immediately — relevant if you want to avoid anything resembling a "cookie wall". The Customize dialog itself still dims the page when open, since that is a dialog the visitor opened on purpose.', 'consentimental'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e('On withdrawal', 'consentimental'); ?></th>
				<td>
					<label>
						<input type="checkbox" name="reload_on_withdrawal" value="1" <?php checked($reload_on_withdrawal); ?> <?php disabled($framework_enforced || $california_enabled); ?> />
						<?php esc_html_e('Reload the page if withdrawing consent revoked something that had already started running', 'consentimental'); ?>
					</label>
					<p class="description">
						<?php esc_html_e('Cookies/storage are cleaned up and embeds are re-blocked either way. A script that already executed may keep timers or connections running until the page reloads. Reload is enabled by default and only happens if an affected script had actually run.', 'consentimental'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e('When the plugin is deleted', 'consentimental'); ?></th>
				<td>
					<label>
						<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked($delete_data_on_uninstall); ?> />
						<?php esc_html_e('Delete all plugin data, including the consent log and disclosure snapshots', 'consentimental'); ?>
					</label>
					<p class="description"><?php esc_html_e('Off by default, because consent records may be needed as evidence. Deactivating the plugin never deletes data. Export the consent log first if you might need it.', 'consentimental'); ?></p>
				</td>
			</tr>
		</table>
		<p>
			<button type="submit" name="cmp_save_general_settings" value="1" class="button button-primary">
				<?php esc_html_e('Save', 'consentimental'); ?>
			</button>
		</p>
	</form>

	<h2><?php esc_html_e('Categories', 'consentimental'); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e('Label', 'consentimental'); ?></th>
				<th><?php esc_html_e('Slug', 'consentimental'); ?></th>
				<th><?php esc_html_e('Required', 'consentimental'); ?></th>
				<th><?php esc_html_e('Default enabled', 'consentimental'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($categories as $category) : ?>
				<tr>
					<td><?php echo esc_html($category->label); ?></td>
					<td><code><?php echo esc_html($category->slug); ?></code></td>
					<td><?php echo $category->required ? esc_html__('Yes', 'consentimental') : esc_html__('No', 'consentimental'); ?></td>
					<td><?php echo $category->default_enabled ? esc_html__('Yes', 'consentimental') : esc_html__('No', 'consentimental'); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e('Services', 'consentimental'); ?></h2>
	<?php if ([] === $services) : ?>
		<p><?php esc_html_e('No services registered yet.', 'consentimental'); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Name', 'consentimental'); ?></th>
					<th><?php esc_html_e('Category', 'consentimental'); ?></th>
					<th><?php esc_html_e('Provider', 'consentimental'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($services as $service) : ?>
					<tr>
						<td><?php echo esc_html($service->name); ?></td>
						<td><code><?php echo esc_html($service->category); ?></code></td>
						<td><?php echo esc_html($service->provider); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e('Google Consent Mode', 'consentimental'); ?></h2>
	<form method="post">
		<?php wp_nonce_field('cmp_save_consent_mode'); ?>
		<fieldset <?php disabled($framework_enforced || $california_enabled); ?>>
			<legend class="screen-reader-text"><?php esc_html_e('Consent Mode type', 'consentimental'); ?></legend>
			<label style="display: block; margin-bottom: 8px;">
				<input type="radio" name="consent_mode_type" value="basic" <?php checked('basic', $consent_mode_type); ?> />
				<strong><?php esc_html_e('Basic', 'consentimental'); ?></strong>
				&mdash;
				<?php esc_html_e('Google Tag Manager itself does not load until consent exists. No request of any kind reaches Google before that.', 'consentimental'); ?>
			</label>
			<label style="display: block; margin-bottom: 8px;">
				<input type="radio" name="consent_mode_type" value="advanced" <?php checked('advanced', $consent_mode_type); ?> />
				<strong><?php esc_html_e('Advanced', 'consentimental'); ?></strong>
				&mdash;
				<?php esc_html_e('Google Tag Manager loads immediately under Consent Mode defaults. Google tags may send cookieless pings to Google before consent exists — this is different from complete blocking.', 'consentimental'); ?>
			</label>
		</fieldset>
		<p>
			<button type="submit" name="cmp_save_consent_mode" value="1" class="button button-primary">
				<?php esc_html_e('Save', 'consentimental'); ?>
			</button>
		</p>
	</form>
</div>
