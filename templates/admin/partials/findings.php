<?php
/**
 * @var \Consentimental\Diagnostics\Finding[] $findings
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Diagnostics\Finding;

$status_labels = [
	Finding::PASSED => __('Passed', 'consentimental'),
	Finding::FAILED => __('Failed', 'consentimental'),
	Finding::WARNING => __('Warning', 'consentimental'),
	Finding::NOT_CHECKED => __('Not checked', 'consentimental'),
	Finding::NOT_APPLICABLE => __('Not applicable', 'consentimental'),
];
$evidence_labels = [
	Finding::EVIDENCE_AUTOMATED => __('Automated check on this server', 'consentimental'),
	Finding::EVIDENCE_ADMINISTRATOR => __('Reported by an administrator (not observed by the plugin)', 'consentimental'),
	Finding::EVIDENCE_NONE => __('No evidence', 'consentimental'),
];
$format_time = static fn (?int $time): string => null === $time
	? __('Never', 'consentimental')
	: wp_date(get_option('date_format') . ' ' . get_option('time_format'), $time);
?>
<ul class="cmp-findings">
	<?php foreach ($findings as $finding) : ?>
		<li class="cmp-finding cmp-finding--<?php echo esc_attr($finding->status); ?>" data-cmp-finding="<?php echo esc_attr($finding->id); ?>" data-cmp-status="<?php echo esc_attr($finding->status); ?>">
			<div class="cmp-finding__head">
				<span class="cmp-status cmp-status--<?php echo esc_attr($finding->status); ?>"><?php echo esc_html($status_labels[$finding->status] ?? $finding->status); ?></span>
				<h4 class="cmp-finding__title"><?php echo esc_html($finding->title); ?></h4>
			</div>
			<p class="cmp-finding__meta">
				<?php
				printf(
					/* translators: 1: date/time, 2: how the result was obtained */
					esc_html__('Checked: %1$s · %2$s', 'consentimental'),
					esc_html($format_time($finding->checked_at)),
					esc_html($evidence_labels[$finding->evidence] ?? $finding->evidence),
				);
				?>
				<br />
				<?php
				/* translators: %s: what the check covered */
				printf(esc_html__('Scope: %s', 'consentimental'), esc_html($finding->scope));
				?>
			</p>
			<?php if ('' !== $finding->problem) : ?>
				<dl>
					<dt><?php esc_html_e('Problem', 'consentimental'); ?></dt>
					<dd>
						<?php echo esc_html($finding->problem); ?>
						<?php if ([] !== $finding->items) : ?>
							<ul>
								<?php foreach (array_slice($finding->items, 0, 25, true) as $index => $item) : ?>
									<?php if (isset($finding->item_urls[$index])) : ?>
										<li>
											<a href="<?php echo esc_url($finding->item_urls[$index]); ?>">
												<?php
												/* translators: %s: item name, e.g. a service */
												printf(esc_html__('Edit %s', 'consentimental'), esc_html($item));
												?>
											</a>
										</li>
									<?php else : ?>
										<li><?php echo esc_html($item); ?></li>
									<?php endif; ?>
								<?php endforeach; ?>
								<?php if (count($finding->items) > 25) : ?>
									<li>
										<?php
										/* translators: %d: number of further items */
										printf(esc_html__('…and %d more', 'consentimental'), count($finding->items) - 25);
										?>
									</li>
								<?php endif; ?>
							</ul>
						<?php endif; ?>
					</dd>
					<dt><?php esc_html_e('Impact', 'consentimental'); ?></dt>
					<dd><?php echo esc_html($finding->impact); ?></dd>
					<dt><?php esc_html_e('Next step', 'consentimental'); ?></dt>
					<dd>
						<?php if ('' !== $finding->action_url) : ?>
							<a href="<?php echo esc_url($finding->action_url); ?>"><?php echo esc_html($finding->action); ?></a>
						<?php else : ?>
							<?php echo esc_html($finding->action); ?>
						<?php endif; ?>
					</dd>
				</dl>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
