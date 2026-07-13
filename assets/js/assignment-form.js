document.querySelectorAll('.tt-assignment-form').forEach(form=>{
 const start=form.querySelector('[name="start_method"]'),end=form.querySelector('[name="end_plan"]'),predecessor=form.querySelector('[name="predecessor_assignment_id"]'),cut=form.querySelector('[name="prepared_cut_id"]');
 const show=(selector,visible)=>form.querySelectorAll(selector).forEach(el=>{el.hidden=!visible;el.querySelectorAll('input,select,textarea').forEach(control=>control.disabled=!visible);});
 const refresh=()=>{const method=start.value,inherit=method==='inherit';show('.tt-field-base',!inherit);show('.tt-field-track',!inherit);show('.tt-field-locos',!inherit);show('.tt-field-cut',method==='prepared_cut');show('.tt-field-cars',method==='manual'||method==='coupled_selected');show('.tt-field-predecessor',inherit);show('.tt-field-inheritance',inherit&&predecessor&&predecessor.value!=='');show('.tt-field-end',end.value!=='return_origin');if(cut){const option=cut.selectedOptions[0];form.querySelectorAll('.tt-cut-summary').forEach(el=>el.textContent=option?.dataset.summary||'');}};
 [start,end,predecessor,cut].filter(Boolean).forEach(el=>el.addEventListener('change',refresh));refresh();
});
