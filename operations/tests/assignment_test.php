<?php
require_once dirname(__DIR__).'/assignment_service.php';
function assignmentExpect($condition,string$message):void{if(!$condition)throw new RuntimeException($message);}

assignmentExpect(ttOperatingSessionIsEditable('draft')&&ttOperatingSessionIsEditable('ready'),'Draft and ready sessions should be editable.');
foreach(['in_progress','completed','cancelled']as$status)assignmentExpect(!ttOperatingSessionIsEditable($status),$status.' session must be frozen.');
assignmentExpect(ttAssignmentIsEditable(['status'=>'draft','session_status'=>'draft'],['draft']),'Safe draft assignment should be editable.');
assignmentExpect(ttAssignmentIsEditable(['status'=>'ready','session_status'=>'draft'],['approved']),'An approved Draft-session assignment may be edited and regenerated.');
foreach(['in_progress','completed','needs_review']as$status)assignmentExpect(!ttAssignmentIsEditable(['status'=>'draft','session_status'=>'draft'],[$status]),$status.' retained revision must freeze editing and deletion.');
assignmentExpect(!ttAssignmentIsEditable(['status'=>'draft','session_status'=>'in_progress'],[]),'Frozen parent session must freeze assignment editing.');

$safe=['status'=>'draft','session_status'=>'draft','start_method'=>'manual','end_plan'=>'return_origin'];
assignmentExpect(ttAssignmentCanGenerate($safe,['draft','approved','cancelled','superseded']),'Draft-session current and historical revisions may be followed by a new draft.');
foreach(['in_progress','completed','needs_review']as$status)assignmentExpect(!ttAssignmentCanGenerate($safe,[$status]),$status.' revision must block generation.');
assignmentExpect(!ttAssignmentCanGenerate(array_merge($safe,['session_status'=>'completed']),[]),'Frozen parent session must block generation.');
assignmentExpect(!ttAssignmentCanGenerate(array_merge($safe,['start_method'=>'inherit']),[]),'Legacy inheritance must block generation.');

$cancelledOnly=ttSwitchListRevisionState([['id'=>2,'revision_number'=>2,'status'=>'cancelled'],['id'=>1,'revision_number'=>1,'status'=>'cancelled']]);
assignmentExpect($cancelledOnly['current']===null&&count($cancelledOnly['rows'])===2,'Cancelled revisions must remain history without becoming current work.');
$supersededOnly=ttSwitchListRevisionState([['id'=>2,'revision_number'=>2,'status'=>'superseded'],['id'=>1,'revision_number'=>1,'status'=>'superseded']]);
assignmentExpect($supersededOnly['current']===null,'Superseded revisions must remain history without becoming current work.');
$newerDraft=ttSwitchListRevisionState([['id'=>2,'revision_number'=>2,'status'=>'draft'],['id'=>1,'revision_number'=>1,'status'=>'draft']]);
assignmentExpect(!ttSwitchListIsApprovable(['id'=>1,'status'=>'draft'],$safe,$newerDraft),'An older draft cannot be approved after a newer revision exists.');
assignmentExpect(ttSwitchListIsApprovable(['id'=>2,'status'=>'draft'],$safe,$newerDraft),'The latest safe draft remains approvable.');
$newerCancelled=ttSwitchListRevisionState([['id'=>3,'revision_number'=>3,'status'=>'cancelled'],['id'=>2,'revision_number'=>2,'status'=>'draft']]);
assignmentExpect((int)$newerCancelled['current']['id']===2&&!ttSwitchListIsApprovable(['id'=>2,'status'=>'draft'],$safe,$newerCancelled),'Current non-cancelled work and latest approvable revision must remain distinct.');

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

