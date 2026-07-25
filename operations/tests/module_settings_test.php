<?php
require_once dirname(__DIR__).'/lib.php';

function moduleExpect($condition,string$message):void{if(!$condition)throw new RuntimeException($message);}

$definitions=ttOperationsModuleDefinitions();
moduleExpect(array_keys($definitions)===['fast_clock','dispatcher','repair_queue','crew_messaging','advanced_roles','track_warrants','yardmaster','interchange_management','ai_job_suggestions'],'Settings must expose the approved Operations modules.');
foreach(['track_warrants','interchange_management','ai_job_suggestions'] as$key)moduleExpect(!$definitions[$key]['available'],'Placeholder modules must remain unavailable.');
moduleExpect($definitions['yardmaster']['available'],'Yardmaster V1 must be available through unified module settings.');

$root=dirname(__DIR__);$project=dirname($root);$migration=file_get_contents($project.'/database/migrations/20260718_add_operations_module_settings.sql');$settings=file_get_contents($root.'/settings.php');$sidebar=file_get_contents($project.'/assets/components/sidebar.php');$dashboard=file_get_contents($root.'/dashboard.php');$widget=file_get_contents($root.'/fast_clock_widget.php');$clockEndpoint=file_get_contents($root.'/fast_clock.php');$dispatcher=file_get_contents($root.'/dispatcher.php');$dispatcherFeed=file_get_contents($root.'/dispatcher_feed.php');$workOrder=file_get_contents($root.'/work_order.php');$completion=file_get_contents($root.'/completion.php');
$jobsLinkPosition=strpos($sidebar,'href="/jobs/list.php"');$jobsPreviousSource=$jobsLinkPosition===false?'':substr($sidebar,0,$jobsLinkPosition);$jobsPreviousConditional=strrpos($jobsPreviousSource,'<?php if');$jobsPreviousConditionalEnd=strrpos($jobsPreviousSource,'<?php endif; ?>');
moduleExpect(strpos($migration,'DEFAULT 0')!==false&&strpos($migration,'INSERT IGNORE')!==false,'New module settings must default off while existing usage is backfilled.');
moduleExpect(strpos($migration,"'fast_clock', 1")!==false&&strpos($migration,"'dispatcher', 1")!==false&&strpos($migration,"'repair_queue', 1")!==false,'Migration must preserve existing Fast Clock, Dispatcher, and Repair Queue use.');
moduleExpect(strpos($settings,'ttOperationsRequireRailroadOwner')!==false&&strpos($settings,'ttOperationsRequireCsrf')!==false,'Settings mutations must be owner-only and CSRF protected.');
moduleExpect(strpos($settings,"!\$definition['available']")!==false&&strpos($settings,'disabled')!==false,'Coming-later modules must not be enableable.');
moduleExpect(strpos($sidebar,"operationModules['repair_queue']")!==false&&strpos($sidebar,'ttDispatcherNavEnabled')!==false,'Advanced navigation must be conditional.');
moduleExpect($jobsLinkPosition!==false&&preg_match('/href="\/jobs\/list\.php">\s*Jobs\s*<\/a>/', $sidebar)===1&&$jobsPreviousConditionalEnd!==false&&($jobsPreviousConditional===false||$jobsPreviousConditionalEnd>$jobsPreviousConditional),'Jobs must always be visible in Operations navigation.');
moduleExpect(strpos($dashboard,'Core workflow')!==false&&strpos($dashboard,'Operations Tools')!==false&&strpos($dashboard,"modules['repair_queue']")!==false,'Dashboard must separate core work from enabled tools.');
moduleExpect(strpos($widget,"ttOperationsModuleEnabled(\$pdo, (int)\$railroadId, 'fast_clock')")!==false&&strpos($clockEndpoint,"ttOperationsRequireModule(\$pdo, \$railroadId, 'fast_clock')")!==false,'Disabled Fast Clock pages must render no polling script and reject requests.');
moduleExpect(strpos($dispatcher,"ttOperationsRequireModule(\$pdo,\$railroadId,'dispatcher')")!==false&&strpos($dispatcherFeed,"ttOperationsRequireModule(\$pdo,\$railroadId,'dispatcher')")!==false,'Disabled Dispatcher pages and polling feeds must reject requests.');
moduleExpect(strpos($workOrder,"modules['crew_messaging']")!==false&&strpos($workOrder,'$exceptionReasons')!==false&&strpos($completion,'$repairQueueEnabled')!==false,'Work-order messaging and Bad Order behavior must follow module settings.');
moduleExpect(strpos($settings,'UPDATE operating_sessions')===false&&strpos($settings,'DELETE FROM operation_')===false,'Module changes must not overwrite session configuration or delete history.');

echo "module_settings_test: OK\n";
