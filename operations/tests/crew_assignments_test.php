<?php
require_once dirname(__DIR__).'/lib.php';
require_once dirname(__DIR__).'/crew_service.php';
require_once dirname(__DIR__).'/assignment_service.php';

function crewExpect($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}

$display=ttOperationsCrewDisplay([
    'engineer_name'=>'Alex Morgan',
    'conductor_name'=>'Jordan Lee',
    'brakeman_names'=>'Casey Kim, Riley Diaz',
    'crew_name'=>'legacy value',
]);
crewExpect(strpos($display,'Engineer: Alex Morgan')!==false&&strpos($display,'Conductor / Foreman: Jordan Lee')!==false&&strpos($display,'Brakeman / Switchman: Casey Kim, Riley Diaz')!==false,'Role-specific crew names must be shown together.');
crewExpect(ttOperationsCrewDisplay(['crew_name'=>'Legacy Crew'])==='Legacy Crew','Older assignments must retain their generic crew label.');
crewExpect(ttOperationsCrewDisplay(['assignment_number'=>'OPS-001-A'])==='Crew not assigned','A generated train/job designation must not be mistaken for a crew identity.');
crewExpect(strlen(ttOperationsCrewSummary(str_repeat('E',120),str_repeat('C',120),str_repeat('B',255)))===120,'The legacy crew summary must fit the existing compatibility column.');
crewExpect(ttOperationsUnitDisplay(['unit_identifier'=>'YARD-4821','assignment_number'=>'OPS-001-A'])==='YARD-4821','The generated unit ID must take precedence over the internal assignment number.');
crewExpect(ttOperationsUnitDisplay(['assignment_number'=>'OPS-001-A'])==='OPS-001-A','Existing assignments must retain a usable identity before backfill.');
crewExpect(ttAssignmentUnitPrefix('West Yard Switcher')==='WEST'&&ttAssignmentUnitPrefix('***')==='JOB','Unit IDs must use a concise railroad-style job prefix with a safe fallback.');

$root=dirname(__DIR__);$project=dirname($root);
$page=file_get_contents($root.'/session_edit.php');
$service=file_get_contents($root.'/crew_service.php');
$migration=file_get_contents($project.'/database/migrations/20260719_add_session_crew_assignments.sql');
$assignmentService=file_get_contents($root.'/assignment_service.php');
$yardmasterService=file_get_contents($root.'/yardmaster_service.php');
$history=file_get_contents($root.'/history_view.php');
$yardmasterPage=file_get_contents($root.'/yardmaster.php');
$views=['dispatcher.php','switch_lists.php','work_order.php','print_order.php'];

crewExpect(strpos($migration,'yardmaster_name')!==false&&strpos($migration,'engineer_name')!==false&&strpos($migration,'conductor_name')!==false&&strpos($migration,'brakeman_names')!==false&&strpos($migration,'unit_identifier')!==false,'The migration must add a generated unit identity plus typed session and crew role fields.');
crewExpect(strpos($migration,'user_id')===false&&strpos($migration,'email')===false,'Crew identity storage must not reference TrainTote accounts or email addresses.');
crewExpect(strpos($page,'Crew Assignments')!==false&&strpos($page,'save_crew_assignments')!==false&&strpos($page,'ttOperationsRequireCsrf()')!==false,'Session Builder must provide a CSRF-protected crew assignment area.');
crewExpect(strpos($page,'ttGenerateAssignmentUnitId')!==false&&strpos($page,"'Unit '.ttOperationsUnitDisplay")!==false,'Each new assignment must receive and display a generated unit ID separate from crew names.');
crewExpect(strpos($assignmentService,'random_int(1000,9999)')!==false&&strpos($assignmentService,'railroad_id=? AND session_id=? AND unit_identifier=?')!==false,'Generated unit IDs must use a random suffix and be collision checked inside the railroad session.');
foreach(['engineer_name','conductor_name','brakeman_names'] as$field)crewExpect(strpos($page,$field)!==false&&strpos($assignmentService,$field)!==false,'Crew field '.$field.' must be accepted by assignment creation and Session Builder.');
crewExpect(strpos($service,'ttOperationsRequireRailroadOwner')!==false&&strpos($service,'id=? AND session_id=? AND railroad_id=?')!==false&&strpos($service,'FOR UPDATE')!==false,'Crew writes must be permission checked, session aware, locked, and railroad scoped.');
crewExpect(strpos($service,"status IN('draft','ready','in_progress')")!==false,'Crew names may change only on current sessions.');
crewExpect(strpos($service,'JOIN users')===false&&strpos($service,'email')===false&&strpos($yardmasterService,'operation_session_roles')===false,'Crew and Yardmaster identity must not use account lookup.');
crewExpect(strpos($service,'UPDATE equipment SET')===false&&strpos($migration,'current_industry_id')===false,'Crew assignment must not affect physical equipment locations.');
crewExpect(strpos($history,"session['yardmaster_name']")!==false&&strpos($history,'ttOperationsCrewDisplay')!==false,'Typed Yardmaster and crew names must remain visible in Session History.');
crewExpect(strpos($yardmasterPage,"session['yardmaster_name']")!==false,'The Yardmaster page must identify the typed session Yardmaster.');
foreach($views as$view)crewExpect(strpos(file_get_contents($root.'/'.$view),'ttOperationsCrewDisplay')!==false,'The '.$view.' view must render role-specific crew assignments.');

echo "crew_assignments_test: OK\n";
