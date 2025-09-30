<?php

if (!defined('ABSPATH')) {
	exit;
}

class REValuationStorage {
	private string $key_communes = 'reval_communes';
	private string $key_weights = 'reval_weights';
	private string $key_forfaits = 'reval_forfaits';
	private string $key_year_adjust = 'reval_year_adjust';
	private string $key_seeded = 'reval_seeded_defaults';

	public function save_communes(array $communes): void {
		update_option($this->key_communes, $communes, false);
	}
	public function get_communes(): array {
		return get_option($this->key_communes, []);
	}

	public function save_weights(array $weights): void {
		update_option($this->key_weights, $weights, false);
	}
	public function get_weights(): array {
		return get_option($this->key_weights, []);
	}

	public function save_forfaits(array $forfaits): void {
		update_option($this->key_forfaits, $forfaits, false);
	}
	public function get_forfaits(): array {
		return get_option($this->key_forfaits, []);
	}

	public function save_year_adjust(array $yearAdjust): void {
		update_option($this->key_year_adjust, $yearAdjust, false);
	}
	public function get_year_adjust(): array {
		return get_option($this->key_year_adjust, []);
	}

	public function has_seeded(): bool {
		return (bool)get_option($this->key_seeded, false);
	}
	public function mark_seeded(): void {
		update_option($this->key_seeded, true, false);
	}
}
