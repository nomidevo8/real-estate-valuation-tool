(function(){
    function qs(sel, root){ return (root||document).querySelector(sel); }
    function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }
    function showAlert(type, msg){
        var el = qs('#reval-alert');
        el.className = 'alert alert-' + type;
        el.textContent = msg;
        el.classList.remove('d-none');
    }
    function hideAlert(){ var el = qs('#reval-alert'); if(el){ el.classList.add('d-none'); } }

    function enhanceSelectsAsCards(root){
        qsa('select[data-display="cards"]', root).forEach(function(sel){
            // build card group
            var wrapper = document.createElement('div');
            wrapper.className = 'reval-card-group row g-2';
            var current = sel.value;
            Array.prototype.slice.call(sel.options).forEach(function(opt){
                if(opt.value === '') return; // skip placeholder
                var col = document.createElement('div');
                col.className = 'col-6 col-md-3';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'reval-card btn w-100 ' + (opt.value === current ? 'reval-card-active btn-primary' : 'btn-outline-primary');
                btn.setAttribute('data-value', opt.value);
                btn.textContent = opt.text;
                btn.addEventListener('click', function(){
                    sel.value = opt.value;
                    // update active state
                    qsa('.reval-card', wrapper).forEach(function(b){ b.classList.remove('reval-card-active', 'btn-primary'); b.classList.add('btn-outline-primary'); });
                    btn.classList.add('reval-card-active', 'btn-primary');
                    btn.classList.remove('btn-outline-primary');
                });
                col.appendChild(btn);
                wrapper.appendChild(col);
            });
            sel.classList.add('d-none');
            sel.parentNode.insertBefore(wrapper, sel.nextSibling);
        });
    }

    function getSteps(){ return qsa('.reval-step'); }
    function currentStepIndex(){ return getSteps().findIndex(function(s){ return !s.classList.contains('d-none'); }); }
    function showStep(idx){
        var steps = getSteps();
        steps.forEach(function(s, i){ s.classList.toggle('d-none', i !== idx); });
        var counter = qs('#reval-step-counter');
        if(counter){ counter.textContent = 'Step ' + (idx+1) + ' of ' + steps.length; }
        // Controls
        qs('[data-reval-prev]').classList.toggle('d-none', idx === 0);
        var isLast = idx === steps.length - 2;
        qs('[data-reval-next]').classList.toggle('d-none', isLast);
        qs('#reval-submit').classList.toggle('d-none', !isLast);
        // If review step, do nothing (no summary rendering)
    }
    function switchStep(delta){
        var idx = currentStepIndex();
        var steps = getSteps();
        var nextIdx = Math.min(Math.max(idx + delta, 0), steps.length - 1);
        if(nextIdx !== idx){ showStep(nextIdx); }
    }

    function serializeForm(form){
		var data = {};
		new FormData(form).forEach(function(v,k){
			if(v === '') return;
			if(/_m2$|_units$|^living_m2$|^land_ares$|^bedrooms$|^year_built$/.test(k)){
				data[k] = Number(v);
			} else {
				data[k] = v;
			}
		});
		return data;
	}

	function formatCurrency(num){
		try { return new Intl.NumberFormat('fr-FR', {maximumFractionDigits:0}).format(num) + ' €'; } catch(e){ return num + ' €'; }
	}
	function formatNumber(num, digits){
		try { return new Intl.NumberFormat('fr-FR', {maximumFractionDigits:digits||1}).format(num); } catch(e){ return String(num); }
	}

    function renderResults(res){
        var wrap = qs('#reval-results');
        var cards = qs('#reval-summary-cards');
        var details = qs('#reval-details');
        wrap.classList.remove('d-none');
        var wrapper = document.querySelector('.reval-container .reval-wrapper');
        if (wrapper) {
            wrapper.style.minHeight = 'unset';
        }

        var r = res.result;
        
        // Main summary cards with range
        cards.innerHTML = 
            '<div class="col-12 mb-4">' +
                '<div class="card border-0 shadow-sm">' +
                    '<div class="card-body text-center py-4">' +
                        '<h5 class="text-muted mb-3">Estimated Property Value</h5>' +
                        '<div class="row align-items-center">' +
                            '<div class="col-md-4">' +
                                '<div class="text-muted small">MINIMUM</div>' +
                                '<div class="fs-4 fw-bold text-secondary">' + formatCurrency(r.range.low) + '</div>' +
                            '</div>' +
                            '<div class="col-md-4">' +
                                '<div class="text-muted small">ESTIMATED VALUE</div>' +
                                '<div class="fs-2 fw-bold text-primary">' + formatCurrency(r.range.mid) + '</div>' +
                                '<div class="text-muted small mt-1">' + formatCurrency(r.prix_m2) + ' per m²</div>' +
                            '</div>' +
                            '<div class="col-md-4">' +
                                '<div class="text-muted small">MAXIMUM</div>' +
                                '<div class="fs-4 fw-bold text-secondary">' + formatCurrency(r.range.high) + '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="col-12 col-sm-6 col-lg-3 mb-3">' +
                '<div class="card h-100">' +
                    '<div class="card-body">' +
                        '<h6 class="card-subtitle mb-2 text-muted">Weighted Area</h6>' +
                        '<div class="fs-5 fw-bold">' + formatNumber(r.weighted_m2,1) + ' m²</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="col-12 col-sm-6 col-lg-3 mb-3">' +
                '<div class="card h-100">' +
                    '<div class="card-body">' +
                        '<h6 class="card-subtitle mb-2 text-muted">Building Value</h6>' +
                        '<div class="fs-5 fw-bold">' + formatCurrency(r.valeur_batie) + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="col-12 col-sm-6 col-lg-3 mb-3">' +
                '<div class="card h-100">' +
                    '<div class="card-body">' +
                        '<h6 class="card-subtitle mb-2 text-muted">Land Value</h6>' +
                        '<div class="fs-5 fw-bold">' + formatCurrency(r.land_value) + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="col-12 col-sm-6 col-lg-3 mb-3">' +
                '<div class="card h-100">' +
                    '<div class="card-body">' +
                        '<h6 class="card-subtitle mb-2 text-muted">Base Value</h6>' +
                        '<div class="fs-5 fw-bold">' + formatCurrency(r.breakdown.base_value) + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
    
        details.innerHTML = 
            '<div class="row g-3">' +
                // Value Components
                '<div class="col-lg-6">' +
                    '<div class="card h-100">' +
                        '<div class="card-header bg-light" style="background: #2c3e50;">' +
                            '<h6 class="mb-0">Value Components</h6>' +
                        '</div>' +
                        '<div class="card-body">' +
                            '<ul class="list-group list-group-flush">' +
                                '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                                    '<span>Building Value</span>' +
                                    '<strong class="text-primary">' + formatCurrency(r.valeur_batie) + '</strong>' +
                                '</li>' +
                                '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                                    '<span>Land Value</span>' +
                                    '<strong>' + formatCurrency(r.land_value) + '</strong>' +
                                '</li>' +
                                '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                                    '<span>Bedroom Bonus</span>' +
                                    '<strong>' + formatCurrency(r.bedroom_bonus) + '</strong>' +
                                '</li>' +
                                '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                                    '<span>Additional Features</span>' +
                                    '<strong>' + formatCurrency(r.forfaits_total) + '</strong>' +
                                '</li>' +
                                '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                                    '<span>Special Features Bonus</span>' +
                                    '<strong>' + formatCurrency(r.special_features_bonus) + '</strong>' +
                                '</li>' +
                                '<li class="list-group-item d-flex justify-content-between align-items-center bg-light">' +
                                    '<span class="fw-bold">Total Coefficient Applied</span>' +
                                    '<strong class="text-success">' + formatNumber(r.total_coefficient*100,2) + '%</strong>' +
                                '</li>' +
                            '</ul>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                
                // Adjustment Factors
                '<div class="col-lg-6">' +
                    '<div class="card h-100">' +
                        '<div class="card-header bg-light" style="background: #2c3e50;">' +
                            '<h6 class="mb-0">Adjustment Factors</h6>' +
                        '</div>' +
                        '<div class="card-body">' +
                            '<ul class="list-group list-group-flush">' +
                                '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                                    '<span>Year Built Adjustment</span>' +
                                    '<strong class="' + (r.coef_year >= 0 ? 'text-success' : 'text-danger') + '">' + 
                                    (r.coef_year >= 0 ? '+' : '') + formatNumber(r.coef_year*100,1) + '%</strong>' +
                                '</li>' +
                                '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                                    '<span>Property Type Adjustment</span>' +
                                    '<strong class="' + (r.coef_type >= 0 ? 'text-success' : 'text-danger') + '">' + 
                                    (r.coef_type >= 0 ? '+' : '') + formatNumber(r.coef_type*100,1) + '%</strong>' +
                                '</li>' +
                                '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                                    '<span>Energy Efficiency Bonus</span>' +
                                    '<strong class="' + (r.energy_bonus >= 0 ? 'text-success' : 'text-danger') + '">' + 
                                    (r.energy_bonus >= 0 ? '+' : '') + formatNumber(r.energy_bonus*100,1) + '%</strong>' +
                                '</li>' +
                                '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                                    '<span>Location Factor</span>' +
                                    '<strong class="' + (r.location_factor >= 0 ? 'text-success' : 'text-danger') + '">' + 
                                    (r.location_factor >= 0 ? '+' : '') + formatNumber(r.location_factor*100,1) + '%</strong>' +
                                '</li>' +
                                '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                                    '<span>Condition Factor</span>' +
                                    '<strong class="' + (r.condition_factor >= 0 ? 'text-success' : 'text-danger') + '">' + 
                                    (r.condition_factor >= 0 ? '+' : '') + formatNumber(r.condition_factor*100,1) + '%</strong>' +
                                '</li>' +
                                '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                                    '<span>Custom Adjustments</span>' +
                                    '<strong class="' + (r.custom_adjustments_total >= 0 ? 'text-success' : 'text-danger') + '">' + 
                                    (r.custom_adjustments_total >= 0 ? '+' : '') + formatCurrency(r.custom_adjustments_total) + '</strong>' +
                                '</li>' +
                            '</ul>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                
                // Evaluation Info
                '<div class="col-12">' +
                    '<div class="card">' +
                        '<div class="card-body">' +
                            '<div class="row text-center">' +
                                '<div class="col-md-6">' +
                                    '<small class="text-muted">Evaluation ID</small>' +
                                    '<div class="fw-semibold">' + r.evaluation_id + '</div>' +
                                '</div>' +
                                '<div class="col-md-6">' +
                                    '<small class="text-muted">Timestamp</small>' +
                                    '<div class="fw-semibold">' + r.timestamp + '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="text-center mt-4">' +
                '<button type="button" class="btn btn-secondary btn-lg px-5" onclick="location.reload();">New Evaluation</button>' +
            '</div>';
    }

    function loadCommunes(){
		var select = qs('#reval-commune');
		fetch(REVAL_CFG.root + 'communes', { headers: { 'X-WP-Nonce': REVAL_CFG.nonce } })
			.then(function(r){ return r.json(); })
			.then(function(json){
				var items = json.communes || [];
				if(items.length === 0){ select.innerHTML = '<option value="">No communes available</option>'; return; }
				select.innerHTML = '<option value="">Select commune</option>' + items.map(function(it){
					return '<option value="' + it.value.replace(/"/g,'&quot;') + '">' + it.label + '</option>';
				}).join('');
                // Enhance with Choices.js (searchable)
                try {
                    if (select._choicesInstance) { select._choicesInstance.destroy(); }
                    var inst = new Choices(select, { searchEnabled: true, shouldSort: false, itemSelectText: '', placeholder: true, placeholderValue: 'Select commune' });
                    select._choicesInstance = inst;
                } catch (e) {}
			})
			.catch(function(err){ showAlert('danger', 'Failed to load communes.'); });
	}

	document.addEventListener('DOMContentLoaded', function(){
		var form = qs('#reval-form');
		if(!form) return;
		loadCommunes();

        showStep(0);
        enhanceSelectsAsCards(form);
        qsa('[data-reval-next]').forEach(function(btn){ btn.addEventListener('click', function(){
            // validate current visible field if required
            var step = getSteps()[currentStepIndex()];
            var field = step.querySelector('input, select, textarea');
            if(field && field.hasAttribute('required') && (field.value === '' || field.value == null)){
                field.focus(); showAlert('warning', 'Please complete the field'); return;
            }
            switchStep(1);
        }); });
        qsa('[data-reval-prev]').forEach(function(btn){ btn.addEventListener('click', function(){ switchStep(-1); }); });

        // Main submit button
        qs('#reval-submit').addEventListener('click', function(){
            hideAlert();
            var steps = getSteps();
            var isLast = steps.length - 2 === steps.length - 2;
            if(!isLast){
                if(!form.reportValidity()){ showAlert('warning', 'Please fill required fields.'); return; }
            }

            var payload = serializeForm(form);

            // Step 1: Send request to /evaluate-request
            fetch(REVAL_CFG.root + 'evaluate-request', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': REVAL_CFG.nonce },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(json => {
                if(json.error){ showAlert('danger', json.error); return; }

                // Show code input to user
                showStepForCodeConfirmation();
            })
            .catch(err => { showAlert('danger', 'Request failed.'); });
        });

        function showStepForCodeConfirmation() {
            var codeStep = qs('[data-field="code_confirmation"]');
            var devNavigation = qs('.dev-navigation');
            var devNavigation = qs('.dev-navigation');
            var revalForm = qs('#reval-form');
            var revalRightPanel = qs('.reval-right-panel');
            if(!codeStep) return;
        
            // Hide the original submit button
            qs('#reval-submit').classList.add('d-none');
            devNavigation.classList.add('d-none');
        
            // Move to the code confirmation step
            switchStep(1); 
        
            var codeInput = codeStep.querySelector('#reval-code');
            var errorDiv = codeStep.querySelector('#reval-code-error');
            var confirmBtn = codeStep.querySelector('#reval-confirm-btn');
        
            confirmBtn.addEventListener('click', function() {
                var code = codeInput.value.trim();
                if(!code){
                    errorDiv.textContent = 'Please enter the code.';
                    errorDiv.classList.remove('d-none');
                    codeInput.classList.add('is-invalid');
                    return;
                }
        
                var payload = { email: form.querySelector('[name="email"]').value.trim(), code: code };
        
                fetch(REVAL_CFG.root + 'evaluate-confirm', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': REVAL_CFG.nonce },
                    body: JSON.stringify(payload)
                })
                .then(r => r.json())
                .then(json => {
                    if(json.error){
                        errorDiv.textContent = json.error;
                        errorDiv.classList.remove('d-none');
                        codeInput.classList.add('is-invalid');
                        return;
                    }
        
                    // Success: remove error styles
                    errorDiv.textContent = '';
                    errorDiv.classList.add('d-none');
                    codeInput.classList.remove('is-invalid');
                    
                    // Render results
                    renderResults(json);
                    revalForm.classList.add('d-none');
                    revalRightPanel.classList.add('d-none');
                    
                    // Optionally hide the code step
                    codeStep.classList.add('d-none');
                })
                .catch(err => {
                    errorDiv.textContent = 'Confirmation failed.';
                    errorDiv.classList.remove('d-none');
                    codeInput.classList.add('is-invalid');
                });
            });
        
            codeInput.addEventListener('input', function() {
                errorDiv.textContent = '';
                errorDiv.classList.add('d-none');
                codeInput.classList.remove('is-invalid');
            });
        }
        
        
	});
})();


