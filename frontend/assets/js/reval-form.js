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

    function getSteps(){ return qsa('.reval-step'); }
    function currentStepIndex(){ return getSteps().findIndex(function(s){ return !s.classList.contains('d-none'); }); }
    function showStep(idx){
        var steps = getSteps();
        steps.forEach(function(s, i){ s.classList.toggle('d-none', i !== idx); });
        var counter = qs('#reval-step-counter');
        if(counter){ counter.textContent = 'Step ' + (idx+1) + ' of ' + steps.length; }
        // Controls
        qs('[data-reval-prev]').classList.toggle('d-none', idx === 0);
        var isLast = idx === steps.length - 1;
        qs('[data-reval-next]').classList.toggle('d-none', isLast);
        qs('#reval-submit').classList.toggle('d-none', !isLast);
        // If review step, render review
        if(steps[idx].getAttribute('data-field') === 'review'){
            var form = qs('#reval-form');
            var payload = serializeForm(form);
            var review = qs('#reval-review');
            if(review){
                review.innerHTML = Object.keys(payload).map(function(k){
                    var v = payload[k];
                    return '<div class="col-md-6"><div class="border rounded p-2 bg-white"><small class="text-muted">' + k.replace(/_/g,' ') + '</small><div class="fw-semibold">' + v + '</div></div></div>';
                }).join('');
            }
        }
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

		var r = res.result;
		cards.innerHTML = [
			{title: 'Total Value', value: formatCurrency(r.valeur_totale)},
			{title: 'Price per m²', value: formatCurrency(r.prix_m2)},
			{title: 'Weighted Area', value: formatNumber(r.weighted_m2,1) + ' m²'},
			{title: 'Building Value', value: formatCurrency(r.valeur_batie)}
		].map(function(c){
			return '<div class="col-12 col-sm-6 col-lg-3 mb-3"><div class="card"><div class="card-body"><h6 class="card-subtitle mb-2 text-muted">' + c.title + '</h6><div class="fs-5 fw-bold">' + c.value + '</div></div></div></div>';
		}).join('');

		details.innerHTML = '' +
			'<div class="row">' +
				'<div class="col-md-6"><ul class="list-group mb-3">' +
					'<li class="list-group-item d-flex justify-content-between"><span>Land value</span><strong>' + formatCurrency(r.land_value) + '</strong></li>' +
					'<li class="list-group-item d-flex justify-content-between"><span>Bedroom bonus</span><strong>' + formatCurrency(r.bedroom_bonus) + '</strong></li>' +
					'<li class="list-group-item d-flex justify-content-between"><span>Forfaits total</span><strong>' + formatCurrency(r.forfaits_total) + '</strong></li>' +
				'</ul></div>' +
				'<div class="col-md-6"><ul class="list-group mb-3">' +
					'<li class="list-group-item d-flex justify-content-between"><span>Year coef</span><strong>' + formatNumber(r.coef_year*100,1) + '%</strong></li>' +
					'<li class="list-group-item d-flex justify-content-between"><span>Type coef</span><strong>' + formatNumber(r.coef_type*100,1) + '%</strong></li>' +
					'<li class="list-group-item d-flex justify-content-between"><span>Energy bonus</span><strong>' + formatNumber(r.energy_bonus*100,1) + '%</strong></li>' +
					'<li class="list-group-item d-flex justify-content-between"><span>Location factor</span><strong>' + formatNumber(r.location_factor*100,1) + '%</strong></li>' +
					'<li class="list-group-item d-flex justify-content-between"><span>Condition factor</span><strong>' + formatNumber(r.condition_factor*100,1) + '%</strong></li>' +
				'</ul></div>' +
			'</div>' +
			'<div class="row"><div class="col-12"><div class="alert alert-info">' +
				'Range: <strong>' + formatCurrency(r.range.low) + '</strong> - <strong>' + formatCurrency(r.range.high) + '</strong>' +
			'</div></div></div>';
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
			})
			.catch(function(err){ showAlert('danger', 'Failed to load communes.'); });
	}

	document.addEventListener('DOMContentLoaded', function(){
		var form = qs('#reval-form');
		if(!form) return;
		loadCommunes();

        showStep(0);
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

        qs('#reval-submit').addEventListener('click', function(){
			hideAlert();
			if(!form.reportValidity()){ showAlert('warning', 'Please fill required fields.'); return; }
			var payload = serializeForm(form);
			fetch(REVAL_CFG.root + 'evaluate', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': REVAL_CFG.nonce },
				body: JSON.stringify(payload)
			})
			.then(function(r){ return r.json(); })
			.then(function(json){
				if(json.error){ showAlert('danger', json.error); return; }
				renderResults(json);
			})
			.catch(function(err){ showAlert('danger', 'Evaluation failed.'); });
		});
	});
})();


