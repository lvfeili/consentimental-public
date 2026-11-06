<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
?>
<tr>
	<th><label for="cmp-sale-sharing"><?php esc_html_e('California sale/sharing', 'consentimental'); ?></label></th>
	<td>
		<select id="cmp-sale-sharing" name="sale_sharing">
			<?php foreach (\Consentimental\Consent\CaliforniaPolicy::labels() as $value => $label) : ?>
				<option value="<?php echo esc_attr($value); ?>" <?php selected($service?->sale_sharing ?? 'unreviewed', $value); ?>><?php echo esc_html($label); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e('Assess this service as configured on your site, including contracts and use for cross-context behavioral advertising. Its consent category alone does not determine sale/sharing. Unreviewed services cannot load while California controls are enabled. Save and publish your review.', 'consentimental'); ?></p>
	</td>
</tr>
