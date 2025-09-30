<div class="reval-container container my-4">
	<h2 class="mb-3">Real Estate Valuation</h2>
	<div id="reval-alert" class="alert d-none" role="alert"></div>

	<form id="reval-form" novalidate>
		<div class="border rounded p-3 bg-light">
			<div class="mb-2 small text-muted" id="reval-step-counter">Step 1</div>

			<!-- One field per step -->
			<div class="reval-step" data-field="type">
				<label class="form-label">Property Type</label>
				<select class="form-select" name="type" required data-display="cards">
					<option value="">Select type</option>
					<option value="apartment">Apartment</option>
					<option value="house">House</option>
					<option value="villa">Villa</option>
				</select>
			</div>

			<div class="reval-step d-none" data-field="email">
				<label class="form-label">Email</label>
				<input type="email" class="form-control" name="email" placeholder="you@example.com" required />
			</div>

			<div class="reval-step d-none" data-field="phone">
				<label class="form-label">Phone</label>
				<input type="tel" class="form-control" name="phone" placeholder="+352 123 456 789" pattern="[+0-9 ()-]{6,}" required />
				<div class="form-text">Digits, spaces, +, (), - allowed</div>
			</div>

			<div class="reval-step d-none" data-field="commune">
				<label class="form-label">Commune</label>
				<select class="form-select" name="commune" id="reval-commune" required>
					<option value="">Loading communes...</option>
				</select>
			</div>

			<div class="reval-step d-none" data-field="living_m2">
				<label class="form-label">Living area (m²)</label>
				<input type="number" class="form-control" name="living_m2" min="0" step="0.1" required />
			</div>

			<div class="reval-step d-none" data-field="land_ares">
				<label class="form-label">Land (ares)</label>
				<input type="number" class="form-control" name="land_ares" min="0" step="0.1" value="0" />
			</div>

			<div class="reval-step d-none" data-field="bedrooms">
				<label class="form-label">Bedrooms</label>
				<input type="number" class="form-control" name="bedrooms" min="0" step="1" value="0" />
			</div>

			<div class="reval-step d-none" data-field="apply_year_coef">
				<label class="form-label">Apply year coefficient?</label>
				<select class="form-select" name="apply_year_coef" data-display="cards">
					<option>No</option>
					<option>Yes</option>
				</select>
			</div>

			<div class="reval-step d-none" data-field="year_built">
				<label class="form-label">Year built</label>
				<input type="number" class="form-control" name="year_built" min="1800" max="2100" />
			</div>

			<div class="reval-step d-none" data-field="energy_efficiency">
				<label class="form-label">Energy efficiency</label>
				<select class="form-select" name="energy_efficiency" data-display="cards">
					<option>A</option><option>B</option><option selected>C</option><option>D</option><option>E</option><option>F</option><option>G</option>
				</select>
			</div>

			<div class="reval-step d-none" data-field="location_type">
				<label class="form-label">Location type</label>
				<select class="form-select" name="location_type" data-display="cards">
					<option value="city_center">City center</option>
					<option value="residential" selected>Residential</option>
					<option value="suburban">Suburban</option>
					<option value="rural">Rural</option>
				</select>
			</div>

			<div class="reval-step d-none" data-field="condition">
				<label class="form-label">Condition</label>
				<select class="form-select" name="condition" data-display="cards">
					<option value="excellent">Excellent</option>
					<option value="good">Good</option>
					<option value="average" selected>Average</option>
					<option value="poor">Poor</option>
					<option value="very_poor">Very poor</option>
				</select>
			</div>

			<div class="reval-step d-none" data-field="balcony_m2">
				<label class="form-label">Balcony (m²)</label>
				<input type="number" class="form-control" name="balcony_m2" min="0" step="0.1" value="0" />
			</div>

			<div class="reval-step d-none" data-field="terrace_m2">
				<label class="form-label">Terrace (m²)</label>
				<input type="number" class="form-control" name="terrace_m2" min="0" step="0.1" value="0" />
			</div>

			<div class="reval-step d-none" data-field="private_garden_m2">
				<label class="form-label">Private garden (m²)</label>
				<input type="number" class="form-control" name="private_garden_m2" min="0" step="0.1" value="0" />
			</div>

			<div class="reval-step d-none" data-field="shared_garden_m2">
				<label class="form-label">Shared garden (m²)</label>
				<input type="number" class="form-control" name="shared_garden_m2" min="0" step="0.1" value="0" />
			</div>

			<div class="reval-step d-none" data-field="cellar_m2">
				<label class="form-label">Cellar (m²)</label>
				<input type="number" class="form-control" name="cellar_m2" min="0" step="0.1" value="0" />
			</div>

			<div class="reval-step d-none" data-field="garage_box_units">
				<label class="form-label">Garage boxes</label>
				<input type="number" class="form-control" name="garage_box_units" min="0" step="1" value="0" />
			</div>

			<div class="reval-step d-none" data-field="parking_indoor_units">
				<label class="form-label">Indoor parking</label>
				<input type="number" class="form-control" name="parking_indoor_units" min="0" step="1" value="0" />
			</div>

			<div class="reval-step d-none" data-field="parking_outdoor_units">
				<label class="form-label">Outdoor parking</label>
				<input type="number" class="form-control" name="parking_outdoor_units" min="0" step="1" value="0" />
			</div>

			<div class="reval-step d-none" data-field="review">
				<p class="text-muted">Review your inputs and submit to see valuation.</p>
				<div id="reval-review" class="row g-2"></div>
			</div>

			<div class="d-flex justify-content-between mt-3">
				<button class="btn btn-outline-secondary" data-reval-prev type="button">Back</button>
				<button class="btn btn-primary" data-reval-next type="button">Next</button>
				<button class="btn btn-success d-none" id="reval-submit" type="button">Evaluate</button>
			</div>
		</div>
	</form>

	<!-- Results -->
	<div id="reval-results" class="mt-4 d-none">
		<div class="card">
			<div class="card-header">Valuation Results</div>
			<div class="card-body">
				<div class="row text-center" id="reval-summary-cards"></div>
				<hr />
				<h5>Details</h5>
				<div id="reval-details"></div>
			</div>
		</div>
	</div>
</div>

