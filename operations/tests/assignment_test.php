<?php
require_once dirname(__DIR__).'/assignment_service.php';
function assignmentExpect($condition,string$message):void{if(!$condition)throw new RuntimeException($message);}

assignmentExpect(ttOperatingSessionIsEditable('draft')&&ttOperatingSessionIsEditable('ready'),'Draft and ready sessions should be editable.');
foreach(['in_progress','completed','cancelled']as$status)assignmentExpect(!ttOperatingSessionIsEditable($status),$status.' session must be frozen.');
assignmentExpect(ttAssignmentIsEditable(['status'=>'draft','session_status'=>'draft'],['draft']),'Safe draft assignment should be editable.');
foreach(['approved','in_progress','completed','needs_review']as$status)assignmentExpect(!ttAssignmentIsEditable(['status'=>'draft','session_status'=>'draft'],[$status]),$status.' retained revision must freeze editing and deletion.');
assignmentExpect(!ttAssignmentIsEditable(['status'=>'draft','session_status'=>'in_progress'],[]),'Frozen parent session must freeze assignment editing.');

$safe=['status'=>'draft','session_status'=>'draft','start_method'=>'manual','end_plan'=>'return_origin'];
assignmentExpect(ttAssignmentCanGenerate($safe,['draft','cancelled']),'Draft and cancelled revisions may be followed by a new draft.');
foreach(['approved','in_progress','completed','needs_review']as$status)assignmentExpect(!ttAssignmentCanGenerate($safe,[$status]),$status.' revision must block generation.');
assignmentExpect(!ttAssignmentCanGenerate(array_merge($safe,['session_status'=>'completed']),[]),'Frozen parent session must block generation.');
assignmentExpect(!ttAssignmentCanGenerate(array_merge($safe,['start_method'=>'inherit']),[]),'Legacy inheritance must block generation.');

[$ready]=ttSessionStartReadiness([['id'=>1,'status'=>'ready','latest_list_status'=>'draft','predecessor_assignment_id'=>null]]);assignmentExpect(!$ready,'An older approved revision plus a newer draft must block Start Session.');
[$ready]=ttSessionStartReadiness([['id'=>1,'status'=>'ready','latest_list_status'=>'approved','predecessor_assignment_id'=>null],['id'=>2,'status'=>'waiting','latest_list_status'=>'approved','predecessor_assignment_id'=>1]]);assignmentExpect($ready,'A waiting assignment with a ready approved predecessor should be valid.');
foreach([
 [['id'=>2,'status'=>'waiting','latest_list_status'=>'approved','predecessor_assignment_id'=>null]],
 [['id'=>1,'status'=>'draft','latest_list_status'=>'approved','predecessor_assignment_id'=>null],['id'=>2,'status'=>'waiting','latest_list_status'=>'approved','predecessor_assignment_id'=>1]],
 [['id'=>1,'status'=>'ready','latest_list_status'=>'draft','predecessor_assignment_id'=>null],['id'=>2,'status'=>'waiting','latest_list_status'=>'approved','predecessor_assignment_id'=>1]]
]as$invalid){[$ready]=ttSessionStartReadiness($invalid);assignmentExpect(!$ready,'Invalid waiting predecessor state must block Start Session.');}

assignmentExpect(ttAssignmentPatternOverrideValue(['operating_pattern'=>'yard_job','type_snapshot'=>'yard_job'])==='','Matching pattern must use the Job Title default.');
assignmentExpect(ttAssignmentPatternOverrideValue(['operating_pattern'=>'yard_job','type_snapshot'=>'local_turn'])==='yard_job','A genuine pattern override must be preserved.');
assignmentExpect(ttAssignmentEndPlans()===['return_origin','terminate_elsewhere'],'Only fully supported end plans may be offered.');

$service=file_get_contents(dirname(__DIR__).'/assignment_service.php');$edit=file_get_contents(dirname(__DIR__).'/assignment_edit.php');$delete=file_get_contents(dirname(__DIR__).'/assignment_delete.php');$session=file_get_contents(dirname(__DIR__).'/session_edit.php');$generate=file_get_contents(dirname(__DIR__).'/generate.php');$formJs=file_get_contents(dirname(__DIR__,2).'/assets/js/assignment-form.js');
assignmentExpect(strpos($service,'Inheritance is not yet supported.')!==false,'New inheritance submissions must be rejected.');
assignmentExpect(strpos($session,'value="inherit"')===false&&strpos($edit,"'inherit'=>'Inherit")===false,'Inheritance must not be offered in Create/Edit.');
foreach(['release_cars','release_locomotives','handoff_cars','handoff_train','continue_next','tie_down_locomotives']as$legacy)assignmentExpect(strpos($session,'value="'.$legacy.'"')===false,'Unsupported end plan '.$legacy.' must not be offered for creation.');
assignmentExpect(strpos($service,'current_industry_id\']!==(int)$data[\'base_id\'')!==false,'Starting cars must be scoped to the Operating Base.');
assignmentExpect(strpos($service,'start_method\']===\'coupled_selected\'')!==false&&strpos($service,'current_track\']!==$data[\'starting_track\'')!==false,'Coupled cars must match Starting Track.');
assignmentExpect(strpos($session,'data-location-id')!==false&&strpos($formJs,'filterCars')!==false,'Starting-car choices must be visibly filtered by Operating Base.');
assignmentExpect(strpos($generate,'ttAssignmentCanGenerate')!==false&&strpos($generate,'http_response_code(409)')!==false,'GET and POST generation must share a server-side freeze boundary.');
assignmentExpect(strpos($delete,'session_status')!==false&&strpos($edit,'session_status')!==false,'Edit and delete must enforce parent session status.');
assignmentExpect(strpos($session,'ORDER BY sl.revision_number DESC LIMIT 1')!==false,'Start Session must inspect the latest switch-list revision.');
assignmentExpect(strpos($service,"UPDATE equipment SET")===false&&strpos($edit,"UPDATE equipment SET")===false&&strpos($delete,"UPDATE equipment SET")===false,'Assignment changes must remain location- and load-neutral.');
echo "assignment_test: OK\n";
