<?php
require_once dirname(__DIR__).'/lib.php';
require_once dirname(__DIR__).'/assignment_service.php';
function lifecycleExpect($condition,string$message):void{if(!$condition)throw new RuntimeException($message);}

lifecycleExpect(ttOperationsStatusLabel('in_progress','session')==='Active','Session in_progress must display as Active.');
lifecycleExpect(ttOperationsStatusLabel('in_progress','assignment')==='Active','Assignment in_progress must display as Active.');
lifecycleExpect(ttOperationsStatusLabel('draft','switch_list')==='Generated','Switch-list draft must display as Generated.');

[$ready]=ttSessionStartReadiness([['id'=>1,'status'=>'cancelled','latest_list_status'=>null],['id'=>2,'status'=>'ready','latest_list_status'=>'approved','predecessor_assignment_id'=>null]]);
lifecycleExpect($ready,'Cancelled legacy assignments must not block a valid current assignment from starting.');
[$ready]=ttSessionStartReadiness([['id'=>1,'status'=>'completed','latest_list_status'=>'completed'],['id'=>2,'status'=>'aborted','latest_list_status'=>'cancelled']]);
lifecycleExpect(!$ready,'Historical assignments alone must not satisfy Start Session.');
[$complete]=ttSessionCanComplete([['status'=>'completed'],['status'=>'aborted'],['status'=>'cancelled']]);lifecycleExpect($complete,'Completed, Aborted, and legacy Cancelled assignments may close a session.');
[$complete]=ttSessionCanComplete([['status'=>'in_progress']]);lifecycleExpect(!$complete,'An unfinished assignment must block explicit session completion.');

$root=dirname(__DIR__);$sessions=file_get_contents($root.'/sessions.php');$session=file_get_contents($root.'/session_edit.php');$delete=file_get_contents($root.'/assignment_delete.php');$edit=file_get_contents($root.'/assignment_edit.php');$generate=file_get_contents($root.'/generate.php');$progress=file_get_contents($root.'/progress.php');$completion=file_get_contents($root.'/completion.php');$workOrder=file_get_contents($root.'/work_order.php');$migration=file_get_contents(dirname($root).'/database/migrations/20260715_expand_operations_lifecycle_statuses.sql');
lifecycleExpect(strpos($sessions,"a.railroad_id=s.railroad_id AND a.status<>'cancelled'")!==false,'Session totals must scope assignments by railroad and exclude Cancelled legacy assignments.');
lifecycleExpect(strpos($delete,'DELETE FROM operation_assignments')!==false&&strpos($session,'Remove Assignment')!==false,'Draft assignment removal must use physical deletion and user-facing Remove wording.');
lifecycleExpect(strpos($edit,'ttSupersedeCurrentSwitchLists')!==false&&strpos($generate,'ttSupersedeCurrentSwitchLists')<strpos($generate,'INSERT INTO operation_switch_lists'),'Editing and regeneration must preserve Superseded revision history.');
lifecycleExpect(strpos($session,"SET status='in_progress',started_at=COALESCE(started_at,NOW()) WHERE session_id=? AND railroad_id=? AND status='ready'")!==false,'Start Session must promote Ready assignments to in_progress.');
lifecycleExpect(strpos($session,"action==='complete_session'")!==false&&strpos($completion,"UPDATE operating_sessions SET status='completed'")===false,'Only the explicit session action may complete the parent session.');
lifecycleExpect(strpos($session,"action==='cancel_session'")!==false&&strpos($session,"SET status='cancelled',cancelled_at=NOW()")!==false,'Active sessions need an explicit guarded Cancel action.');
lifecycleExpect(strpos($workOrder,"action==='abort'")!==false&&strpos($workOrder,"SET status='aborted'")!==false&&strpos($workOrder,'Assignment aborted.')!==false,'Active assignment stopping must be recorded and described as Aborted.');
lifecycleExpect(strpos($workOrder,"action==='cancel'")===false,'Draft work orders must not expose the legacy Cancel assignment action.');
lifecycleExpect(strpos($session,'This session is read-only history.')!==false&&strpos($session,"ttOperatingSessionIsHistory")!==false,'Completed and Cancelled sessions must render as read-only history.');
lifecycleExpect(strpos($progress,"session_status']!=='in_progress'")!==false&&strpos($completion,"session_status']!=='in_progress'")!==false,'Progress and closeout must reject non-Active sessions server-side.');
lifecycleExpect(strpos($session,"action==='delete_draft_session'")!==false&&strpos($session,'A session cannot be deleted after it starts.')!==false,'Draft session deletion must be server-side guarded.');
lifecycleExpect(strpos($migration,"'cancelled','aborted'")!==false&&strpos($migration,"'needs_review','superseded'")!==false,'Migration must append Aborted and Superseded while retaining existing values.');
lifecycleExpect(strpos($migration,"notes LIKE '%Superseded by Revision %'")!==false,'Legacy conversion must be limited to revisions with unambiguous invalidation notes.');
echo "lifecycle_test: OK\n";
