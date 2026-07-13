document.querySelectorAll('.tt-assignment-form').forEach(form=>{
 const start=form.querySelector('[name="start_method"]'),end=form.querySelector('[name="end_plan"]'),cut=form.querySelector('[name="prepared_cut_id"]'),base=form.querySelector('[name="operating_base_industry_id"]'),job=form.querySelector('[name="job_template_id"]'),pattern=form.querySelector('[name="operating_pattern"]');
 const show=(selector,visible)=>form.querySelectorAll(selector).forEach(el=>{el.hidden=!visible;el.querySelectorAll('input,select,textarea').forEach(control=>control.disabled=!visible);});
 const filterCars=enabled=>form.querySelectorAll('.tt-field-cars [data-location-id]').forEach(label=>{const visible=enabled&&label.dataset.locationId===base.value;label.hidden=!visible;label.querySelector('input').disabled=!visible;});
 const refresh=()=>{const method=start.value,carMode=method==='manual'||method==='coupled_selected';show('.tt-field-base',true);show('.tt-field-track',true);show('.tt-field-locos',true);show('.tt-field-cut',method==='prepared_cut');show('.tt-field-cars',carMode);show('.tt-field-end',end.value!=='return_origin');filterCars(carMode);if(cut){const option=cut.selectedOptions[0];form.querySelectorAll('.tt-cut-summary').forEach(el=>el.textContent=option?.dataset.summary||'');}};
 [start,end,cut,base].filter(Boolean).forEach(el=>el.addEventListener('change',refresh));
 if(job&&pattern)job.addEventListener('change',()=>{if(pattern.value==='')pattern.value='';});
 refresh();
});
