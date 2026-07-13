<?php

function ttAssignmentStartMethods(): array
{
    return ['locomotives_only','coupled_selected','prepared_cut','manual','auto_build'];
}

function ttAssignmentEndPlans(): array
{
    return ['return_origin','terminate_elsewhere'];
}

function ttOperatingSessionIsEditable(string $status): bool
{
    return in_array($status,['draft','ready'],true);
}

function ttAssignmentPatternOverrideValue(array $assignment): string
{
    return (string)$assignment['operating_pattern']===(string)$assignment['type_snapshot']?'':(string)$assignment['operating_pattern'];
}

function ttAssignmentIsEditable(array $assignment, array $listStatuses = []): bool
{
    if(isset($assignment['session_status'])&&!ttOperatingSessionIsEditable((string)$assignment['session_status']))return false;
    if (!in_array((string)$assignment['status'], ['draft','waiting'], true)) return false;
    return count(array_intersect($listStatuses, ['approved','in_progress','completed','needs_review'])) === 0;
}

function ttAssignmentCanGenerate(array $assignment,array $listStatuses):bool
{
    return ttOperatingSessionIsEditable((string)($assignment['session_status']??''))
        &&in_array((string)($assignment['status']??''),['draft','waiting'],true)
        &&($assignment['start_method']??'')!=='inherit'
        &&in_array((string)($assignment['end_plan']??''),ttAssignmentEndPlans(),true)
        &&count(array_intersect($listStatuses,['approved','in_progress','completed','needs_review']))===0;
}

function ttSwitchListRevisionState(array $rows):array
{
    $current=null;
    foreach($rows as$row)if($current===null&&$row['status']!=='cancelled')$current=$row;
    return ['rows'=>$rows,'statuses'=>array_column($rows,'status'),'latest'=>$rows[0]??null,'current'=>$current,'max_revision'=>$rows?(int)$rows[0]['revision_number']:0];
}

