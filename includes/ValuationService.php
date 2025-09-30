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
		$s = str_replace(['€', ' ', "\u00A0"], '', $s);
		$s = str_replace(['.', '\''], '', $s);
		$s = str_replace(',', '.', $s);
		return (float) $s;
	}

	private function parse_percent(string $s): float
	{
		$s = trim($s);
		$s = str_replace('%', '', $s);
		$s = str_replace(',', '.', $s);
		return (float) $s / 100.0;
	}

	public function evaluate(array $input): array
	{
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

		$communes = $this->storage->get_communes();
		$weights = $this->storage->get_weights();
		$forfaits = $this->storage->get_forfaits();
		$yearAdjust = $this->storage->get_year_adjust();

		if (!isset($communes[$commune])) {
			throw new Exception('Unknown commune: ' . $commune);
		}
		$prix_m2 = (float) $communes[$commune];

		$weighted_m2 = (
			$living_m2 * ($weights['habitable'] ?? 1.0)
			+ $balcony_m2 * ($weights['balcony'] ?? 0.3)
			+ $terrace_m2 * ($weights['terrace'] ?? 0.35)
			+ $private_garden_m2 * ($weights['private_garden'] ?? 0.15)
			+ $shared_garden_m2 * ($weights['shared_garden'] ?? 0.08)
			+ $cellar_m2 * ($weights['cellar'] ?? 0.6)
		);

		$valeur_batie = $weighted_m2 * $prix_m2;

		$forfaits_total = (
			$garage_box_units * ($forfaits['garage_box'] ?? 0)
			+ $parking_indoor_units * ($forfaits['parking_indoor'] ?? 0)
			+ $parking_outdoor_units * ($forfaits['parking_outdoor'] ?? 0)
		);

		$coef_year = 0.0;
		if ($apply_year_coef && $year_built > 0) {
			$coef_year = ($year_built < 1990) ? ($yearAdjust['before1990'] ?? 0.0) : ($yearAdjust['after1990'] ?? 0.0);
		}

		$coef_type = 0.0;

		$valeur_totale = ($valeur_batie + $forfaits_total) * (1.0 + $coef_year + $coef_type);

		$range_low = $valeur_totale * 0.95;
		$range_high = $valeur_totale * 1.05;

		return [
			'prix_m2' => $prix_m2,
			'weighted_m2' => $weighted_m2,
			'valeur_batie' => $valeur_batie,
			'forfaits_total' => $forfaits_total,
			'coef_year' => $coef_year,
			'coef_type' => $coef_type,
			'valeur_totale' => $valeur_totale,
			'range' => [
				'low' => $range_low,
				'mid' => $valeur_totale,
				'high' => $range_high
			]
		];
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
