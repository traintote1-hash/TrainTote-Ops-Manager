<?php
require_once dirname(__DIR__).'/yardmaster_service.php';
function yardExpect($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}

$space=ttYardmasterForecast(6,3,10);
yardExpect($space===['forecast'=>9,'available'=>4,'over_capacity'=>false],'Yard capacity must distinguish current availability from planned forecast.');
$over=ttYardmasterForecast(9,2,10);
yardExpect($over['over_capacity']&&$over['forecast']===11,'Incoming classification plans must surface over-capacity warnings.');
$unlimited=ttYardmasterForecast(12,4,0);
yardExpect($unlimited['available']===null&&!$unlimited['over_capacity'],'Unspecified capacity must not create a false warning.');

$root=dirname(__DIR__);$project=dirname($root);
$service=file_get_contents($root.'/yardmaster_service.php');
$page=file_get_contents($root.'/yardmaster.php');
$lib=file_get_contents($root.'/lib.php');
$sidebar=file_get_contents($project.'/assets/components/sidebar.php');
$dashboard=file_get_contents($root.'/dashboard.php');
$session=file_get_contents($root.'/session_edit.php');
$history=file_get_contents($root.'/history_view.php');
$css=file_get_contents($project.'/assets/css/operations.css');
$migration=file_get_contents($project.'/database/migrations/20260718_add_operations_yardmaster.sql');
$crewMigration=file_get_contents($project.'/database/migrations/20260719_add_session_crew_assignments.sql');
$crewService=file_get_contents($root.'/crew_service.php');

yardExpect(strpos($lib,"'yardmaster' => ['label' => 'Yardmaster', 'available' => true")!==false,'Yardmaster must be an available unified Operations module.');
yardExpect(strpos($page,"ttOperationsRequireModule(\$pdo, \$railroadId, 'yardmaster')")!==false&&strpos($page,'ttOperationsRequireCsrf()')!==false,'Yardmaster reads/writes must enforce the module and CSRF.');
yardExpect(strpos($service,'ttYardmasterRequireAccess')!==false&&strpos($service,'session_id=? AND railroad_id=?')!==false,'Yard writes must be session-aware, permission checked, and railroad scoped.');
yardExpect(strpos($service,"fetchColumn() !== 'in_progress'")!==false,'Classification writes must be limited to Active sessions.');
yardExpect(strpos($service,'UPDATE equipment SET')===false,'Yard planning must never update persistent equipment locations.');
yardExpect(strpos($migration,'operation_yard_assignments')!==false&&strpos($migration,'uq_yard_assignment_session_equipment')!==false,'A separate session plan with duplicate prevention is required.');
yardExpect(strpos($crewMigration,'yardmaster_name')!==false&&strpos($crewMigration,'user_id')===false,'The session-level Yardmaster identity must be a typed name, not a TrainTote account.');
yardExpect(strpos($session,'save_yardmaster_name')!==false&&strpos($session,'name="yardmaster_name"')!==false,'Session Builder must support assigning or clearing a typed Yardmaster name separately from assignment crews.');
yardExpect(strpos($crewService,'UPDATE operating_sessions SET yardmaster_name')!==false&&strpos($crewService,'operation_yard_history')!==false,'Yardmaster name changes must be session scoped and retained in history.');
yardExpect(strpos($service,'operation_session_roles')===false&&strpos($service,'yardmaster_email')===false&&strpos($service,'JOIN users')===false,'Yardmaster access must not resolve the acting name through a TrainTote account.');
yardExpect(strpos($sidebar,'ttYardmasterNavEnabled')!==false&&strpos($sidebar,'/operations/yardmaster.php')!==false&&strpos($dashboard,"modules['yardmaster']")!==false,'Yardmaster navigation and dashboard UI must be conditional.');
yardExpect(strpos($page,'Inbound Cars Needing Classification')!==false&&strpos($page,'Outbound Cars by Job / Switch List')!==false,'The page must expose inbound classification and grouped outbound work.');
foreach(['Over capacity','Duplicate plan','Inactive','Repair Queue'] as$warning)yardExpect(strpos($page,$warning)!==false,'Yardmaster must render the '.$warning.' warning.');
yardExpect(strpos($service,"r.railroad_id=e.railroad_id")!==false&&strpos($service,"r.status<>'closed'")!==false,'Repair warnings must be railroad scoped and limited to open repairs.');
yardExpect(strpos($migration,'operation_yard_history')!==false&&strpos($history,'Yardmaster Activity')!==false&&strpos($history,'yh.railroad_id=?')!==false,'Meaningful Yardmaster events must appear in railroad-scoped Session History.');
yardExpect(strpos($page,'setInterval')===false&&strpos($page,'fetch(')===false,'Yardmaster V1 must not create polling or background work.');
yardExpect(strpos($css,'.tt-yard-track-grid')!==false&&strpos($css,'@media(max-width:576px){.tt-yard-track-grid')!==false,'The yard overview must follow current responsive Operations patterns.');
echo "yardmaster_test: OK\n";