function ttSwitchListRevisionSummary(PDO $pdo,int $assignmentId,int $railroadId,bool $lock=false):array
{
    $sql='SELECT id,revision_number,status FROM operation_switch_lists WHERE assignment_id=? AND railroad_id=? ORDER BY revision_number DESC'.($lock?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute([$assignmentId,$railroadId]);return ttSwitchListRevisionState($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function ttCancelDraftSwitchLists(PDO $pdo,int $assignmentId,int $railroadId,string $note):void
{
    $stmt=$pdo->prepare("UPDATE operation_switch_lists SET status='cancelled',cancelled_at=NOW(),notes=CONCAT_WS(' ',NULLIF(TRIM(COALESCE(notes,'')),''),?) WHERE assignment_id=? AND railroad_id=? AND status='draft'");
    $stmt->execute([$note,$assignmentId,$railroadId]);
}

function ttSwitchListIsApprovable(array $list,array $assignment,array $summary):bool
{
    return ($list['status']??'')==='draft'
        &&(int)($summary['latest']['id']??0)===(int)($list['id']??0)
        &&ttAssignmentCanGenerate($assignment,(array)($summary['statuses']??[]));
}

function ttAssignmentNormalizeInput(PDO $pdo, int $railroadId, int $sessionId, array $input, ?int $assignmentId = null): array
{
    $jobId=(int)($input['job_template_id']??0);
    $stmt=$pdo->prepare('SELECT * FROM jobs WHERE id=? AND railroad_id=? AND active=1');$stmt->execute([$jobId,$railroadId]);$job=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$job)throw new RuntimeException('Choose an active Job Title.');
    $start=(string)($input['start_method']??'locomotives_only');if($start==='inherit')throw new RuntimeException('Inheritance is not yet supported. Configure this assignment with a supported start method.');if(!in_array($start,ttAssignmentStartMethods(),true))throw new RuntimeException('Invalid start method.');
    $patterns=ttJobTypes();$pattern=trim((string)($input['operating_pattern']??''));if($pattern==='')$pattern=(string)$job['job_type'];if(!isset($patterns[$pattern]))throw new RuntimeException('Invalid operating pattern.');
    $base=(int)($input['operating_base_industry_id']??0);if($base<=0)throw new RuntimeException('Choose an operating base.');
    if($base>0){$stmt=$pdo->prepare('SELECT id FROM industries WHERE id=? AND railroad_id=? AND active=1');$stmt->execute([$base,$railroadId]);if(!$stmt->fetchColumn())throw new RuntimeException('Invalid operating base.');}
    $predecessor=0;$dependency=null;
    $cut=$start==='prepared_cut'?(int)($input['prepared_cut_id']??0):0;if($start==='prepared_cut'&&$cut<=0)throw new RuntimeException('Choose an available prepared cut.');
    $endPlan=(string)($input['end_plan']??'return_origin');if(!in_array($endPlan,ttAssignmentEndPlans(),true))throw new RuntimeException('This end plan is retained for legacy history but is not supported for new or edited assignments.');
    $endId=$endPlan==='return_origin'?0:(int)($input['end_industry_id']??0);$endTrack=$endPlan==='return_origin'?'':substr(trim((string)($input['end_track']??'')),0,120);
    if($endPlan==='terminate_elsewhere'&&$endId<=0)throw new RuntimeException('Choose an End Location when terminating elsewhere.');
    if($endId>0){$stmt=$pdo->prepare('SELECT id FROM industries WHERE id=? AND railroad_id=? AND active=1');$stmt->execute([$endId,$railroadId]);if(!$stmt->fetchColumn())throw new RuntimeException('Invalid end location.');}
    return [
        'job'=>$job,'job_id'=>$jobId,'pattern'=>$pattern,'start_method'=>$start,
        'base_id'=>$base?:null,'starting_track'=>substr(trim((string)($input['starting_track']??'')),0,120),
        'cut_id'=>$cut?:null,'requested'=>max(0,min(100,(int)($input['requested_car_count']??10))),
        'prepared_cut_count'=>max(0,min(100,(int)($input['prepared_cut_car_count']??10))),
        'difficulty'=>in_array($input['difficulty']??'', ['easy','medium','hard'],true)?$input['difficulty']:'medium',
        'crew'=>substr(trim((string)($input['crew_name']??'')),0,120),'predecessor'=>$predecessor?:null,
        'dependency'=>$dependency,'end_plan'=>$endPlan,'end_id'=>$endId?:null,'end_track'=>$endTrack,
        'notes'=>substr(trim((string)($input['notes']??'')),0,5000),
        'locomotive_ids'=>array_values(array_unique(array_filter(array_map('intval',(array)($input['locomotive_ids']??[]))))),
        'starting_car_ids'=>array_values(array_unique(array_filter(array_map('intval',(array)($input['starting_car_ids']??[])))))
    ];
}

function ttAssignmentReplaceLocomotives(PDO $pdo,int $assignmentId,int $railroadId,array $ids,array $reserved):void
{
    $pdo->prepare('DELETE FROM operation_assignment_locomotives WHERE assignment_id=?')->execute([$assignmentId]);$position=1;
    foreach($ids as$id){if(in_array($id,$reserved,true))throw new RuntimeException('A selected locomotive is reserved by another active assignment.');$stmt=$pdo->prepare("SELECT id FROM equipment WHERE id=? AND railroad_id=? AND active=1 AND equipment_class='Locomotive'");$stmt->execute([$id,$railroadId]);if(!$stmt->fetchColumn())throw new RuntimeException('Invalid locomotive selection.');$pdo->prepare("INSERT INTO operation_assignment_locomotives(assignment_id,equipment_id,position,source) VALUES(?,?,?,'selected')")->execute([$assignmentId,$id,$position++]);}
}

function ttAssignmentReplaceStartingCars(PDO $pdo,int $assignmentId,int $railroadId,array $data,array $reserved,?int $oldCutId=null):void
{
    $newCut=$data['cut_id'];
    if($oldCutId&&$oldCutId!==$newCut){$pdo->prepare("UPDATE prepared_cuts SET status='ready' WHERE id=? AND railroad_id=? AND status='assigned'")->execute([$oldCutId,$railroadId]);}
    $pdo->prepare('DELETE FROM operation_assignment_starting_cars WHERE assignment_id=?')->execute([$assignmentId]);
    if($data['start_method']==='prepared_cut'){
        $stmt=$pdo->prepare("SELECT id FROM prepared_cuts WHERE id=? AND railroad_id=? AND (status='ready' OR (status='assigned' AND id=?)) FOR UPDATE");$stmt->execute([$newCut,$railroadId,$oldCutId?:0]);if(!$stmt->fetchColumn())throw new RuntimeException('Prepared cut is no longer available.');
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM prepared_cut_cars WHERE prepared_cut_id=?');$stmt->execute([$newCut]);$cutCarCount=(int)$stmt->fetchColumn();
        $stmt=$pdo->prepare("SELECT pc.equipment_id,pc.position FROM prepared_cut_cars pc JOIN equipment e ON e.id=pc.equipment_id WHERE pc.prepared_cut_id=? AND e.railroad_id=? AND e.active=1 AND COALESCE(e.equipment_class,'')<>'Locomotive' ORDER BY pc.position");$stmt->execute([$newCut,$railroadId]);$cars=$stmt->fetchAll(PDO::FETCH_ASSOC);if(!$cars||count($cars)!==$cutCarCount)throw new RuntimeException('Every car in the prepared cut must still be active and eligible.');
        $ins=$pdo->prepare("INSERT INTO operation_assignment_starting_cars(assignment_id,equipment_id,position,source_type,source_id) VALUES(?,?,?,'prepared_cut',?)");foreach($cars as$car){$id=(int)$car['equipment_id'];if(in_array($id,$reserved,true))throw new RuntimeException('A prepared-cut car is reserved elsewhere.');$ins->execute([$assignmentId,$id,(int)$car['position'],$newCut]);}
        $stmt=$pdo->prepare("UPDATE prepared_cuts SET status='assigned' WHERE id=? AND railroad_id=? AND (status='ready' OR (status='assigned' AND id=?))");$stmt->execute([$newCut,$railroadId,$oldCutId?:0]);
    } elseif(in_array($data['start_method'],['manual','coupled_selected'],true)){
        $ins=$pdo->prepare("INSERT INTO operation_assignment_starting_cars(assignment_id,equipment_id,position,source_type) VALUES(?,?,?,'selected')");$position=1;foreach($data['starting_car_ids']as$id){if(in_array($id,$reserved,true))throw new RuntimeException('A selected starting car is reserved elsewhere.');$stmt=$pdo->prepare("SELECT id,current_industry_id,current_track FROM equipment WHERE id=? AND railroad_id=? AND active=1 AND COALESCE(equipment_class,'')<>'Locomotive'");$stmt->execute([$id,$railroadId]);$car=$stmt->fetch(PDO::FETCH_ASSOC);if(!$car)throw new RuntimeException('Invalid starting-car selection.');if((int)$car['current_industry_id']!==(int)$data['base_id'])throw new RuntimeException('Every starting car must be at the selected Operating Base.');if($data['start_method']==='coupled_selected'&&$data['starting_track']!==''&&(string)$car['current_track']!==$data['starting_track'])throw new RuntimeException('Every coupled starting car must be on the selected Starting Track.');$ins->execute([$assignmentId,$id,$position++]);}
    }
}

function ttAssignmentListStatuses(PDO $pdo,int $assignmentId,int $railroadId):array
{
    return ttSwitchListRevisionSummary($pdo,$assignmentId,$railroadId)['statuses'];
}

function ttSessionStartReadiness(array $assignments): array
{
    $active=array_values(array_filter($assignments,static fn($a)=>($a['status']??'')!=='cancelled'));
    if(!$active)return [false,'Add at least one assignment before starting the session.'];
    $byId=[];foreach($active as$a)$byId[(int)($a['id']??0)]=$a;
    foreach($active as$a){
        if(($a['latest_list_status']??'')!=='approved')return [false,'Generate and approve the latest switch list for every assignment before starting the session.'];
        if(($a['status']??'')==='ready')continue;
        if(($a['status']??'')!=='waiting')return [false,'Generate and approve the latest switch list for every assignment before starting the session.'];
        $predecessor=$byId[(int)($a['predecessor_assignment_id']??0)]??null;
        if(!$predecessor||($predecessor['status']??'')!=='ready'||($predecessor['latest_list_status']??'')!=='approved')return [false,'A waiting assignment requires a ready predecessor with an approved latest switch list.'];
    }
    return [true,''];
}
