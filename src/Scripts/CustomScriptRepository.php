<?php

declare(strict_types=1);

namespace Consentimental\Scripts;

if (!defined('ABSPATH')) {
	exit;
}

final class CustomScriptRepository
{
	public const OPTION_NAME = 'cmp_custom_scripts';

	public function __construct(
		private readonly ?string $option_name = null,
		private readonly ?string $fallback_option_name = null,
	) {
	}

	/**
	 * @return CustomScript[]
	 */
	public function all(): array
	{
		$stored = get_option($this->option_name ?? self::OPTION_NAME, null);

		if (null === $stored && null !== $this->fallback_option_name) {
			$stored = get_option($this->fallback_option_name, null);
		}

		if (!is_array($stored)) {
			return [];
		}

		return array_map(static function (array $data): CustomScript {
			if (!array_key_exists('proxy_paths', $data) && !empty($data['proxy'])) {
				$data['proxy_paths'] = ProxyRoutes::legacy((string) ($data['url'] ?? ''));
			}
			return CustomScript::from_array($data);
		}, $stored);
	}

	public function get(string $id): ?CustomScript
	{
		foreach ($this->all() as $script) {
			if ($script->id === $id) {
				return $script;
			}
		}

		return null;
	}

	public function save(CustomScript $script): void
	{
		$scripts = $this->all();
		$found = false;

		foreach ($scripts as $i => $existing) {
			if ($existing->id === $script->id) {
				$scripts[$i] = $script;
				$found = true;

				break;
			}
		}

		if (!$found) {
			$scripts[] = $script;
		}

		$this->save_all($scripts);
	}

	public function delete(string $id): void
	{
		$this->save_all(array_values(array_filter(
			$this->all(),
			static fn (CustomScript $script): bool => $script->id !== $id,
		)));
	}

	/**
	 * @param CustomScript[] $scripts
	 */
	private function save_all(array $scripts): void
	{
		update_option(
			$this->option_name ?? self::OPTION_NAME,
			array_map(static fn (CustomScript $script): array => $script->to_array(), $scripts),
		);
	}
}
