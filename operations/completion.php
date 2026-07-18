<?php

require_once __DIR__.'/repair_service.php';

function ttPrepareWorkOrderResults(array $moves,array $equipmentById,array $post,callable $industryExists):array
{
    $reasons=['track_blocked','car_inaccessible','no_capacity','bad_order','wrong_car','customer_not_ready','locomotive_issue','crew_issue','order_changed','other'];
    $results=[];$movedAsPlanned=0;$movedDifferent=0;$notMoved=0;
    foreach($moves as$move){
        $equipmentId=(int)$move['equipment_id'];$key=(string)$move['id'];$equipment=$equipmentById[$equipmentId]??null;$car=trim((string)$move['reporting_marks_snapshot'].' '.(string)$move['road_number_snapshot']);
        if(!$equipment)throw new RuntimeException('Planned car '.$car.' no longer belongs to this railroad.');
        $exception=(string)($post['exception_outcome'][$key]??'');
        if(!in_array($exception,['','not_moved','moved_elsewhere'],true))throw new RuntimeException('Choose a valid exception outcome for '.$car.'.');
        $notes=substr(trim((string)($post['exception_notes'][$key]??'')),0,255);$reason=null;$outcome='moved';$actualIndustry=(int)$move['destination_industry_id'];$actualTrack=substr(trim((string)$move['destination_track']),0,120);$updateEquipment=true;
        if($exception==='not_moved'){
            $outcome='not_moved';$updateEquipment=false;$reason=(string)($post['reason'][$key]??'');
            if(!in_array($reason,$reasons,true))throw new RuntimeException('Not Moved car '.$car.' needs a valid exception reason.');
            if($reason==='other'&&$notes==='')throw new RuntimeException('Other exceptions require notes for '.$car.'.');
            $actualIndustry=(int)$equipment['current_industry_id'];$actualTrack=(string)$equipment['current_track'];$notMoved++;
        }else{
            if((int)$equipment['current_industry_id']!==(int)$move['origin_industry_id']||trim((string)$equipment['current_track'])!==trim((string)$move['origin_track']))throw new RuntimeException('Stale physical state detected for '.$car.'. Its location changed after approval; no results were applied.');
            if($exception==='moved_elsewhere'){
                $actualIndustry=(int)($post['actual_industry_id'][$key]??0);$actualTrack=substr(trim((string)($post['actual_track'][$key]??'')),0,120);$reason='moved_different_location';
                if($actualIndustry<=0||!$industryExists($actualIndustry))throw new RuntimeException('Choose a valid actual destination for '.$car.'.');
                if($notes==='')throw new RuntimeException('Moved to a Different Location requires a note for '.$car.'.');
                $movedDifferent++;
            }else{
                if($actualIndustry<=0||!$industryExists($actualIndustry))throw new RuntimeException('The planned destination for '.$car.' is no longer valid.');
                $movedAsPlanned++;
            }
            $equipmentById[$equipmentId]['current_industry_id']=$actualIndustry;$equipmentById[$equipmentId]['current_track']=$actualTrack;
        }
        $results[]=['move_id'=>(int)$move['id'],'equipment_id'=>$equipmentId,'outcome'=>$outcome,'actual_industry_id'=>$actualIndustry,'actual_track'=>$actualTrack,'actual_load_status'=>(string)$equipment['load_status'],'reason'=>$reason,'notes'=>$notes!==''?$notes:null,'update_equipment'=>$updateEquipment];
    }
    return ['results'=>$results,'moved_as_planned'=>$movedAsPlanned,'moved_different'=>$movedDifferent,'moved'=>$movedAsPlanned+$movedDifferent,'not_moved'=>$notMoved];
}

