<?php
/**
 * @var string $verification_type VerificationLog::TYPE_BROWSER|TYPE_VENDOR
 * @var int $config_version
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Diagnostics\VerificationLog;

$steps = VerificationLog::steps()[$verification_type];
$prefix = 'cmp-' . $verification_type;
?>
<form method="post" class="cmp-panel" id="cmp-verify-<?php echo esc_attr($verification_type); ?>">
	<?php wp_nonce_field('cmp_record_verification', 'cmp_verification_nonce'); ?>
	<input type="hidden" name="verification_type" value="<?php echo esc_attr($verification_type); ?>" />
	<fieldset>
		<legend><?php esc_html_e('Which steps did you complete and confirm?', 'consentimental'); ?></legend>
		<?php foreach ($steps as $key => $instruction) : ?>
			<label>
				<input type="checkbox" name="confirmed[]" value="<?php echo esc_attr($key); ?>" />
				<?php echo esc_html($instruction); ?>
			</label>
		<?php endforeach; ?>
	</fieldset>
	<fieldset>
		<legend><?php esc_html_e('Result', 'consentimental'); ?></legend>
		<label><input type="radio" name="verification_result" value="ok" checked /> <?php esc_html_e('Everything I checked behaved as described', 'consentimental'); ?></label>
		<label><input type="radio" name="verification_result" value="problem" /> <?php esc_html_e('I found a problem', 'consentimental'); ?></label>
	</fieldset>
	<p>
		<label for="<?php echo esc_attr($prefix); ?>-notes"><?php esc_html_e('Notes (browser, pages checked, what went wrong)', 'consentimental'); ?></label>
		<textarea id="<?php echo esc_attr($prefix); ?>-notes" name="notes" rows="3" class="large-text" maxlength="1000"></textarea>
	</p>
	<p class="description">
		<?php
		printf(
			/* translators: %d: configuration version */
			esc_html__('Recorded against configuration v%d with your user name and the current time. It stops counting as current evidence when the configuration is published again, or after 90 days.', 'consentimental'),
			(int) $config_version,
		);
		?>
	</p>
	<p><button type="submit" name="cmp_record_verification" value="1" class="button button-primary"><?php esc_html_e('Record result', 'consentimental'); ?></button></p>
</form>
