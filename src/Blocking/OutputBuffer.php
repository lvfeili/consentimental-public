<?php

declare(strict_types=1);

namespace Consentimental\Blocking;

if (!defined('ABSPATH')) {
	exit;
}

final class OutputBuffer
{
	public function __construct(private readonly DomRewriter $rewriter)
	{
	}

	public function register(): void
	{
		add_action('template_redirect', $this->maybe_start(...), 0);
	}

	private function maybe_start(): void
	{
		if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
			return;
		}

		if (!$this->rewriter->has_matchable_services()) {
			return;
		}

		ob_start($this->rewriter->rewrite(...));
	}
}
