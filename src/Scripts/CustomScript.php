<?php

declare(strict_types=1);

namespace Consentimental\Scripts;

if (!defined('ABSPATH')) {
	exit;
}

final class CustomScript
{
	public const TYPE_URL = 'url';
	public const TYPE_INLINE = 'inline';

	public const PLACEMENT_HEAD = 'head';
	public const PLACEMENT_BODY_START = 'body_start';
	public const PLACEMENT_FOOTER = 'footer';

	public const CONDITION_WHOLE_SITE = 'whole_site';
	public const CONDITION_URL = 'url';
	public const CONDITION_URL_PATTERN = 'url_pattern';
	public const CONDITION_POST_TYPE = 'post_type';

	public function __construct(
		public readonly string $id,
		public string $name,
		public string $type,
		public string $url,
		public string $code,
		public string $attributes,
		public bool $proxy,
		public string $proxy_api_origin,
		public string $placement,
		public string $category,
		public string $service,
		public string $condition_type,
		public string $condition_value,
		public string $proxy_paths = '',
	) {
	}

	/**
	 * @return array<string, string|bool>
	 */
	public function to_array(): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name,
			'type' => $this->type,
			'url' => $this->url,
			'code' => $this->code,
			'attributes' => $this->attributes,
			'proxy' => $this->proxy,
			'proxy_api_origin' => $this->proxy_api_origin,
			'proxy_paths' => $this->proxy_paths,
			'placement' => $this->placement,
			'category' => $this->category,
			'service' => $this->service,
			'condition_type' => $this->condition_type,
			'condition_value' => $this->condition_value,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array(array $data): self
	{
		return new self(
			id: (string) $data['id'],
			proxy_paths: (string) ($data['proxy_paths'] ?? ''),
			name: (string) ($data['name'] ?? ''),
			type: self::TYPE_INLINE === ($data['type'] ?? '') ? self::TYPE_INLINE : self::TYPE_URL,
			url: (string) ($data['url'] ?? ''),
			code: (string) ($data['code'] ?? ''),
			attributes: (string) ($data['attributes'] ?? ''),
			proxy: (bool) ($data['proxy'] ?? false),
			proxy_api_origin: (string) ($data['proxy_api_origin'] ?? ''),
			placement: in_array($data['placement'] ?? '', [self::PLACEMENT_HEAD, self::PLACEMENT_BODY_START, self::PLACEMENT_FOOTER], true)
				? (string) $data['placement']
				: self::PLACEMENT_FOOTER,
			category: (string) ($data['category'] ?? 'necessary'),
			service: (string) ($data['service'] ?? ''),
			condition_type: in_array($data['condition_type'] ?? '', [self::CONDITION_WHOLE_SITE, self::CONDITION_URL, self::CONDITION_URL_PATTERN, self::CONDITION_POST_TYPE], true)
				? (string) $data['condition_type']
				: self::CONDITION_WHOLE_SITE,
			condition_value: (string) ($data['condition_value'] ?? ''),
		);
	}
}
