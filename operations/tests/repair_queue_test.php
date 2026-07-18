<?php
require_once dirname(__DIR__).'/repair_service.php';

function repairExpect($condition,string$message):void{if(!$condition)throw new RuntimeException($message);}

repairExpect(array_keys(ttRepairStatuses())===['awaiting_repair','in_repair','ready_for_service','closed'],'Repair Queue V1 must expose exactly the four approved statuses.');
repairExpect(ttRepairStatusLabel('closed')==='Closed / Returned to Service','Closed status must use the return-to-service label.');

$root=dirname(__DIR__);
$completion=file_get_contents($root.'/completion.php');
$service=file_get_contents($root.'/repair_service.php');
$list=file_get_contents($root.'/repairs.php');
$detail=file_get_contents($root.'/repair.php');
$sidebar=file_get_contents(dirname($root).'/assets/components/sidebar.php');
$migration=file_get_contents(dirname($root).'/database/migrations/20260718_add_operations_repair_queue.sql');

repairExpect(strpos($completion,"\$repairQueueEnabled&&\$result['reason']==='bad_order'")!==false,'A completed Bad Order exception must create or reuse a repair queue item only while the module is enabled.');
repairExpect(strpos($migration,'uq_operation_repair_open_equipment')!==false&&strpos($migration,'open_equipment_id INT GENERATED ALWAYS')!==false,'The database must enforce one open repair per railroad and equipment item.');
repairExpect(strpos($service,"status<>'closed' FOR UPDATE")!==false&&strpos($service,"event_type,new_status,note,source_move_id")!==false,'Duplicate Bad Orders must lock and append to the existing open repair.');
repairExpect(strpos($list,'WHERE r.railroad_id=?')!==false&&strpos($detail,'WHERE r.id=? AND r.railroad_id=?')!==false&&strpos($service,'WHERE id=? AND railroad_id=? FOR UPDATE')!==false,'List, detail, and update access must be railroad scoped.');
repairExpect(strpos($service,'operation_repair_history')!==false&&strpos($service,'$eventType = $newStatus === $repair[\'status\'] ? \'note\' : \'status_change\';')!==false,'Status progression and repair notes must append dated history.');
repairExpect(strpos($service,"\$newStatus === 'closed'")!==false&&strpos($service,'UPDATE equipment SET active=1')!==false,'Closing a repair must restore eligible equipment to service.');
repairExpect(strpos($service,'current_industry_id')===false&&strpos($service,'current_track')===false,'Repair workflow must never change equipment location or track.');
repairExpect(strpos($service,'UPDATE operation_switch_list_moves')===false&&strpos($service,'UPDATE operation_switch_lists')===false&&strpos($service,'UPDATE operating_sessions')===false,'Repair updates must leave historical Operations records unchanged.');
repairExpect(strpos($list,'$view = ($_GET[\'view\'] ?? \'\') === \'closed\'')!==false&&strpos($list,'No equipment is awaiting repair')!==false&&strpos($list,'No closed repairs yet')!==false,'Open, closed-history, and empty views must be present.');
repairExpect(strpos($detail,'ttOperationsRequireCsrf()')!==false&&strpos($detail,'delete')===false,'Repair changes must require CSRF protection and V1 must expose no delete control.');
repairExpect(strpos($migration,'service_state_applied')!==false&&strpos($migration,'newer.id IS NULL')!==false,'Historical Bad Orders must be safely surfaced without changing current service state.');
repairExpect(strpos($sidebar,'Repair Queue')!==false&&strpos($sidebar,"'repairs'")!==false,'Repair Queue must be present in Operations navigation.');

echo "repair_queue_test: OK\n";
