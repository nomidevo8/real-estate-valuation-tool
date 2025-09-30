<?php

if (!defined('ABSPATH')) {
	exit;
}

class REValuationService
{
	private REValuationStorage $storage;

	public function __construct(REValuationStorage $storage)
	{
		$this->storage = $storage;
	}

	public function ingest_file(string $path, string $originalName): array
	{
		$lower = strtolower($originalName);
		if (str_ends_with($lower, '.csv')) {
			return $this->ingest_csv($path);
		}
		if (str_ends_with($lower, '.xlsx') || str_ends_with($lower, '.xls')) {
			return $this->ingest_xlsx($path, $lower);
		}
		throw new Exception('Unsupported file type');
	}

	private function ingest_csv(string $path): array
	{
		$fh = fopen($path, 'r');
		if (!$fh) {
			throw new Exception('Cannot open file');
		}
		$rows = [];
		while (($row = fgetcsv($fh)) !== false) {
			$rows[] = $row;
		}
		fclose($fh);
		return $this->parse_rows($rows);
	}

	private function ingest_xlsx(string $path, string $lowerName): array
	{
		if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
			throw new \RuntimeException('XLS/XLSX requires PhpSpreadsheet. Please install phpoffice/phpspreadsheet.');
		}

		try {
			$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
			$reader->setReadDataOnly(true);

			$spreadsheet = $reader->load($path);
			$sheet = $spreadsheet->getActiveSheet();

			$rows = [];
			foreach ($sheet->getRowIterator() as $row) {
				$cells = [];
				foreach ($row->getCellIterator() as $cell) {
					// safer for readable values (e.g. dates, currencies)
					$cells[] = trim((string) $cell->getFormattedValue());
				}
				$rows[] = $cells;
			}

			return $this->parse_rows($rows);

		} catch (\Exception $e) {
			throw new \RuntimeException("Failed to read Excel file: " . $e->getMessage(), 0, $e);
		}
	}


	private function parse_rows(array $rows): array
	{
		$communes = [];
		$weights = [
			'habitable' => 1.0,
			'balcony' => 0.3,
			'terrace' => 0.35,
			'private_garden' => 0.15,
			'shared_garden' => 0.08,
			'cellar' => 0.6
		];
		$yearAdjust = [
			'before1990' => -0.19,
			'after1990' => -0.38
		];
		$forfaits = [
			'garage_box' => 27500,
			'parking_indoor' => 16500,
			'parking_outdoor' => 7500
		];

		foreach ($rows as $row) {
			if (!isset($row[0]) || !isset($row[1]))
				continue;
			$name = trim($row[0]);
			$val = trim($row[1]);
			if ($name === '' || $val === '')
				continue;

			if (!preg_match('/^\d/', $name)) {
				$price = $this->parse_price_eur($val);
				if ($price > 0) {
					$communes[$name] = $price;
				}
			}

			if (isset($row[6]) && isset($row[7])) {
				$label = trim($row[6]);
				$v = trim((string) $row[7]);
				if ($label !== '' && $v !== '') {
					if (stripos($label, 'Balcon') !== false)
						$weights['balcony'] = (float) $v;
					if (stripos($label, 'Terrasse') !== false)
						$weights['terrace'] = (float) $v;
					if (stripos($label, 'Jardin privatif') !== false)
						$weights['private_garden'] = (float) $v;
					if (stripos($label, 'Jardin copropriété') !== false)
						$weights['shared_garden'] = (float) $v;
					if (stripos($label, 'Cave') !== false)
						$weights['cellar'] = (float) $v;

					if (stripos($label, 'Garage box') !== false)
						$forfaits['garage_box'] = (int) $v;
					if (stripos($label, 'Parking intérieur') !== false)
						$forfaits['parking_indoor'] = (int) $v;
					if (stripos($label, 'Parking extérieur') !== false)
						$forfaits['parking_outdoor'] = (int) $v;
				}
			}

			if (isset($row[6]) && isset($row[7])) {
				$label = trim($row[6]);
				$v = trim((string) $row[7]);
				if ($label === '>1990')
					$yearAdjust['after1990'] = $this->parse_percent($v);
				if ($label === '<1990')
					$yearAdjust['before1990'] = $this->parse_percent($v);
			}
		}

		$this->storage->save_communes($communes);
		$this->storage->save_weights($weights);
		$this->storage->save_forfaits($forfaits);
		$this->storage->save_year_adjust($yearAdjust);

		return [
			'communes' => count($communes),
			'weights' => $weights,
			'forfaits' => $forfaits,
			'yearAdjust' => $yearAdjust
		];
	}

	private function parse_price_eur(string $s): float
	{
		$s = trim(str_replace(['€', "\u{00A0}"], '', $s)); // remove euro + non-breaking space
		
		// Case 1: "9,800.00" or "9.800,00"
		if (preg_match('/^\d{1,3}([.,]\d{3})*[.,]\d{2}$/', $s)) {
			// Has cents → remove thousand sep
			$s = preg_replace('/[.,](?=\d{3}(?:[.,]|$))/', '', $s); 
			$s = str_replace(',', '.', $s);
		} else {
			// Case 2: "9,800" → replace comma with nothing
			$s = str_replace(',', '', $s);
		}
	
		return (float) $s;
	}
	

	private function parse_percent(string $s): float
	{
		$s = trim($s);
		$s = str_replace('%', '', $s);
		$s = str_replace(',', '.', $s);
		$val = (float) $s;
	
		// If Excel already gave us a decimal (e.g. -0.38), just return it
		if ($val > -1 && $val < 1) {
			return $val;
		}
	
		return $val / 100.0;
	}
	
	public function evaluate(array $input): array
	{
		// Enhanced input validation with more fields
		$type = $this->require_string($input, 'type');
		$commune = $this->require_string($input, 'commune');
		$land_ares = $this->require_number($input, 'land_ares', 0.0);
		$living_m2 = $this->require_number($input, 'living_m2');
		$bedrooms = (int) ($input['bedrooms'] ?? 0);
		$balcony_m2 = $this->require_number($input, 'balcony_m2', 0.0);
		$terrace_m2 = $this->require_number($input, 'terrace_m2', 0.0);
		$private_garden_m2 = $this->require_number($input, 'private_garden_m2', 0.0);
		$shared_garden_m2 = $this->require_number($input, 'shared_garden_m2', 0.0);
		$cellar_m2 = $this->require_number($input, 'cellar_m2', 0.0);
		$garage_box_units = (int) ($input['garage_box_units'] ?? 0);
		$parking_indoor_units = (int) ($input['parking_indoor_units'] ?? 0);
		$parking_outdoor_units = (int) ($input['parking_outdoor_units'] ?? 0);
		$apply_year_coef = strtolower((string) ($input['apply_year_coef'] ?? 'No')) === 'yes';
		$year_built = (int) ($input['year_built'] ?? 0);
		
		// New enhanced fields
		$energy_efficiency = strtoupper(trim($input['energy_efficiency'] ?? 'C'));
		$location_type = trim($input['location_type'] ?? 'residential');
		$condition = trim($input['condition'] ?? 'average');
		$special_features = $input['special_features'] ?? [];
		$custom_adjustments = $input['custom_adjustments'] ?? [];
		$email = trim((string)($input['email'] ?? ''));
		$phone = trim((string)($input['phone'] ?? ''));

		$communes = $this->storage->get_communes();
		$weights = $this->storage->get_weights();
		$forfaits = $this->storage->get_forfaits();
		$yearAdjust = $this->storage->get_year_adjust();
		$formulas = $this->storage->get_formulas();

		if (!isset($communes[$commune])) {
			throw new Exception('Unknown commune: ' . $commune);
		}
		$prix_m2 = (float) $communes[$commune];

		// ✅ Weighted surface calculation
		$weighted_m2 = (
			$living_m2 * ($weights['habitable'] ?? 1.0)
			+ $balcony_m2 * ($weights['balcony'] ?? 0.3)
			+ $terrace_m2 * ($weights['terrace'] ?? 0.35)
			+ $private_garden_m2 * ($weights['private_garden'] ?? 0.15)
			+ $shared_garden_m2 * ($weights['shared_garden'] ?? 0.08)
			+ $cellar_m2 * ($weights['cellar'] ?? 0.6)
		);

		$valeur_batie = $weighted_m2 * $prix_m2;

		// ✅ Forfaits calculation
		$forfaits_total = (
			$garage_box_units * ($forfaits['garage_box'] ?? 0)
			+ $parking_indoor_units * ($forfaits['parking_indoor'] ?? 0)
			+ $parking_outdoor_units * ($forfaits['parking_outdoor'] ?? 0)
		);

		// ✅ Land value with configurable coefficient
		$land_coef = $formulas['land_coefficient'] ?? 0.1;
		$land_value = $land_ares * ($prix_m2 * $land_coef);

		// ✅ Bedroom bonus with configurable multiplier
		$bedroom_multiplier = $formulas['bedroom_bonus_multiplier'] ?? 5;
		$bedroom_bonus = $bedrooms * ($prix_m2 * $bedroom_multiplier);

		// ✅ Year adjustment
		$coef_year = 0.0;
		if ($apply_year_coef && $year_built > 0) {
			$coef_year = ($year_built < 1990) ? ($yearAdjust['before1990'] ?? -0.38) : ($yearAdjust['after1990'] ?? -0.19);
		}

		// ✅ Type adjustment with configurable coefficients
		$type_coefficients = $formulas['type_coefficients'] ?? [
			'apartment' => -0.05,
			'house' => 0.0,
			'villa' => 0.10
		];
		$coef_type = $type_coefficients[strtolower($type)] ?? 0.0;

		// ✅ Energy efficiency bonus
		$energy_bonus = 0.0;
		$energy_efficiency_bonus = $formulas['energy_efficiency_bonus'] ?? [];
		if (isset($energy_efficiency_bonus[$energy_efficiency])) {
			$energy_bonus = $energy_efficiency_bonus[$energy_efficiency];
		}

		// ✅ Location factor
		$location_factor = 0.0;
		$location_factors = $formulas['location_factors'] ?? [];
		if (isset($location_factors[$location_type])) {
			$location_factor = $location_factors[$location_type];
		}

		// ✅ Condition factor
		$condition_factor = 0.0;
		$condition_factors = $formulas['condition_factors'] ?? [];
		if (isset($condition_factors[$condition])) {
			$condition_factor = $condition_factors[$condition];
		}

		// ✅ Special features bonus
		$special_features_bonus = 0.0;
		foreach ($special_features as $feature => $value) {
			if (is_numeric($value)) {
				$special_features_bonus += (float) $value;
			}
		}

		// ✅ Custom adjustments
		$custom_adjustments_total = 0.0;
		foreach ($custom_adjustments as $adjustment => $value) {
			if (is_numeric($value)) {
				$custom_adjustments_total += (float) $value;
			}
		}

		// ✅ Apply coefficients only to construction (Excel logic)
		$total_coefficient = 1.0;
		$total_coefficient *= (1.0 + $coef_year);
		$total_coefficient *= (1.0 + $coef_type);
		$total_coefficient *= (1.0 + $energy_bonus);
		$total_coefficient *= (1.0 + $location_factor);
		$total_coefficient *= (1.0 + $condition_factor);
		$total_coefficient *= (1.0 + $special_features_bonus);
		$total_coefficient *= (1.0 + $custom_adjustments_total);

		$adjusted_valeur_batie = $valeur_batie * $total_coefficient;

		// ✅ Final total = adjusted construction + land + forfaits + bedroom bonus
		$valeur_totale = $adjusted_valeur_batie + $forfaits_total + $land_value + $bedroom_bonus;


		// ✅ Range calculation with configurable percentages
		$range_low_percent = $formulas['range_low_percent'] ?? -0.05;
		$range_high_percent = $formulas['range_high_percent'] ?? 0.05;
		$range_low = $valeur_totale * (1 + $range_low_percent);
		$range_high = $valeur_totale * (1 + $range_high_percent);

		// ✅ Create detailed result
		$result = [
			'prix_m2' => $prix_m2,
			'weighted_m2' => $weighted_m2,
			'valeur_batie' => $valeur_batie,
			'land_value' => $land_value,
			'bedroom_bonus' => $bedroom_bonus,
			'forfaits_total' => $forfaits_total,
			'coef_year' => $coef_year,
			'coef_type' => $coef_type,
			'energy_bonus' => $energy_bonus,
			'location_factor' => $location_factor,
			'condition_factor' => $condition_factor,
			'special_features_bonus' => $special_features_bonus,
			'custom_adjustments_total' => $custom_adjustments_total,
			'total_coefficient' => $total_coefficient,
			'valeur_totale' => $valeur_totale,
			'range' => [
				'low' => $range_low,
				'mid' => $valeur_totale,
				'high' => $range_high
			],
			'breakdown' => [
				'base_value' => $adjusted_valeur_batie,
				'coefficients' => [
					'year' => $coef_year,
					'type' => $coef_type,
					'energy' => $energy_bonus,
					'location' => $location_factor,
					'condition' => $condition_factor,
					'special_features' => $special_features_bonus,
					'custom' => $custom_adjustments_total
				]
			]
		];

		// ✅ Save evaluation details
		$evaluation_id = $this->storage->save_evaluation($input, $result);

		$result['evaluation_id'] = $evaluation_id;
		$result['timestamp'] = current_time('mysql');

		return $result;
	}


	private function require_string(array $input, string $key): string
	{
		if (!isset($input[$key]) || trim((string) $input[$key]) === '') {
			throw new Exception('Missing field: ' . $key);
		}
		return (string) $input[$key];
	}

	private function require_number(array $input, string $key, float $default = null): float
	{
		if (!isset($input[$key])) {
			if ($default !== null)
				return (float) $default;
			throw new Exception('Missing field: ' . $key);
		}
		return (float) $input[$key];
	}
}
