<?php
require_once __DIR__ . '/lib.php';

function ttChooseOperationsMoves(array $assignment, array $cars, array $industries, array $startingIds, array $reservedIds): array
{
    $max = max(0, (int)$assignment['requested_car_count']);
    $baseId = (int)$assignment['operating_base_industry_id'];
    $endId = (int)$assignment['end_industry_id'];
    $industryById = [];
    $occupancy = [];
    foreach ($industries as $industry) {
        $id = (int)$industry['id']; $industryById[$id] = $industry; $occupancy[$id] = 0;
    }
    foreach ($cars as $car) { $current=(int)$car['current_industry_id']; if($current>0)$occupancy[$current]=($occupancy[$current]??0)+1; }
    $capacityDelta = []; $used = []; $moves = []; $diagnostics = [];
    $isYard = static function(array $industry): bool { return preg_match('/yard|staging|interchange|classification/i', ($industry['industry_name']??'').' '.($industry['industry_type']??'')) === 1; };
    $capacityAvailable = static function(array $destination) use (&$occupancy,&$capacityDelta): bool { $id=(int)$destination['id'];$cap=(int)($destination['track_capacity']??0);return $cap<=0 || (($occupancy[$id]??0)+($capacityDelta[$id]??0))<$cap; };
    usort($cars, static function($a,$b){return [strtolower((string)$a['reporting_marks']),(string)$a['road_number'],(int)$a['id']] <=> [strtolower((string)$b['reporting_marks']),(string)$b['road_number'],(int)$b['id']];});
    foreach ($cars as $car) {
        if (count($moves) >= $max) break;
        $carId=(int)$car['id'];$originId=(int)$car['current_industry_id'];
        if(isset($used[$carId])||in_array($carId,$reservedIds,true)||trim((string)$car['operations_service'])==='')continue;
        $isStarting=in_array($carId,$startingIds,true)||$originId===$baseId;
        if($isStarting){
            $field=strcasecmp((string)$car['load_status'],'Loaded')===0?'receives_services':'ships_services';$choices=[];
            foreach($industries as $destination){$destinationId=(int)$destination['id'];if($destinationId===$originId||!ttIndustrySupports($destination,$field,(string)$car['operations_service'])||!$capacityAvailable($destination))continue;$choices[]=$destination;}
            usort($choices,static fn($a,$b)=>[$a['industry_name'],(int)$a['id']]<=>[$b['industry_name'],(int)$b['id']]);
            if($choices){$destination=$choices[0];$capacityDelta[(int)$destination['id']]=($capacityDelta[(int)$destination['id']]??0)+1;$capacityDelta[$originId]=($capacityDelta[$originId]??0)-1;$moves[]=ttPlannedCarMove($car,$destination,'SPOT','Set out at '.$destination['industry_name']);$used[$carId]=true;}else{$diagnostics[]='No compatible capacity-safe destination for '.trim($car['reporting_marks'].' '.$car['road_number']).'.';}
            continue;
        }
        $origin=$industryById[$originId]??null;if(!$origin)continue;$ready=ttIndustrySupports($origin,'ships_services',(string)$car['operations_service'])||ttIndustrySupports($origin,'receives_services',(string)$car['operations_service']);if(!$ready)continue;
        $destination=null;if($endId>0&&isset($industryById[$endId])&&$endId!==$originId&&$capacityAvailable($industryById[$endId])){$destination=$industryById[$endId];}else{foreach($industries as $candidate){if((int)$candidate['id']!==$originId&&(int)$candidate['id']!==$baseId&&$isYard($candidate)&&$capacityAvailable($candidate)){$destination=$candidate;break;}}}
        if(!$destination){$diagnostics[]='No explicit terminal, interchange, staging, or non-base yard destination for pull '.trim($car['reporting_marks'].' '.$car['road_number']).'.';continue;}
        $capacityDelta[(int)$destination['id']]=($capacityDelta[(int)$destination['id']]??0)+1;$capacityDelta[$originId]=($capacityDelta[$originId]??0)-1;$moves[]=ttPlannedCarMove($car,$destination,'PULL','Pull from '.$origin['industry_name'].' for '.$destination['industry_name']);$used[$carId]=true;
    }
    if(count($moves)<$max)$diagnostics[]='Requested up to '.$max.' cars; '.count($moves).' valid, unreserved, capacity-safe moves were available.';
    return ['moves'=>$moves,'diagnostics'=>$diagnostics];
}

function ttPlannedCarMove(array $car, array $destination, string $action, string $instruction): array
{
    return ['equipment_id'=>(int)$car['id'],'movement_group'=>bin2hex(random_bytes(12)),'reporting_marks_snapshot'=>$car['reporting_marks'],'road_number_snapshot'=>$car['road_number'],'equipment_type_snapshot'=>$car['equipment_type'],'service_snapshot'=>$car['operations_service'],'photo_filename_snapshot'=>$car['photo_filename']??null,'original_load_status'=>$car['load_status'],'planned_load_status'=>$car['load_status'],'origin_industry_id'=>(int)$car['current_industry_id'],'origin_name_snapshot'=>$car['origin_name'],'origin_track'=>$car['current_track'],'destination_industry_id'=>(int)$destination['id'],'destination_name_snapshot'=>$destination['industry_name'],'destination_track'=>'','action'=>$action,'instruction'=>$instruction,'work_location'=>$action==='PULL'?$car['origin_name']:$destination['industry_name']];
}
