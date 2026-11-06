<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
?>
<section class="cmp-california" data-cmp-california
	data-confirmed="<?php echo esc_attr($content['labels']['sale_confirmed']); ?>"
	data-gpc="<?php echo esc_attr($content['labels']['sale_gpc']); ?>"
	data-waiting="<?php echo esc_attr($content['labels']['sale_waiting']); ?>"
	data-error="<?php echo esc_attr($content['labels']['sale_error']); ?>">
	<p><?php echo esc_html($content['labels']['sale_description']); ?></p>
	<?php $policy_links($content['policy_links']); ?>
	<button type="button" class="cmp-btn cmp-btn--secondary" data-cmp-sale-opt-out><?php echo esc_html($content['labels']['sale_opt_out']); ?></button>
	<p role="status" aria-live="polite" data-cmp-sale-status></p>
	<button type="button" class="cmp-btn cmp-btn--secondary" data-cmp-sale-retry hidden><?php echo esc_html($content['labels']['sale_retry']); ?></button>
	<noscript><p><?php esc_html_e('Enable JavaScript to save a browser opt-out here, or contact the site through its privacy policy to exercise your rights.', 'consentimental'); ?></p></noscript>
</section>
