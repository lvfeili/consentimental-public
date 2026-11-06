<?php

declare(strict_types=1);

namespace Consentimental\Services;

use Consentimental\Consent\DraftManager;

if (!defined('ABSPATH')) {
	exit;
}

final class ServiceActivator
{
	public function __construct(
		private readonly ServiceRegistry $services,
		private readonly ServiceOverrideRepository $overrides,
		private readonly CustomServiceRepository $custom_services,
		private readonly DraftManager $draft,
	) {
	}

	public function set_active(string $id, bool $active): bool
	{
		$service = $this->services->get($id);

		if (null === $service || $service->active === $active) {
			return false;
		}

		$this->draft->mark_meaningful();

		if ($service->built_in) {
			$override = $this->overrides->get($id) ?? [
				'category' => $service->category,
				'domains' => $service->domains,
				'script_patterns' => $service->script_patterns,
				'iframe_patterns' => $service->iframe_patterns,
			];
			$override['active'] = $active;
			$this->overrides->save($id, $override);

			return true;
		}

		$service->active = $active;
		$this->custom_services->save_one($service);

		return true;
	}

	public function set_category(string $id, string $category): bool
	{
		$service = $this->services->get($id);

		if (null === $service || '' === $category || $service->category === $category) {
			return false;
		}

		$this->draft->mark_meaningful();

		if ($service->built_in) {
			$override = $this->overrides->get($id) ?? [
				'domains' => $service->domains,
				'script_patterns' => $service->script_patterns,
				'iframe_patterns' => $service->iframe_patterns,
			];
			$override['category'] = $category;
			$this->overrides->save($id, $override);

			return true;
		}

		$service->category = $category;
		$this->custom_services->save_one($service);

		return true;
	}
}
