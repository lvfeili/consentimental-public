<?php
/**
 * @var array{state: string, counts: array<string, int>} $summary
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

$headlines = [
	'problems' => __('Problems found', 'consentimental'),
	'incomplete' => __('Incomplete: some checks were not performed or need attention', 'consentimental'),
	'passed' => __('Every applicable check passed, including administrator-reported checks', 'consentimental'),
];
?>
<div class="cmp-summary cmp-summary--<?php echo esc_attr($summary['state']); ?>" role="status">
	<p class="cmp-summary__title"><strong><?php echo esc_html($headlines[$summary['state']]); ?></strong></p>
	<p>
		<?php
		printf(
			/* translators: 1-4: counts */
			esc_html__('%1$d failed · %2$d warnings · %3$d not checked · %4$d passed', 'consentimental'),
			(int) $summary['counts']['failed'],
			(int) $summary['counts']['warning'],
			(int) $summary['counts']['not_checked'],
			(int) $summary['counts']['passed'],
		);
		?>
	</p>
</div>