$service=file_get_contents(dirname(__DIR__).'/assignment_service.php');$edit=file_get_contents(dirname(__DIR__).'/assignment_edit.php');$delete=file_get_contents(dirname(__DIR__).'/assignment_delete.php');$session=file_get_contents(dirname(__DIR__).'/session_edit.php');$generate=file_get_contents(dirname(__DIR__).'/generate.php');$workOrder=file_get_contents(dirname(__DIR__).'/work_order.php');$formJs=file_get_contents(dirname(__DIR__,2).'/assets/js/assignment-form.js');
assignmentExpect(strpos($service,'Inheritance is not yet supported.')!==false,'New inheritance submissions must be rejected.');
assignmentExpect(strpos($session,'value="inherit"')===false&&strpos($edit,"'inherit'=>'Inherit")===false,'Inheritance must not be offered in Create/Edit.');
foreach(['release_cars','release_locomotives','handoff_cars','handoff_train','continue_next','tie_down_locomotives']as$legacy)assignmentExpect(strpos($session,'value="'.$legacy.'"')===false,'Unsupported end plan '.$legacy.' must not be offered for creation.');
assignmentExpect(strpos($service,'current_industry_id\']!==(int)$data[\'base_id\'')!==false,'Starting cars must be scoped to the Operating Base.');
assignmentExpect(strpos($service,'start_method\']===\'coupled_selected\'')!==false&&strpos($service,'current_track\']!==$data[\'starting_track\'')!==false,'Coupled cars must match Starting Track.');
assignmentExpect(strpos($session,'data-location-id')!==false&&strpos($formJs,'filterCars')!==false,'Starting-car choices must be visibly filtered by Operating Base.');
assignmentExpect(strpos($generate,'ttAssignmentCanGenerate')!==false&&strpos($generate,'http_response_code(409)')!==false,'GET and POST generation must share a server-side freeze boundary.');
assignmentExpect(strpos($delete,'session_status')!==false&&strpos($edit,'session_status')!==false,'Edit and delete must enforce parent session status.');
assignmentExpect(strpos($session,'ORDER BY sl.revision_number DESC LIMIT 1')!==false,'Start Session must inspect the latest switch-list revision.');
assignmentExpect(strpos($edit,'Invalidated because the assignment settings were edited.')!==false&&strpos($edit,'ttSupersedeCurrentSwitchLists')!==false,'Editing must supersede invalidated generated revisions inside its transaction.');
assignmentExpect(strpos($generate,'Superseded by Revision ')!==false&&strpos($generate,'ttSupersedeCurrentSwitchLists')<strpos($generate,'INSERT INTO operation_switch_lists'),'Regeneration must supersede the previous current revision before inserting the new revision.');
assignmentExpect(substr_count($generate,'ttAssignmentCanGenerate')>=2&&strpos($generate,'ttSwitchListRevisionSummary($pdo,$assignmentId,$railroadId,true)')!==false,'POST generation must recheck the locked revision boundary transactionally.');
assignmentExpect(strpos($workOrder,'ttSwitchListIsApprovable')!==false&&strpos($workOrder,'stale, superseded')!==false,'Approval must reject stale draft URLs.');
assignmentExpect(strpos($session,"NOT IN('cancelled','superseded')")!==false&&strpos($generate,"NOT IN('cancelled','superseded')")!==false,'Cancelled and Superseded history must be excluded from current-list queries.');
assignmentExpect(strpos($service,'Choose an End Location when terminating elsewhere.')!==false,'Terminate elsewhere must require an End Location.');
assignmentExpect(strpos($service,'$endPlan===\'return_origin\'?0')!==false&&strpos($service,'$endPlan===\'return_origin\'?\'\':')!==false,'Return to origin must discard End Location and End Track.');
assignmentExpect(strpos($service,"UPDATE operation_switch_lists SET status='superseded'")!==false&&strpos($service,'DELETE FROM operation_switch_lists')===false,'Superseded revisions must remain retained history.');
assignmentExpect(strpos($service,"UPDATE equipment SET")===false&&strpos($edit,"UPDATE equipment SET")===false&&strpos($delete,"UPDATE equipment SET")===false,'Assignment changes must remain location- and load-neutral.');
echo "assignment_test: OK\n";
