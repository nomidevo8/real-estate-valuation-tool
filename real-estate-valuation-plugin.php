<?php
/**
 * Plugin Name: Real Estate Valuation API
 * Description: Provides REST API endpoints for uploading valuation CSV/XLSX and computing property valuations.
 * Version: 0.1.1
 * Author: AI Assistant
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/includes/ValuationService.php';
require_once __DIR__ . '/includes/Storage.php';

class REValuationPlugin {
	const REST_NAMESPACE = 'reval/v1';

	public function __construct() {
		add_action('rest_api_init', [$this, 'register_routes']);
		add_action('init', [$this, 'maybe_seed_defaults']);
		add_action('admin_menu', [$this, 'register_admin_menu']);
		add_filter('upload_mimes', [$this, 'allow_mimes']);
	}

	public function register_routes() {
		register_rest_route(self::REST_NAMESPACE, '/upload', [
			'methods' => 'POST',
			'callback' => [$this, 'handle_upload'],
			'permission_callback' => function() { return current_user_can('manage_options'); }
		]);

		register_rest_route(self::REST_NAMESPACE, '/evaluate', [
			'methods' => 'POST',
			'callback' => [$this, 'handle_evaluate'],
			'permission_callback' => '__return_true'
		]);
	}

	public function handle_upload(\WP_REST_Request $request) {
		$files = $request->get_file_params();
		if (empty($files['file'])) {
			return new \WP_REST_Response(['error' => 'Missing file field name "file"'], 400);
		}
		$file = $files['file'];
		if (!is_uploaded_file($file['tmp_name'])) {
			return new \WP_REST_Response(['error' => 'Invalid upload'], 400);
		}

		$service = new REValuationService(new REValuationStorage());
		try {
			$summary = $service->ingest_file($file['tmp_name'], $file['name']);
			return new \WP_REST_Response(['status' => 'ok', 'summary' => $summary], 200);
		} catch (\Throwable $e) {
			return new \WP_REST_Response(['error' => $e->getMessage()], 500);
		}
	}

	public function handle_evaluate(\WP_REST_Request $request) {
		$params = $request->get_json_params();
		$service = new REValuationService(new REValuationStorage());
		try {
			$result = $service->evaluate($params ?? []);
			return new \WP_REST_Response(['status' => 'ok', 'result' => $result], 200);
		} catch (\Throwable $e) {
			return new \WP_REST_Response(['error' => $e->getMessage()], 400);
		}
	}

	public function maybe_seed_defaults() {
		$storage = new REValuationStorage();
		if ($storage->has_seeded()) {
			return;
		}
		$csvPath = __DIR__ . '/IMMO.csv';
		if (file_exists($csvPath)) {
			$service = new REValuationService($storage);
			try {
				$service->ingest_file($csvPath, 'IMMO.csv');
				$storage->mark_seeded();
			} catch (\Throwable $e) {
				// Silent fail to avoid breaking site
			}
		}
	}

	public function register_admin_menu() {
		add_menu_page(
			'Real Estate Valuation',
			'Valuation',
			'manage_options',
			'reval_admin',
			[$this, 'render_admin_page'],
			'dashicons-chart-area',
			58
		);
	}

	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reval_admin_nonce']) && wp_verify_nonce($_POST['reval_admin_nonce'], 'reval_admin_upload')) {
			$this->handle_admin_upload();
		}
		?>
		<div class="wrap">
			<h1>Real Estate Valuation - Upload Data</h1>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field('reval_admin_upload', 'reval_admin_nonce'); ?>
				<p>
					<label for="reval_file">CSV or XLS/XLSX file:</label><br />
					<input type="file" id="reval_file" name="reval_file" accept=".csv,.xls,.xlsx" required />
				</p>
				<p><button type="submit" class="button button-primary">Upload</button></p>
			</form>
		</div>
		<?php
	}

	private function handle_admin_upload() {
		if (empty($_FILES['reval_file'])) {
			add_settings_error('reval_admin', 'reval_no_file', 'Please choose a file to upload.', 'error');
			settings_errors('reval_admin');
			return;
		}
		$file = $_FILES['reval_file'];
		if (!is_uploaded_file($file['tmp_name'])) {
			add_settings_error('reval_admin', 'reval_invalid', 'Upload failed or invalid file.', 'error');
			settings_errors('reval_admin');
			return;
		}
		$service = new REValuationService(new REValuationStorage());
		try {
			$summary = $service->ingest_file($file['tmp_name'], $file['name']);
			add_settings_error('reval_admin', 'reval_ok', 'Upload successful. Parsed: ' . esc_html(json_encode($summary)), 'updated');
		} catch (\Throwable $e) {
			add_settings_error('reval_admin', 'reval_error', 'Error: ' . esc_html($e->getMessage()), 'error');
		}
		settings_errors('reval_admin');
	}

	public function allow_mimes($mimes) {
		if (current_user_can('manage_options')) {
			$mimes['csv'] = 'text/csv';
			$mimes['xls'] = 'application/vnd.ms-excel';
			$mimes['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
		}
		return $mimes;
	}
}

new REValuationPlugin();