function ttCompleteWorkOrderLoadNeutral(PDO $pdo,array $list,int $railroadId,array $post,int $userId=0):array
{
    $pdo->beginTransaction();
    $lock=$pdo->prepare('SELECT sl.status list_status,a.status assignment_status,s.status session_status FROM operation_switch_lists sl JOIN operation_assignments a ON a.id=sl.assignment_id AND a.railroad_id=sl.railroad_id JOIN operating_sessions s ON s.id=sl.session_id AND s.railroad_id=sl.railroad_id WHERE sl.id=? AND sl.railroad_id=? FOR UPDATE');$lock->execute([(int)$list['id'],$railroadId]);$locked=$lock->fetch(PDO::FETCH_ASSOC);
    if(!$locked||$locked['session_status']!=='in_progress'||!in_array($locked['assignment_status'],['ready','in_progress','needs_review'],true)||!in_array($locked['list_status'],['approved','in_progress','needs_review'],true))throw new RuntimeException('This work order can only be completed while its operating session is Active.');
    $stmt=$pdo->prepare('SELECT * FROM operation_switch_list_moves WHERE switch_list_id=? AND railroad_id=? AND equipment_id IS NOT NULL ORDER BY sequence_number FOR UPDATE');$stmt->execute([(int)$list['id'],$railroadId]);$moves=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $lockEquipment=$pdo->prepare('SELECT id,current_industry_id,current_track,load_status FROM equipment WHERE id=? AND railroad_id=? FOR UPDATE');$equipmentById=[];
    foreach($moves as$move){$equipmentId=(int)$move['equipment_id'];if(isset($equipmentById[$equipmentId]))continue;$lockEquipment->execute([$equipmentId,$railroadId]);$equipment=$lockEquipment->fetch(PDO::FETCH_ASSOC);if($equipment)$equipmentById[$equipmentId]=$equipment;}
    $validIndustry=$pdo->prepare('SELECT id FROM industries WHERE id=? AND railroad_id=? AND active=1');
    $plan=ttPrepareWorkOrderResults($moves,$equipmentById,$post,static function(int$industryId)use($validIndustry,$railroadId):bool{$validIndustry->execute([$industryId,$railroadId]);return(bool)$validIndustry->fetchColumn();});
    $updateMove=$pdo->prepare('UPDATE operation_switch_list_moves SET actual_outcome=?,actual_industry_id=?,actual_track=?,actual_load_status=?,exception_reason_code=?,exception_notes=?,progress_complete=1,progress_updated_at=NOW(),completed_at=NOW() WHERE id=?');
    $updateEquipment=$pdo->prepare('UPDATE equipment SET current_industry_id=?,current_track=? WHERE id=? AND railroad_id=?');
    $repairQueueEnabled=ttOperationsModuleEnabled($pdo,$railroadId,'repair_queue');
    foreach($plan['results']as$result){if($result['update_equipment'])$updateEquipment->execute([$result['actual_industry_id'],$result['actual_track'],$result['equipment_id'],$railroadId]);$updateMove->execute([$result['outcome'],$result['actual_industry_id'],$result['actual_track'],$result['actual_load_status'],$result['reason'],$result['notes'],$result['move_id']]);if($repairQueueEnabled&&$result['reason']==='bad_order')ttEnsureBadOrderRepair($pdo,$railroadId,$result['equipment_id'],$result['move_id'],$result['notes'],$userId);}
    $endIndustry=(int)$list['end_industry_id'];if($endIndustry<=0&&$list['end_plan']==='return_origin')$endIndustry=(int)$list['operating_base_industry_id'];
    if($endIndustry>0)$pdo->prepare('UPDATE equipment e JOIN operation_assignment_locomotives al ON al.equipment_id=e.id SET e.current_industry_id=?,e.current_track=? WHERE al.assignment_id=? AND e.railroad_id=?')->execute([$endIndustry,(string)($list['end_track']?:$list['starting_track']),(int)$list['assignment_id'],$railroadId]);
    $pdo->prepare("UPDATE operation_switch_lists SET status='completed',completed_at=NOW(),moved_count=?,not_moved_count=? WHERE id=?")->execute([$plan['moved'],$plan['not_moved'],(int)$list['id']]);
    $pdo->prepare("UPDATE operation_assignments SET status='completed',completed_at=NOW() WHERE id=?")->execute([(int)$list['assignment_id']]);
    $pdo->prepare("UPDATE prepared_cuts SET status='released' WHERE id=(SELECT prepared_cut_id FROM operation_assignments WHERE id=?) AND status IN('assigned','in_use')")->execute([(int)$list['assignment_id']]);
    $dependents=$pdo->prepare("SELECT id FROM operation_assignments WHERE predecessor_assignment_id=? AND railroad_id=? AND status='waiting' FOR UPDATE");$dependents->execute([(int)$list['assignment_id'],$railroadId]);foreach($dependents->fetchAll(PDO::FETCH_ASSOC)as$dependent)$pdo->prepare('UPDATE operation_assignments SET status=? WHERE id=?')->execute([$plan['not_moved']===0?'ready':'needs_review',(int)$dependent['id']]);
    $pdo->commit();return$plan;
}
