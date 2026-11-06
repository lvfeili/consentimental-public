<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}
?>
<button type="button" id="cmp-debug-toggle" class="cmp-debug-toggle" aria-expanded="false" aria-controls="cmp-debug-panel">
	<?php esc_html_e('CMP Debug', 'consentimental'); ?>
</button>

<div id="cmp-debug-panel" class="cmp-debug-panel" role="dialog" aria-label="<?php esc_attr_e('Consentimental diagnostics', 'consentimental'); ?>" hidden>
	<div class="cmp-debug-panel__header">
		<h2><?php esc_html_e('CMP Debug', 'consentimental'); ?></h2>
		<button type="button" class="cmp-debug-panel__close" aria-label="<?php esc_attr_e('Close', 'consentimental'); ?>">&times;</button>
	</div>
	<div class="cmp-debug-panel__body">
		<section>
			<h3><?php esc_html_e('Consent', 'consentimental'); ?></h3>
			<table id="cmp-debug-consent"></table>
		</section>

		<section>
			<h3><?php esc_html_e('Configuration', 'consentimental'); ?></h3>
			<dl id="cmp-debug-config"></dl>
		</section>

		<section>
			<h3><?php esc_html_e('Active services', 'consentimental'); ?></h3>
			<ul id="cmp-debug-services"></ul>
		</section>

		<section id="cmp-debug-consent-mode-section" hidden>
			<h3><?php esc_html_e('Google Consent Mode', 'consentimental'); ?></h3>
			<table id="cmp-debug-consent-mode"></table>
		</section>

		<section>
			<h3><?php esc_html_e('Blocked', 'consentimental'); ?></h3>
			<ul id="cmp-debug-blocked"></ul>
		</section>

		<section>
			<h3><?php esc_html_e('Released after consent', 'consentimental'); ?></h3>
			<ul id="cmp-debug-released"></ul>
		</section>

		<section>
			<h3><?php esc_html_e('Self-test', 'consentimental'); ?></h3>
			<div class="cmp-debug-panel__actions">
				<button type="button" data-cmp-debug-action="reset"><?php esc_html_e('Reset consent', 'consentimental'); ?></button>
				<button type="button" data-cmp-debug-action="none"><?php esc_html_e('Simulate no consent', 'consentimental'); ?></button>
				<button type="button" data-cmp-debug-action="analytics"><?php esc_html_e('Simulate analytics consent', 'consentimental'); ?></button>
				<button type="button" data-cmp-debug-action="marketing"><?php esc_html_e('Simulate marketing consent', 'consentimental'); ?></button>
				<button type="button" data-cmp-debug-action="all"><?php esc_html_e('Simulate all consent', 'consentimental'); ?></button>
				<button type="button" data-cmp-debug-action="reopen-banner"><?php esc_html_e('Reopen banner', 'consentimental'); ?></button>
			</div>
		</section>
	</div>
</div>
