<?php
require_once dirname(__DIR__).'/dispatcher_service.php';
function dispatcherExpect($condition,string$message):void{if(!$condition)throw new RuntimeException($message);}
dispatcherExpect(array_keys(ttDispatcherStatuses())===['not_started','working','delayed','complete'],'Dispatcher must expose exactly the approved operating statuses.');
dispatcherExpect(ttDispatcherStatus(['assignment_status'=>'completed','dispatcher_status'=>'delayed'])==='complete','Lifecycle completion must override dispatcher state.');
dispatcherExpect(ttDispatcherStatus(['assignment_status'=>'in_progress','dispatcher_status'=>'working'])==='working','Dispatcher state must remain independent for unfinished work.');
dispatcherExpect(ttDispatcherEffectiveEnabled(['operations_dispatcher_enabled'=>1],['dispatcher_enabled'=>null]),'A session must inherit the railroad setting.');
dispatcherExpect(!ttDispatcherEffectiveEnabled(['operations_dispatcher_enabled'=>1],['dispatcher_enabled'=>0]),'A session-level disable must override the railroad setting.');
$root=dirname(__DIR__);$service=file_get_contents($root.'/dispatcher_service.php');$feed=file_get_contents($root.'/dispatcher_feed.php');$page=file_get_contents($root.'/dispatcher.php');$workOrder=file_get_contents($root.'/work_order.php');$sidebar=file_get_contents(dirname($root).'/assets/components/sidebar.php');$migration=file_get_contents(dirname($root).'/database/migrations/20260718_add_operations_dispatcher.sql');$js=file_get_contents(dirname($root).'/assets/js/dispatcher.js');
dispatcherExpect(strpos($service,"orr.role='dispatcher'")!==false&&strpos($service,'WHERE a.session_id=? AND a.railroad_id=?')!==false,'Dispatcher access and assignment reads must be railroad scoped.');
dispatcherExpect(strpos($feed,'ttOperationsRequireCsrf()')!==false&&strpos($service,'FOR UPDATE')!==false,'Dispatcher writes must require CSRF and lock scoped session state.');
dispatcherExpect(strpos($service,"session_status'] !== 'in_progress'")!==false&&strpos($service,"assignment_status'] === 'completed'")!==false,'Closed sessions and completed assignments must reject dispatcher writes.');
dispatcherExpect(strpos($service,'UPDATE operation_switch_list_moves')===false&&strpos($service,'UPDATE operation_switch_lists')===false&&strpos($service,'UPDATE operating_sessions SET status')===false,'Dispatcher updates must not duplicate lifecycle or movement logic.');
dispatcherExpect(strpos($migration,"role ENUM('dispatcher')")!==false&&strpos($migration,'PRIMARY KEY (railroad_id, user_id, role)')!==false,'Dispatcher roles must be explicit and railroad scoped.');
dispatcherExpect(strpos($page,'Dispatcher-only Note')!==false&&strpos($page,'Crew Message')!==false&&strpos($workOrder,'dispatcher_crew_message')!==false,'Private notes and crew-facing messages must remain distinct.');
dispatcherExpect(strpos($js,'document.hidden')!==false&&strpos($js,'15000')!==false&&strpos($js,'stopped=!data.polling')!==false,'Polling must be lightweight, visibility aware, and stop on session close.');
dispatcherExpect(strpos($sidebar,'ttDispatcherNavEnabled')!==false,'Dispatcher navigation must be conditional.');
echo "dispatcher_test: OK\n";
