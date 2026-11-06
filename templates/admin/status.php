<?php
/**
 * @var \Consentimental\Diagnostics\Finding[] $findings
 * @var array{state: string, counts: array<string, int>} $summary
 * @var list<array<string, mixed>> $verifications
 * @var int $config_version
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

use Consentimental\Diagnostics\Finding;
use Consentimental\Diagnostics\VerificationLog;

$all_findings = $findings;
$areas = [
	Finding::AREA_CONFIGURATION => [
		__('1. Configuration validation', 'consentimental'),
		__('Whether the published settings are consistent and complete. Checked automatically each time this page loads.', 'consentimental'),
	],
	Finding::AREA_DISCOVERY => [
		__('2. Discovery', 'consentimental'),
		__('What the last site scan found in your pages’ HTML. A scan cannot see everything; finding nothing is not proof that nothing is there.', 'consentimental'),
	],
	Finding::AREA_PROXY => [
		__('3. Proxy and upstream availability', 'consentimental'),
		__('Whether this server could reach the vendor for proxied scripts. A successful HTTP response here says nothing about consent enforcement or vendor-side processing.', 'consentimental'),
	],
	Finding::AREA_BROWSER => [
		__('4. Consent enforcement in the browser', 'consentimental'),
		__('Whether trackers really wait for consent in a visitor’s browser. Only a check in a real browser can show this.', 'consentimental'),
	],
	Finding::AREA_VENDOR => [
		__('5. Vendor-side receipt', 'consentimental'),
		__('Whether the vendor actually received data after consent. Only the vendor’s own dashboard can show this.', 'consentimental'),
	],
];
?>
<div class="wrap cmp-wrap">
	<h1><?php esc_html_e('Status & verification', 'consentimental'); ?></h1>

	<?php if (isset($_GET['recorded'])) : ?>
		<div class="notice notice-success is-dismissible" role="status"><p><?php esc_html_e('Verification result recorded.', 'consentimental'); ?></p></div>
	<?php endif; ?>

	<p><?php esc_html_e('Each area below proves something different. A passing result in one area is not evidence for another. The plugin does not certify legal compliance.', 'consentimental'); ?></p>

	<?php require __DIR__ . '/partials/summary.php'; ?>

	<?php foreach ($areas as $area => [$heading, $description]) : ?>
		<h2 id="cmp-area-<?php echo esc_attr($area); ?>"><?php echo esc_html($heading); ?></h2>
		<p><?php echo esc_html($description); ?></p>
		<?php
		$findings = array_values(array_filter($all_findings, static fn (Finding $finding): bool => $finding->area === $area));
		require __DIR__ . '/partials/findings.php';
		?>
		<?php if (Finding::AREA_BROWSER === $area || Finding::AREA_VENDOR === $area) : ?>
			<details>
				<summary><?php Finding::AREA_BROWSER === $area ? esc_html_e('Record a browser verification', 'consentimental') : esc_html_e('Record a vendor verification', 'consentimental'); ?></summary>
				<?php
				$verification_type = Finding::AREA_BROWSER === $area ? VerificationLog::TYPE_BROWSER : VerificationLog::TYPE_VENDOR;
				require __DIR__ . '/partials/verification-form.php';
				?>
			</details>
		<?php endif; ?>
	<?php endforeach; ?>

	<?php if ([] !== $verifications) : ?>
		<h2><?php esc_html_e('Verification history', 'consentimental'); ?></h2>
		<table class="widefat striped cmp-stack">
			<thead>
				<tr>
					<th><?php esc_html_e('When', 'consentimental'); ?></th>
					<th><?php esc_html_e('Type', 'consentimental'); ?></th>
					<th><?php esc_html_e('Result', 'consentimental'); ?></th>
					<th><?php esc_html_e('Config v.', 'consentimental'); ?></th>
					<th><?php esc_html_e('By', 'consentimental'); ?></th>
					<th><?php esc_html_e('Notes', 'consentimental'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($verifications as $entry) : ?>
					<tr>
						<td data-label="<?php esc_attr_e('When', 'consentimental'); ?>"><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $entry['checked_at'])); ?></td>
						<td data-label="<?php esc_attr_e('Type', 'consentimental'); ?>"><?php echo esc_html(VerificationLog::TYPE_BROWSER === $entry['type'] ? __('Browser', 'consentimental') : __('Vendor', 'consentimental')); ?></td>
						<td data-label="<?php esc_attr_e('Result', 'consentimental'); ?>"><?php echo esc_html(match ($entry['result']) { 'passed' => __('All steps confirmed', 'consentimental'), 'failed' => __('Problem found', 'consentimental'), default => __('Some steps confirmed', 'consentimental') }); ?></td>
						<td data-label="<?php esc_attr_e('Config v.', 'consentimental'); ?>"><?php echo esc_html((string) $entry['config_version']); ?></td>
						<td data-label="<?php esc_attr_e('By', 'consentimental'); ?>"><?php echo esc_html((string) $entry['user_login']); ?></td>
						<td data-label="<?php esc_attr_e('Notes', 'consentimental'); ?>"><?php echo esc_html((string) $entry['notes']); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
