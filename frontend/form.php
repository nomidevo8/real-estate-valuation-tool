
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css"/>

    <div class="reval-container">
        <h2 class="mb-3">Real Estate Valuation</h2>
        <div id="reval-alert" class="alert d-none" role="alert"></div>

        <div class="reval-wrapper">
            <!-- Left Panel - Form -->
            <div class="reval-left-panel">
                <div class="reval-icon-container">
                    <div class="reval-icon">
                        <div class="reval-icon-circle">
                            <div class="reval-icon-inner">🏠</div>
                        </div>
                    </div>
                </div>

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
                            <input type="number" class="form-control" required name="year_built" min="1800" max="2100" />
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
                            <p class="text-muted">Ready to evaluate? Click below to proceed.</p>
                        </div>

                        <div class="reval-step d-none" data-field="code_confirmation">
                            <p class="text-muted">Please enter the confirmation code sent to your email.</p>
                            <input type="text" class="form-control" id="reval-code" placeholder="Code from your email" required />
                            <div id="reval-code-error" class="text-danger small mt-1 d-none"></div>
                            <button type="button" class="btn btn-success mt-2" id="reval-confirm-btn">Confirm Code</button>
                        </div>

                        <div class="d-flex justify-content-between mt-3 dev-navigation">
                            <button class="btn btn-outline-secondary" data-reval-prev type="button">Back</button>
                            <button class="btn btn-primary" data-reval-next type="button">Next</button>
                            <button class="btn btn-success d-none" id="reval-submit" type="button">Evaluate</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Right Panel - Summary -->
            <div class="reval-right-panel">
                <div class="reval-summary-header">
                    <h3>Your information</h3>
                    <p>Click below to edit your information</p>
                </div>

                <div class="reval-summary-section">
                    <div class="reval-summary-section-header" onclick="toggleSection(this)">
                        <h4>
                            <span>▶</span> Home & you
                        </h4>
                        <span class="chevron">▼</span>
                    </div>
                    <div class="reval-summary-section-body" id="summary-basic">
                        <div class="reval-summary-empty">No information entered yet</div>
                    </div>
                </div>

                <div class="reval-summary-section">
                    <div class="reval-summary-section-header" onclick="toggleSection(this)">
                        <h4>
                            <span>▶</span> Property details
                        </h4>
                        <span class="chevron">▼</span>
                    </div>
                    <div class="reval-summary-section-body" id="summary-details">
                        <div class="reval-summary-empty">No information entered yet</div>
                    </div>
                </div>

                <div class="reval-summary-section">
                    <div class="reval-summary-section-header" onclick="toggleSection(this)">
                        <h4>
                            <span>▶</span> Additional features
                        </h4>
                        <span class="chevron">▼</span>
                    </div>
                    <div class="reval-summary-section-body" id="summary-features">
                        <div class="reval-summary-empty">No information entered yet</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div id="reval-results" class="mt-4 d-none" >
            <div class="card">
                <div class="card-header">Valuation Results</div>
                <div class="card-body">
                    <div class="row text-center" id="reval-summary-cards"></div>
                    <hr />
                    <h5>Details</h5>
                    <div id="reval-details"></div>
                    <div class="mt-3 text-center">
                        <button type="button" class="btn btn-secondary" onclick="location.reload();">Go Back</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mock configuration
        window.REVAL_CFG = { root: '/wp-json/reval/v1/', nonce: 'demo-nonce' };

        function toggleSection(header) {
            const body = header.nextElementSibling;
            header.classList.toggle('collapsed');
            body.classList.toggle('collapsed');
        }
		function collapseSummarySection(sectionId) {
			const header = document.querySelector(`#${sectionId}`).closest('.reval-summary-section').querySelector('.reval-summary-section-header');
			const body = header.nextElementSibling;
			header.classList.add('collapsed');
			body.classList.add('collapsed');
		}
        function updateSummaryPanel() {
            const form = document.querySelector('#reval-form');
            const formData = new FormData(form);
            
            const basicFields = ['type', 'email', 'phone', 'commune'];
            const detailFields = ['living_m2', 'land_ares', 'bedrooms', 'year_built', 'energy_efficiency', 'location_type', 'condition'];
            const featureFields = ['balcony_m2', 'terrace_m2', 'private_garden_m2', 'shared_garden_m2', 'cellar_m2', 'garage_box_units', 'parking_indoor_units', 'parking_outdoor_units'];

            updateSummarySection('summary-basic', basicFields, formData);
            updateSummarySection('summary-details', detailFields, formData);
            updateSummarySection('summary-features', featureFields, formData);
			    // Collapse each section if all its fields are filled
			if (basicFields.every(field => { const v = formData.get(field); return v && v !== ''; })) {
				collapseSummarySection('summary-basic');
			}
			if (detailFields.every(field => { const v = formData.get(field); return v && v !== '' && v !== '0'; })) {
				collapseSummarySection('summary-details');
			}
			if (featureFields.every(field => { const v = formData.get(field); return v && v !== '' && v !== '0'; })) {
				collapseSummarySection('summary-features');
			}
        }

		function attachSummaryListeners() {
			const form = document.querySelector('#reval-form');
			
			// Listen to all inputs and selects (except those with card display)
			form.querySelectorAll('input, select:not([data-display="cards"])').forEach(input => {
				input.addEventListener('change', updateSummaryPanel);
				input.addEventListener('input', updateSummaryPanel);
			});
		}
		window.addEventListener('DOMContentLoaded', function() {
			attachSummaryListeners();
			enhanceSelectsAsCards(document);
			showStep(0);
		});


        function updateSummarySection(sectionId, fields, formData) {
            const section = document.getElementById(sectionId);
            let html = '';
            let hasData = false;

            fields.forEach(field => {
                const value = formData.get(field);
                if (value && value !== '' && value !== '0') {
                    hasData = true;
                    const label = field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    html += `
                        <div class="reval-summary-item">
                            <span class="reval-summary-item-label">${label}</span>
                            <span class="reval-summary-item-value">${value}</span>
                        </div>
                    `;
                }
            });

            section.innerHTML = hasData ? html : '<div class="reval-summary-empty">No information entered yet</div>';
        }


    </script>
 