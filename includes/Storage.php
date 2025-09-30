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
	private string $key_formulas = 'reval_formulas';
	private string $key_evaluations = 'reval_evaluations';
	private string $key_evaluation_history = 'reval_evaluation_history';

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

	// Enhanced storage for formulas and configurations
	public function save_formulas(array $formulas): void {
		update_option($this->key_formulas, $formulas, false);
	}
	public function get_formulas(): array {
		$defaults = [
			'land_coefficient' => 0.1,
			'bedroom_bonus_multiplier' => 5,
			'type_coefficients' => [
				'apartment' => -0.05,
				'house' => 0.0,
				'villa' => 0.10
			],
			'range_low_percent' => -0.05,
			'range_high_percent' => 0.05,
			'energy_efficiency_bonus' => [
				'A' => 0.10,
				'B' => 0.05,
				'C' => 0.0,
				'D' => -0.05,
				'E' => -0.10,
				'F' => -0.15,
				'G' => -0.20
			],
			'location_factors' => [
				'city_center' => 0.15,
				'residential' => 0.05,
				'suburban' => 0.0,
				'rural' => -0.10
			],
			'condition_factors' => [
				'excellent' => 0.10,
				'good' => 0.05,
				'average' => 0.0,
				'poor' => -0.10,
				'very_poor' => -0.20
			]
		];
		return get_option($this->key_formulas, $defaults);
	}

	// Save evaluation details
	public function save_evaluation(array $input, array $result, string $user_id = null): string {
		$evaluation_id = uniqid('eval_', true);
		$evaluation_data = [
			'id' => $evaluation_id,
			'input' => $input,
			'result' => $result,
			'user_id' => $user_id ?: get_current_user_id(),
			'timestamp' => current_time('mysql'),
			'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
		];

		$evaluations = get_option($this->key_evaluations, []);
		$evaluations[$evaluation_id] = $evaluation_data;
		update_option($this->key_evaluations, $evaluations, false);

		// Add to history
		$this->add_to_history($evaluation_data);

		return $evaluation_id;
	}

	public function get_evaluation(string $evaluation_id): ?array {
		$evaluations = get_option($this->key_evaluations, []);
		return $evaluations[$evaluation_id] ?? null;
	}

	public function get_evaluations(int $limit = 50, int $offset = 0): array {
		$evaluations = get_option($this->key_evaluations, []);
		$sorted = array_reverse($evaluations, true);
		return array_slice($sorted, $offset, $limit, true);
	}

	public function delete_evaluation(string $evaluation_id): void {
		$evaluations = get_option($this->key_evaluations, []);
		if (isset($evaluations[$evaluation_id])) {
			unset($evaluations[$evaluation_id]);
			update_option($this->key_evaluations, $evaluations, false);
		}
	}

	public function delete_evaluations(array $evaluation_ids): int {
		$evaluations = get_option($this->key_evaluations, []);
		$deleted = 0;
		foreach ($evaluation_ids as $id) {
			if (isset($evaluations[$id])) {
				unset($evaluations[$id]);
				$deleted++;
			}
		}
		update_option($this->key_evaluations, $evaluations, false);
		return $deleted;
	}

	public function clear_all_evaluations(): void {
		update_option($this->key_evaluations, [], false);
	}

	public function get_evaluation_history(int $limit = 100): array {
		return get_option($this->key_evaluation_history, []);
	}

	private function add_to_history(array $evaluation_data): void {
		$history = get_option($this->key_evaluation_history, []);
		$history[] = $evaluation_data;
		
		// Keep only last 1000 evaluations in history
		if (count($history) > 1000) {
			$history = array_slice($history, -1000);
		}
		
		update_option($this->key_evaluation_history, $history, false);
	}

	// Statistics and analytics
	public function get_statistics(): array {
		$evaluations = get_option($this->key_evaluations, []);
		$total_evaluations = count($evaluations);
		
		if ($total_evaluations === 0) {
			return [
				'total_evaluations' => 0,
				'average_value' => 0,
				'most_common_commune' => null,
				'most_common_type' => null,
				'value_range' => ['min' => 0, 'max' => 0]
			];
		}

		$values = array_column($evaluations, 'result');
		$total_values = array_column($values, 'valeur_totale');
		$communes = array_column($values, 'input');
		$commune_names = array_column($communes, 'commune');
		$types = array_column($communes, 'type');

		return [
			'total_evaluations' => $total_evaluations,
			'average_value' => array_sum($total_values) / count($total_values),
			'most_common_commune' => $this->get_most_common($commune_names),
			'most_common_type' => $this->get_most_common($types),
			'value_range' => [
				'min' => min($total_values),
				'max' => max($total_values)
			]
		];
	}

	private function get_most_common(array $items): ?string {
		$counts = array_count_values($items);
		arsort($counts);
		return array_key_first($counts);
	}

	// Cleanup old data
	public function cleanup_old_data(int $days = 365): void {
		$cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
		$evaluations = get_option($this->key_evaluations, []);
		
		foreach ($evaluations as $id => $evaluation) {
			if ($evaluation['timestamp'] < $cutoff_date) {
				unset($evaluations[$id]);
			}
		}
		
		update_option($this->key_evaluations, $evaluations, false);
	}
}
