<?php

function ttCompleteWorkOrderLoadNeutral(PDO $pdo, array $list, int $railroadId, array $post): void
{
    $reasons=['track_blocked','car_inaccessible','no_capacity','bad_order','wrong_car','customer_not_ready','locomotive_issue','crew_issue','order_changed','other'];
    $pdo->beginTransaction();
    $lock=$pdo->prepare("SELECT status FROM operation_switch_lists WHERE id=? AND railroad_id=? FOR UPDATE");
    $lock->execute([(int)$list['id'],$railroadId]);
    if(!in_array($lock->fetchColumn(),['approved','in_progress','needs_review'],true)) throw new RuntimeException('This work order cannot be completed.');
    $stmt=$pdo->prepare('SELECT * FROM operation_switch_list_moves WHERE switch_list_id=? AND railroad_id=? AND equipment_id IS NOT NULL ORDER BY sequence_number FOR UPDATE');
    $stmt->execute([(int)$list['id'],$railroadId]);
    $moves=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $moved=0;$notMoved=0;
    $updateMove=$pdo->prepare('UPDATE operation_switch_list_moves SET actual_outcome=?,actual_industry_id=?,actual_track=?,actual_load_status=?,exception_reason_code=?,exception_notes=?,progress_complete=1,progress_updated_at=NOW(),completed_at=NOW() WHERE id=?');
    $lockEquipment=$pdo->prepare('SELECT current_industry_id,current_track,load_status FROM equipment WHERE id=? AND railroad_id=? FOR UPDATE');
    $updateEquipment=$pdo->prepare('UPDATE equipment SET current_industry_id=?,current_track=? WHERE id=? AND railroad_id=?');
    foreach($moves as $move){
        $key=(string)$move['id'];$outcome=(string)($post['outcome'][$key]??'');
        if(!in_array($outcome,['moved','not_moved'],true)) throw new RuntimeException('Every car requires a Moved or Not Moved result.');
        $lockEquipment->execute([(int)$move['equipment_id'],$railroadId]);$equipment=$lockEquipment->fetch(PDO::FETCH_ASSOC);
        if(!$equipment) throw new RuntimeException('A planned car no longer belongs to this railroad.');
        $reason=null;$notes=substr(trim((string)($post['exception_notes'][$key]??'')),0,255);
        $actualIndustry=(int)($post['actual_industry_id'][$key]??$move['destination_industry_id']);
        $actualTrack=substr(trim((string)($post['actual_track'][$key]??$move['destination_track'])),0,120);
        $actualLoad=(string)$equipment['load_status'];
        if($outcome==='not_moved'){
            $reason=(string)($post['reason'][$key]??'');
            if(!in_array($reason,$reasons,true)) throw new RuntimeException('Every Not Moved car needs a valid reason.');
            if($reason==='other'&&$notes==='') throw new RuntimeException('Other exceptions require notes.');
            $actualIndustry=(int)$equipment['current_industry_id'];$actualTrack=(string)$equipment['current_track'];$notMoved++;
        }else{
            if((int)$equipment['current_industry_id']!==(int)$move['origin_industry_id']||trim((string)$equipment['current_track'])!==trim((string)$move['origin_track'])) throw new RuntimeException('Stale physical state detected for '.$move['reporting_marks_snapshot'].' '.$move['road_number_snapshot'].'. Its location changed after approval; no results were applied.');
            $valid=$pdo->prepare('SELECT id FROM industries WHERE id=? AND railroad_id=?');$valid->execute([$actualIndustry,$railroadId]);
            if(!$valid->fetchColumn()) throw new RuntimeException('Choose a valid actual destination.');
            $updateEquipment->execute([$actualIndustry,$actualTrack,(int)$move['equipment_id'],$railroadId]);$moved++;
        }
        $updateMove->execute([$outcome,$actualIndustry,$actualTrack,$actualLoad,$reason,$notes?:null,(int)$move['id']]);
    }
    $endIndustry=(int)$list['end_industry_id'];
    if($endIndustry<=0&&$list['end_plan']==='return_origin') $endIndustry=(int)$list['operating_base_industry_id'];
    if($endIndustry>0) $pdo->prepare('UPDATE equipment e JOIN operation_assignment_locomotives al ON al.equipment_id=e.id SET e.current_industry_id=?,e.current_track=? WHERE al.assignment_id=? AND e.railroad_id=?')->execute([$endIndustry,(string)($list['end_track']?:$list['starting_track']),(int)$list['assignment_id'],$railroadId]);
    $pdo->prepare("UPDATE operation_switch_lists SET status='completed',completed_at=NOW(),moved_count=?,not_moved_count=? WHERE id=?")->execute([$moved,$notMoved,(int)$list['id']]);
    $pdo->prepare("UPDATE operation_assignments SET status='completed',completed_at=NOW() WHERE id=?")->execute([(int)$list['assignment_id']]);
    $pdo->prepare("UPDATE prepared_cuts SET status='released' WHERE id=(SELECT prepared_cut_id FROM operation_assignments WHERE id=?) AND status IN('assigned','in_use')")->execute([(int)$list['assignment_id']]);
    $dependents=$pdo->prepare("SELECT id FROM operation_assignments WHERE predecessor_assignment_id=? AND railroad_id=? AND status='waiting' FOR UPDATE");$dependents->execute([(int)$list['assignment_id'],$railroadId]);
    foreach($dependents->fetchAll(PDO::FETCH_ASSOC) as $dependent) $pdo->prepare('UPDATE operation_assignments SET status=? WHERE id=?')->execute([$notMoved===0?'ready':'needs_review',(int)$dependent['id']]);
    $remaining=$pdo->prepare("SELECT COUNT(*) FROM operation_assignments WHERE session_id=? AND status NOT IN('completed','cancelled')");$remaining->execute([(int)$list['session_id']]);
    if((int)$remaining->fetchColumn()===0) $pdo->prepare("UPDATE operating_sessions SET status='completed',completed_at=NOW() WHERE id=? AND railroad_id=?")->execute([(int)$list['session_id'],$railroadId]);
    $pdo->commit();
}
