<?php

declare(strict_types=1);

namespace Consentimental\Integrations;

use Consentimental\Consent\Category;
use Consentimental\Services\Service;

if (!defined('ABSPATH')) {
	exit;
}

final class MultilingualStrings
{
	public function text(string $name, string $value, bool $multiline = false): string
	{
		if ('' === $value) {
			return $value;
		}

		do_action('wpml_register_single_string', 'consentimental', $name, $value);
		$value = (string) apply_filters('wpml_translate_single_string', $value, 'consentimental', $name);

		if (function_exists('pll_register_string')) {
			pll_register_string($name, $value, 'consentimental', $multiline);
		}

		if (function_exists('pll__')) {
			$value = pll__($value);
		}

		return $value;
	}

	public function page_id(int $page_id): int
	{
		if ($page_id <= 0) {
			return $page_id;
		}

		$translated = apply_filters('wpml_object_id', $page_id, 'page', true);

		if (function_exists('pll_get_post')) {
			$translated = pll_get_post($page_id) ?: $translated;
		}

		return (int) ($translated ?: $page_id);
	}

	/**
	 * @return array{locale: string, code: string, direction: string}
	 */
	public function current_language(): array
	{
		$code = (string) apply_filters('wpml_current_language', '');

		if ('' === $code && function_exists('pll_current_language')) {
			$code = (string) pll_current_language('slug');
		}

		return [
			'locale' => (string) determine_locale(),
			'code' => sanitize_key($code),
			'direction' => is_rtl() ? 'rtl' : 'ltr',
		];
	}

	public function category(Category $category): Category
	{
		$translated = clone $category;
		$defaults = [
			'necessary' => ['Necessary', 'Required for the website to function and cannot be disabled.', __('Necessary', 'consentimental'), __('Required for the website to function and cannot be disabled.', 'consentimental')],
			'functional' => ['Functional', 'Enables enhanced functionality such as embedded content and preferences.', __('Functional', 'consentimental'), __('Enables enhanced functionality such as embedded content and preferences.', 'consentimental')],
			'analytics' => ['Analytics', 'Helps understand how visitors use the website.', __('Analytics', 'consentimental'), __('Helps understand how visitors use the website.', 'consentimental')],
			'marketing' => ['Marketing', 'Used to track visitors for advertising purposes.', __('Marketing', 'consentimental'), __('Used to track visitors for advertising purposes.', 'consentimental')],
		];
		[$source_label, $source_description, $localized_label, $localized_description] = $defaults[$category->slug] ?? ['', '', '', ''];
		$label = $category->label === $source_label ? $localized_label : $category->label;
		$description = $category->description === $source_description ? $localized_description : $category->description;
		$translated->label = $this->text("category_{$category->slug}_label", $label);
		$translated->description = $this->text("category_{$category->slug}_description", $description, multiline: true);

		return $translated;
	}

	public function service(Service $service): Service
	{
		$translated = clone $service;
		$translated->name = $this->text("service_{$service->id}_name", $service->name);
		$translated->purpose = $this->text("service_{$service->id}_purpose", $service->purpose, multiline: true);

		foreach ($translated->cookies as $index => $cookie) {
			if (isset($cookie['purpose']) && '' !== $cookie['purpose']) {
				$translated->cookies[$index]['purpose'] = $this->text(
					"service_{$service->id}_cookie_{$index}_purpose",
					$cookie['purpose'],
				);
			}
		}

		return $translated;
	}
}
