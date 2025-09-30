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
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class REValuationPlugin {
	const REST_NAMESPACE = 'reval/v1';

	public function __construct() {
		add_action('rest_api_init', [$this, 'register_routes']);
		add_action('init', [$this, 'maybe_seed_defaults']);
		add_action('admin_menu', [$this, 'register_admin_menu']);
		add_filter('upload_mimes', [$this, 'allow_mimes']);
		add_action('wp_enqueue_scripts', [$this, 'register_frontend_assets']);
		add_shortcode('reval_form', [$this, 'render_frontend_form']);
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

		register_rest_route(self::REST_NAMESPACE, '/evaluations', [
			'methods' => 'GET',
			'callback' => [$this, 'handle_get_evaluations'],
			'permission_callback' => function() { return current_user_can('manage_options'); }
		]);

		register_rest_route(self::REST_NAMESPACE, '/evaluations/(?P<id>[a-zA-Z0-9_]+)', [
			'methods' => 'GET',
			'callback' => [$this, 'handle_get_evaluation'],
			'permission_callback' => function() { return current_user_can('manage_options'); }
		]);

		register_rest_route(self::REST_NAMESPACE, '/statistics', [
			'methods' => 'GET',
			'callback' => [$this, 'handle_get_statistics'],
			'permission_callback' => function() { return current_user_can('manage_options'); }
		]);

		register_rest_route(self::REST_NAMESPACE, '/formulas', [
			'methods' => 'GET',
			'callback' => [$this, 'handle_get_formulas'],
			'permission_callback' => function() { return current_user_can('manage_options'); }
		]);

		register_rest_route(self::REST_NAMESPACE, '/formulas', [
			'methods' => 'POST',
			'callback' => [$this, 'handle_save_formulas'],
			'permission_callback' => function() { return current_user_can('manage_options'); }
		]);

		// Public communes list for frontend form
		register_rest_route(self::REST_NAMESPACE, '/communes', [
			'methods' => 'GET',
			'callback' => [$this, 'handle_get_communes'],
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

		// Add submenu pages
		add_submenu_page(
			'reval_admin',
			'Upload Data',
			'Upload Data',
			'manage_options',
			'reval_admin',
			[$this, 'render_admin_page']
		);

		add_submenu_page(
			'reval_admin',
			'Formulas & Settings',
			'Formulas & Settings',
			'manage_options',
			'reval_formulas',
			[$this, 'render_formulas_page']
		);

		add_submenu_page(
			'reval_admin',
			'Evaluation History',
			'Evaluation History',
			'manage_options',
			'reval_history',
			[$this, 'render_history_page']
		);

		add_submenu_page(
			'reval_admin',
			'Statistics',
			'Statistics',
			'manage_options',
			'reval_statistics',
			[$this, 'render_statistics_page']
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

	// New admin page methods
	public function render_formulas_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$storage = new REValuationStorage();
		$formulas = $storage->get_formulas();

		// Handle form submission
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reval_formulas_nonce']) && wp_verify_nonce($_POST['reval_formulas_nonce'], 'reval_formulas_save')) {
			$this->handle_formulas_save();
			$formulas = $storage->get_formulas(); // Refresh after save
		}

		?>
		<div class="wrap">
			<h1>Formulas & Settings</h1>
			<form method="post">
				<?php wp_nonce_field('reval_formulas_save', 'reval_formulas_nonce'); ?>
				
				<h2>Basic Coefficients</h2>
				<table class="form-table">
					<tr>
						<th scope="row">Land Coefficient</th>
						<td>
							<input type="number" name="land_coefficient" value="<?php echo esc_attr($formulas['land_coefficient']); ?>" step="0.01" min="0" max="1" />
							<p class="description">Percentage of m² price for land value calculation</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Bedroom Bonus Multiplier</th>
						<td>
							<input type="number" name="bedroom_bonus_multiplier" value="<?php echo esc_attr($formulas['bedroom_bonus_multiplier']); ?>" step="0.1" min="0" />
							<p class="description">Multiplier for bedroom bonus calculation</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Range Low Percentage</th>
						<td>
							<input type="number" name="range_low_percent" value="<?php echo esc_attr($formulas['range_low_percent']); ?>" step="0.01" max="0" />
							<p class="description">Lower bound percentage for value range</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Range High Percentage</th>
						<td>
							<input type="number" name="range_high_percent" value="<?php echo esc_attr($formulas['range_high_percent']); ?>" step="0.01" min="0" />
							<p class="description">Upper bound percentage for value range</p>
						</td>
					</tr>
				</table>

				<h2>Property Type Coefficients</h2>
				<table class="form-table">
					<?php foreach ($formulas['type_coefficients'] as $type => $coef): ?>
					<tr>
						<th scope="row"><?php echo ucfirst($type); ?></th>
						<td>
							<input type="number" name="type_coefficients[<?php echo esc_attr($type); ?>]" value="<?php echo esc_attr($coef); ?>" step="0.01" />
						</td>
					</tr>
					<?php endforeach; ?>
				</table>

				<h2>Energy Efficiency Bonuses</h2>
				<table class="form-table">
					<?php foreach ($formulas['energy_efficiency_bonus'] as $grade => $bonus): ?>
					<tr>
						<th scope="row">Grade <?php echo $grade; ?></th>
						<td>
							<input type="number" name="energy_efficiency_bonus[<?php echo esc_attr($grade); ?>]" value="<?php echo esc_attr($bonus); ?>" step="0.01" />
						</td>
					</tr>
					<?php endforeach; ?>
				</table>

				<h2>Location Factors</h2>
				<table class="form-table">
					<?php foreach ($formulas['location_factors'] as $location => $factor): ?>
					<tr>
						<th scope="row"><?php echo ucfirst(str_replace('_', ' ', $location)); ?></th>
						<td>
							<input type="number" name="location_factors[<?php echo esc_attr($location); ?>]" value="<?php echo esc_attr($factor); ?>" step="0.01" />
						</td>
					</tr>
					<?php endforeach; ?>
				</table>

				<h2>Condition Factors</h2>
				<table class="form-table">
					<?php foreach ($formulas['condition_factors'] as $condition => $factor): ?>
					<tr>
						<th scope="row"><?php echo ucfirst(str_replace('_', ' ', $condition)); ?></th>
						<td>
							<input type="number" name="condition_factors[<?php echo esc_attr($condition); ?>]" value="<?php echo esc_attr($factor); ?>" step="0.01" />
						</td>
					</tr>
					<?php endforeach; ?>
				</table>

				<?php submit_button('Save Formulas'); ?>
			</form>
		</div>
		<?php
	}

	public function render_history_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$storage = new REValuationStorage();
		
		// Handle cleanup action
		if (isset($_POST['cleanup_data']) && isset($_POST['reval_cleanup_nonce']) && wp_verify_nonce($_POST['reval_cleanup_nonce'], 'reval_cleanup')) {
			$days = (int) $_POST['cleanup_days'];
			$storage->cleanup_old_data($days);
			add_settings_error('reval_cleanup', 'reval_cleanup_success', "Cleaned up evaluations older than {$days} days.", 'updated');
			settings_errors('reval_cleanup');
		}

		// Handle single delete (uses bulk nonce for simplicity)
		if (isset($_POST['reval_delete_single']) && isset($_POST['reval_bulk_delete_nonce']) && wp_verify_nonce($_POST['reval_bulk_delete_nonce'], 'reval_bulk_delete')) {
			$id = sanitize_text_field($_POST['reval_delete_single']);
			$storage->delete_evaluation($id);
			add_settings_error('reval_delete', 'reval_delete_success', 'Deleted selected evaluation.', 'updated');
			settings_errors('reval_delete');
		}

		// Handle bulk delete
		if (isset($_POST['reval_bulk_delete']) && isset($_POST['reval_bulk_delete_nonce']) && wp_verify_nonce($_POST['reval_bulk_delete_nonce'], 'reval_bulk_delete')) {
			$ids = array_map('sanitize_text_field', (array) ($_POST['reval_selected'] ?? []));
			$deleted = $storage->delete_evaluations($ids);
			add_settings_error('reval_bulk_delete', 'reval_bulk_delete_success', "Deleted {$deleted} evaluations.", 'updated');
			settings_errors('reval_bulk_delete');
		}

		// Handle delete all
		if (isset($_POST['reval_clear_all']) && isset($_POST['reval_bulk_delete_nonce']) && wp_verify_nonce($_POST['reval_bulk_delete_nonce'], 'reval_bulk_delete')) {
			$storage->clear_all_evaluations();
			add_settings_error('reval_clear_all', 'reval_clear_all_success', 'Deleted all evaluations.', 'updated');
			settings_errors('reval_clear_all');
		}

		// Handle individual evaluation view
		if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
			$this->render_evaluation_details($_GET['id']);
			return;
		}

		$page = (int) ($_GET['paged'] ?? 1);
		$limit = 20;
		$offset = ($page - 1) * $limit;
		
		$evaluations = $storage->get_evaluations($limit, $offset);
		$total_evaluations = count($storage->get_evaluations(1000, 0)); // Get total count
		$total_pages = ceil($total_evaluations / $limit);

		?>
		<div class="wrap">
			<h1>Evaluation History</h1>
			
			<div class="tablenav top">
				<div class="tablenav-pages">
					<span class="displaying-num"><?php echo $total_evaluations; ?> items</span>
					<?php if ($total_pages > 1): ?>
						<?php
						$pagination_args = [
							'base' => add_query_arg('paged', '%#%'),
							'format' => '',
							'current' => $page,
							'total' => $total_pages
						];
						echo paginate_links($pagination_args);
						?>
					<?php endif; ?>
				</div>
			</div>

			<form method="post">
			<?php wp_nonce_field('reval_bulk_delete', 'reval_bulk_delete_nonce'); ?>
			<div style="margin:10px 0;">
				<input type="submit" name="reval_bulk_delete" class="button button-secondary" value="Delete Selected" onclick="return confirm('Delete selected evaluations?');" />
				<input type="submit" name="reval_clear_all" class="button" value="Delete All" onclick="return confirm('Delete ALL evaluations?');" />
			</div>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:24px;"><input type="checkbox" id="reval-select-all" onclick="document.querySelectorAll('.reval-select').forEach(cb=>cb.checked=this.checked);" /></th>
						<th>ID</th>
						<th>Date</th>
						<th>Commune</th>
						<th>Type</th>
						<th>Living Area (m²)</th>
						<th>Total Value</th>
						<th>User</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($evaluations as $id => $evaluation): ?>
					<tr>
						<td><input type="checkbox" class="reval-select" name="reval_selected[]" value="<?php echo esc_attr($id); ?>" /></td>
						<td><?php echo esc_html(substr($id, 0, 8)); ?>...</td>
						<td><?php echo esc_html(date('Y-m-d H:i', strtotime($evaluation['timestamp']))); ?></td>
						<td><?php echo esc_html($evaluation['input']['commune']); ?></td>
						<td><?php echo esc_html(ucfirst($evaluation['input']['type'])); ?></td>
						<td><?php echo esc_html($evaluation['input']['living_m2']); ?></td>
						<td><?php echo number_format($evaluation['result']['valeur_totale'], 0, ',', ' '); ?> €</td>
						<td><?php echo esc_html(get_userdata($evaluation['user_id'])->display_name ?? 'Unknown'); ?></td>
						<td>
							<button type="submit" name="reval_delete_single" value="<?php echo esc_attr($id); ?>" class="button button-small" onclick="return confirm('Delete this evaluation?');">Delete</button>
							<a href="<?php echo admin_url('admin.php?page=reval_history&action=view&id=' . $id); ?>" class="button button-small">View Details</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<div style="margin:10px 0;">
				<input type="submit" name="reval_bulk_delete" class="button button-secondary" value="Delete Selected" onclick="return confirm('Delete selected evaluations?');" />
			</div>
			</form>
		</div>
		<?php
	}

	private function render_evaluation_details(string $evaluation_id) {
		$storage = new REValuationStorage();
		$evaluation = $storage->get_evaluation($evaluation_id);
		
		if (!$evaluation) {
			echo '<div class="wrap"><h1>Evaluation Not Found</h1><p>The requested evaluation could not be found.</p></div>';
			return;
		}

		$input = $evaluation['input'];
		$result = $evaluation['result'];
		?>
		<div class="wrap">
			<h1>Evaluation Details</h1>
			<p><a href="<?php echo admin_url('admin.php?page=reval_history'); ?>" class="button">&larr; Back to History</a></p>
			
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
				<div class="evaluation-section" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
					<h2>Property Information</h2>
					<table class="form-table">
						<tr><th>Evaluation ID:</th><td><?php echo esc_html($evaluation_id); ?></td></tr>
						<tr><th>Date:</th><td><?php echo esc_html($evaluation['timestamp']); ?></td></tr>
						<tr><th>Commune:</th><td><?php echo esc_html($input['commune']); ?></td></tr>
						<tr><th>Property Type:</th><td><?php echo esc_html(ucfirst($input['type'])); ?></td></tr>
						<tr><th>Living Area:</th><td><?php echo esc_html($input['living_m2']); ?> m²</td></tr>
						<tr><th>Land Area:</th><td><?php echo esc_html($input['land_ares'] ?? 0); ?> ares</td></tr>
						<tr><th>Bedrooms:</th><td><?php echo esc_html($input['bedrooms'] ?? 0); ?></td></tr>
						<tr><th>Year Built:</th><td><?php echo esc_html($input['year_built'] ?? 'N/A'); ?></td></tr>
						<tr><th>Energy Efficiency:</th><td><?php echo esc_html($input['energy_efficiency'] ?? 'N/A'); ?></td></tr>
						<tr><th>Location Type:</th><td><?php echo esc_html(ucfirst($input['location_type'] ?? 'N/A')); ?></td></tr>
						<tr><th>Condition:</th><td><?php echo esc_html(ucfirst($input['condition'] ?? 'N/A')); ?></td></tr>
					</table>
				</div>

				<div class="evaluation-section" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
					<h2>Surfaces & Features</h2>
					<table class="form-table">
						<tr><th>Balcony:</th><td><?php echo esc_html($input['balcony_m2'] ?? 0); ?> m²</td></tr>
						<tr><th>Terrace:</th><td><?php echo esc_html($input['terrace_m2'] ?? 0); ?> m²</td></tr>
						<tr><th>Private Garden:</th><td><?php echo esc_html($input['private_garden_m2'] ?? 0); ?> m²</td></tr>
						<tr><th>Shared Garden:</th><td><?php echo esc_html($input['shared_garden_m2'] ?? 0); ?> m²</td></tr>
						<tr><th>Cellar:</th><td><?php echo esc_html($input['cellar_m2'] ?? 0); ?> m²</td></tr>
						<tr><th>Garage Boxes:</th><td><?php echo esc_html($input['garage_box_units'] ?? 0); ?></td></tr>
						<tr><th>Indoor Parking:</th><td><?php echo esc_html($input['parking_indoor_units'] ?? 0); ?></td></tr>
						<tr><th>Outdoor Parking:</th><td><?php echo esc_html($input['parking_outdoor_units'] ?? 0); ?></td></tr>
					</table>
				</div>
			</div>

			<div class="evaluation-section" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin: 20px 0;">
				<h2>Valuation Results</h2>
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
					<div class="result-item" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
						<h3 style="margin: 0; color: #0073aa;">Total Value</h3>
						<p style="font-size: 1.5em; margin: 5px 0; font-weight: bold;"><?php echo number_format($result['valeur_totale'], 0, ',', ' '); ?> €</p>
					</div>
					<div class="result-item" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
						<h3 style="margin: 0; color: #0073aa;">Price per m²</h3>
						<p style="font-size: 1.2em; margin: 5px 0; font-weight: bold;"><?php echo number_format($result['prix_m2'], 0, ',', ' '); ?> €</p>
					</div>
					<div class="result-item" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
						<h3 style="margin: 0; color: #0073aa;">Weighted Area</h3>
						<p style="font-size: 1.2em; margin: 5px 0; font-weight: bold;"><?php echo number_format($result['weighted_m2'], 1, ',', ' '); ?> m²</p>
					</div>
					<div class="result-item" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 4px;">
						<h3 style="margin: 0; color: #0073aa;">Building Value</h3>
						<p style="font-size: 1.2em; margin: 5px 0; font-weight: bold;"><?php echo number_format($result['valeur_batie'], 0, ',', ' '); ?> €</p>
					</div>
				</div>

				<h3>Value Breakdown</h3>
				<table class="form-table">
					<tr><th>Land Value:</th><td><?php echo number_format($result['land_value'], 0, ',', ' '); ?> €</td></tr>
					<tr><th>Bedroom Bonus:</th><td><?php echo number_format($result['bedroom_bonus'], 0, ',', ' '); ?> €</td></tr>
					<tr><th>Forfaits Total:</th><td><?php echo number_format($result['forfaits_total'], 0, ',', ' '); ?> €</td></tr>
					<tr><th>Year Coefficient:</th><td><?php echo number_format($result['coef_year'] * 100, 1); ?>%</td></tr>
					<tr><th>Type Coefficient:</th><td><?php echo number_format($result['coef_type'] * 100, 1); ?>%</td></tr>
					<tr><th>Energy Bonus:</th><td><?php echo number_format($result['energy_bonus'] * 100, 1); ?>%</td></tr>
					<tr><th>Location Factor:</th><td><?php echo number_format($result['location_factor'] * 100, 1); ?>%</td></tr>
					<tr><th>Condition Factor:</th><td><?php echo number_format($result['condition_factor'] * 100, 1); ?>%</td></tr>
					<tr><th>Special Features:</th><td><?php echo number_format($result['special_features_bonus'] * 100, 1); ?>%</td></tr>
					<tr><th>Custom Adjustments:</th><td><?php echo number_format($result['custom_adjustments_total'] * 100, 1); ?>%</td></tr>
				</table>

				<h3>Value Range</h3>
				<div style="display: flex; gap: 20px; justify-content: center;">
					<div style="text-align: center;">
						<h4>Low</h4>
						<p style="font-size: 1.2em; font-weight: bold;"><?php echo number_format($result['range']['low'], 0, ',', ' '); ?> €</p>
					</div>
					<div style="text-align: center;">
						<h4>Mid</h4>
						<p style="font-size: 1.2em; font-weight: bold;"><?php echo number_format($result['range']['mid'], 0, ',', ' '); ?> €</p>
					</div>
					<div style="text-align: center;">
						<h4>High</h4>
						<p style="font-size: 1.2em; font-weight: bold;"><?php echo number_format($result['range']['high'], 0, ',', ' '); ?> €</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_statistics_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$storage = new REValuationStorage();
		$stats = $storage->get_statistics();

		?>
		<div class="wrap">
			<h1>Statistics & Analytics</h1>
			
			<div class="reval-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
				<div class="stats-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
					<h3>Total Evaluations</h3>
					<p style="font-size: 2em; margin: 0; color: #0073aa;"><?php echo number_format($stats['total_evaluations']); ?></p>
				</div>
				
				<div class="stats-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
					<h3>Average Value</h3>
					<p style="font-size: 2em; margin: 0; color: #0073aa;"><?php echo number_format($stats['average_value'], 0, ',', ' '); ?> €</p>
				</div>
				
				<div class="stats-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
					<h3>Most Common Commune</h3>
					<p style="font-size: 1.5em; margin: 0; color: #0073aa;"><?php echo esc_html($stats['most_common_commune'] ?? 'N/A'); ?></p>
				</div>
				
				<div class="stats-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
					<h3>Most Common Type</h3>
					<p style="font-size: 1.5em; margin: 0; color: #0073aa;"><?php echo esc_html(ucfirst($stats['most_common_type'] ?? 'N/A')); ?></p>
				</div>
				
				<div class="stats-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
					<h3>Value Range</h3>
					<p style="font-size: 1.2em; margin: 0; color: #0073aa;">
						<?php echo number_format($stats['value_range']['min'], 0, ',', ' '); ?> € - 
						<?php echo number_format($stats['value_range']['max'], 0, ',', ' '); ?> €
					</p>
				</div>
			</div>

			<h2>Data Management</h2>
			<form method="post" onsubmit="return confirm('Are you sure you want to clean up old data? This action cannot be undone.');">
				<?php wp_nonce_field('reval_cleanup', 'reval_cleanup_nonce'); ?>
				<p>
					<label for="cleanup_days">Clean up evaluations older than:</label>
					<select name="cleanup_days" id="cleanup_days">
						<option value="365">1 year</option>
						<option value="180">6 months</option>
						<option value="90">3 months</option>
						<option value="30">1 month</option>
					</select>
					<input type="submit" name="cleanup_data" value="Clean Up Old Data" class="button button-secondary" />
				</p>
			</form>
		</div>
		<?php
	}

	private function handle_formulas_save() {
		$formulas = [
			'land_coefficient' => (float) $_POST['land_coefficient'],
			'bedroom_bonus_multiplier' => (float) $_POST['bedroom_bonus_multiplier'],
			'range_low_percent' => (float) $_POST['range_low_percent'],
			'range_high_percent' => (float) $_POST['range_high_percent'],
			'type_coefficients' => $_POST['type_coefficients'] ?? [],
			'energy_efficiency_bonus' => $_POST['energy_efficiency_bonus'] ?? [],
			'location_factors' => $_POST['location_factors'] ?? [],
			'condition_factors' => $_POST['condition_factors'] ?? []
		];

		$storage = new REValuationStorage();
		$storage->save_formulas($formulas);
		
		add_settings_error('reval_formulas', 'reval_formulas_saved', 'Formulas saved successfully!', 'updated');
		settings_errors('reval_formulas');
	}

	// New REST API handlers
	public function handle_get_evaluations(\WP_REST_Request $request) {
		$storage = new REValuationStorage();
		$limit = (int) $request->get_param('limit') ?: 50;
		$offset = (int) $request->get_param('offset') ?: 0;
		
		$evaluations = $storage->get_evaluations($limit, $offset);
		return new \WP_REST_Response(['evaluations' => $evaluations], 200);
	}

	public function handle_get_evaluation(\WP_REST_Request $request) {
		$storage = new REValuationStorage();
		$evaluation_id = $request->get_param('id');
		$evaluation = $storage->get_evaluation($evaluation_id);
		
		if (!$evaluation) {
			return new \WP_REST_Response(['error' => 'Evaluation not found'], 404);
		}
		
		return new \WP_REST_Response(['evaluation' => $evaluation], 200);
	}

	public function handle_get_statistics(\WP_REST_Request $request) {
		$storage = new REValuationStorage();
		$stats = $storage->get_statistics();
		return new \WP_REST_Response(['statistics' => $stats], 200);
	}

	public function handle_get_formulas(\WP_REST_Request $request) {
		$storage = new REValuationStorage();
		$formulas = $storage->get_formulas();
		return new \WP_REST_Response(['formulas' => $formulas], 200);
	}

	public function handle_save_formulas(\WP_REST_Request $request) {
		$formulas = $request->get_json_params();
		
		if (!$formulas) {
			return new \WP_REST_Response(['error' => 'Invalid JSON data'], 400);
		}
		
		$storage = new REValuationStorage();
		$storage->save_formulas($formulas);
		
		return new \WP_REST_Response(['status' => 'ok', 'message' => 'Formulas saved successfully'], 200);
	}

	public function handle_get_communes(\WP_REST_Request $request) {
		$storage = new REValuationStorage();
		$communes = $storage->get_communes();
		// Return as array of {label, value, price}
		$items = [];
		foreach ($communes as $name => $price) {
			$items[] = [
				'label' => $name,
				'value' => $name,
				'price' => (float) $price
			];
		}
		return new \WP_REST_Response(['communes' => $items], 200);
	}

	public function register_frontend_assets() {
		$ver = '0.1.1';
		$plugin_url = plugin_dir_url(__FILE__);
		// Bootstrap from CDN
		wp_register_style('bootstrap-5', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', [], '5.3.3');
		wp_register_script('bootstrap-5', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], '5.3.3', true);
		// Plugin assets
		wp_register_style('reval-frontend', $plugin_url . 'frontend/assets/css/reval.css', ['bootstrap-5'], $ver);
		wp_register_script('reval-frontend', $plugin_url . 'frontend/assets/js/reval-form.js', ['bootstrap-5'], $ver, true);
	}

	public function render_frontend_form(): string {
		// Enqueue assets only when shortcode renders
		wp_enqueue_style('bootstrap-5');
		wp_enqueue_style('reval-frontend');
		wp_enqueue_script('bootstrap-5');
		wp_enqueue_script('reval-frontend');

		// Localize config for JS
		$cfg = [
			'root' => esc_url_raw(get_rest_url(null, self::REST_NAMESPACE . '/')),
			'nonce' => wp_create_nonce('wp_rest')
		];
		wp_localize_script('reval-frontend', 'REVAL_CFG', $cfg);

		ob_start();
		$tpl = __DIR__ . '/frontend/form.php';
		if (file_exists($tpl)) {
			include $tpl;
		} else {
			echo '<div class="alert alert-danger">Form template not found.</div>';
		}
		return ob_get_clean();
	}
}

new REValuationPlugin();
