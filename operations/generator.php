<?php
require_once __DIR__ . '/lib.php';

function ttChooseOperationsMoves(array $assignment, array $cars, array $industries, array $startingIds, array $reservedIds, array $routeStops = []): array
{
    $selectedRoute = ($assignment['work_scope'] ?? 'entire_railroad') === 'selected_route';
    if ($selectedRoute) {
        if (!$routeStops) {
            throw new RuntimeException('This Job Title uses Selected Route / Territory but has no route stops. Add at least one stop or change the scope to Entire Railroad.');
        }
        ttAssertUniqueRouteStops($routeStops, $industries);
    }
    if (($assignment['start_method'] ?? '') === 'prepared_cut') {
        return ttChoosePreparedCutMoves($assignment, $cars, $industries, $startingIds, $reservedIds, $routeStops);
    }
    if ($selectedRoute) {
        return ttChooseSelectedRouteMoves($assignment, $cars, $industries, $startingIds, $reservedIds, $routeStops);
    }
    return ttChooseBroadOperationsMoves($assignment, $cars, $industries, $startingIds, $reservedIds);
}

function ttPreparedCutStatusIsAssigned(?array $cut): bool
{
    return $cut !== null && ($cut['status'] ?? '') === 'assigned';
}

function ttAssertUniqueRouteStops(array $routeStops, array $industries): void
{
    $names = [];
    foreach ($industries as $industry) $names[(int)$industry['id']] = (string)$industry['industry_name'];
    $seen = [];
    foreach ($routeStops as $stop) {
        $industryId = (int)($stop['industry_id'] ?? 0);
        if ($industryId <= 0) continue;
        if (isset($seen[$industryId])) {
            $name = $names[$industryId] ?? ('location #'.$industryId);
            throw new RuntimeException('Duplicate route location '.$name.' is configured. Remove the duplicate before generating a switch list.');
        }
        $seen[$industryId] = true;
    }
}

function ttChoosePreparedCutMoves(array $assignment,array $cars,array $industries,array $startingIds,array $reservedIds,array $routeStops=[]):array
{
    $selectedRoute=($assignment['work_scope']??'entire_railroad')==='selected_route';if($selectedRoute&&!$routeStops)throw new RuntimeException('This Job Title uses Selected Route / Territory but has no route stops. Add at least one stop or change the scope to Entire Railroad.');
    $industryById=[];$occupancy=[];foreach($industries as$industry){$id=(int)$industry['id'];$industryById[$id]=$industry;$occupancy[$id]=0;}foreach($cars as$car){$id=(int)$car['current_industry_id'];if($id>0)$occupancy[$id]=($occupancy[$id]??0)+1;}
    $carById=[];foreach($cars as$car)$carById[(int)$car['id']]=$car;$startingIds=array_values(array_unique(array_map('intval',$startingIds)));$startingSet=array_fill_keys($startingIds,true);$startingCars=[];$diagnostics=[];
    foreach($startingIds as$id){$car=$carById[$id]??null;if(!$car||in_array($id,$reservedIds,true)){$diagnostics[]='Prepared-cut car '.$id.' is missing, inactive, or reserved by another assignment; no substitute was used.';continue;}$carName=trim($car['reporting_marks'].' '.$car['road_number']);if((int)($assignment['prepared_cut_industry_id']??0)>0&&(int)$car['current_industry_id']!==(int)$assignment['prepared_cut_industry_id']){$diagnostics[]='Prepared-cut car '.$carName.' is no longer at the saved cut location; no move was created.';continue;}$savedTrack=trim((string)($assignment['prepared_cut_track']??''));if($savedTrack!==''&&trim((string)$car['current_track'])!==$savedTrack){$diagnostics[]='Prepared-cut car '.$carName.' is no longer on the saved cut track '.$savedTrack.'; no move was created.';continue;}$startingCars[]=$car;}
    $capacityDelta=[];$used=[];$groups=[];
    if($selectedRoute){
        usort($routeStops,static fn($a,$b)=>(int)$a['sequence_number']<=>(int)$b['sequence_number']);$routeIds=array_map(static fn($stop)=>(int)$stop['industry_id'],$routeStops);$supportIds=[(int)$assignment['operating_base_industry_id'],(int)$assignment['end_industry_id']];foreach($routeStops as$stop){$supportIds[]=(int)$stop['pull_destination_industry_id'];$supportIds[]=(int)$stop['replacement_source_industry_id'];}$allowedIds=array_values(array_unique(array_filter(array_merge($routeIds,$supportIds))));$pickupLimit=max(0,(int)$assignment['requested_car_count']);$pickupCount=0;
        foreach($routeStops as$stopIndex=>$stop){$stopId=(int)$stop['industry_id'];$stopIndustry=$industryById[$stopId]??null;if(!$stopIndustry)continue;$groups[$stopId]=['industry'=>$stopIndustry,'pulls'=>[],'spots'=>[]];$destination=ttResolvePullDestination($stop,$stopIndex,$routeStops,$industryById,$allowedIds,(int)$assignment['operating_base_industry_id']);
            if($destination&&$pickupCount<$pickupLimit){$pullCandidates=[];foreach($cars as$car){$id=(int)$car['id'];if(isset($startingSet[$id])||isset($used[$id])||in_array($id,$reservedIds,true)||(int)$car['current_industry_id']!==$stopId||trim((string)$car['operations_service'])===''||!ttRouteOutboundReady($car,$stopIndustry)||!ttLoadMatches((string)$car['load_status'],(string)($stop['outbound_load_status']??'Any')))continue;$pullCandidates[]=$car;}usort($pullCandidates,'ttCarSort');foreach($pullCandidates as$car){if($pickupCount>=$pickupLimit)break;if(!ttCarCompatibleWithIndustry($car,$destination))continue;if(!ttRouteCapacityAvailable($destination,$occupancy,$capacityDelta))continue;$groups[$stopId]['pulls'][]=ttPlannedCarMove($car,$destination,'PULL','Pull for '.$destination['industry_name'],null,$stopIndustry['industry_name']);$used[(int)$car['id']]=true;$capacityDelta[$stopId]=($capacityDelta[$stopId]??0)-1;$destId=(int)$destination['id'];$capacityDelta[$destId]=($capacityDelta[$destId]??0)+1;$pickupCount++;}}
            foreach($startingCars as$car){$id=(int)$car['id'];if(isset($used[$id])||(int)$car['current_industry_id']===$stopId||!ttLoadMatches((string)$car['load_status'],(string)($stop['inbound_load_status']??'Any'))||!ttCarCompatibleWithIndustry($car,$stopIndustry)||!ttRouteCapacityAvailable($stopIndustry,$occupancy,$capacityDelta))continue;$groups[$stopId]['spots'][]=ttPlannedCarMove($car,$stopIndustry,'SPOT','Spot from prepared cut',null,$stopIndustry['industry_name']);$used[$id]=true;$originId=(int)$car['current_industry_id'];$capacityDelta[$originId]=($capacityDelta[$originId]??0)-1;$capacityDelta[$stopId]=($capacityDelta[$stopId]??0)+1;}
        }
    }else{
        $destinations=$industries;usort($destinations,static fn($a,$b)=>[strtolower((string)$a['industry_name']),(int)$a['id']]<=>[strtolower((string)$b['industry_name']),(int)$b['id']]);foreach($startingCars as$car){$id=(int)$car['id'];foreach($destinations as$destination){$destId=(int)$destination['id'];if($destId===(int)$car['current_industry_id']||!ttCarCompatibleWithIndustry($car,$destination)||!ttRouteCapacityAvailable($destination,$occupancy,$capacityDelta))continue;$groups[$destId]??=['industry'=>$destination,'pulls'=>[],'spots'=>[]];$groups[$destId]['spots'][]=ttPlannedCarMove($car,$destination,'SPOT','Spot from prepared cut',null,$destination['industry_name']);$used[$id]=true;$originId=(int)$car['current_industry_id'];$capacityDelta[$originId]=($capacityDelta[$originId]??0)-1;$capacityDelta[$destId]=($capacityDelta[$destId]??0)+1;break;}}
    }
    foreach($startingCars as$car)if(!isset($used[(int)$car['id']]))$diagnostics[]='No compatible capacity-safe Spot destination for prepared-cut car '.trim($car['reporting_marks'].' '.$car['road_number']).'; it remains with the train.';
    $moves=[];foreach($groups as$group)foreach(array_merge($group['pulls'],$group['spots'])as$move)$moves[]=$move;
    return ['moves'=>$moves,'diagnostics'=>$diagnostics];
}

function ttPreparedCutPickupInstruction(array $assignment,array $cut):string
{
    $label=trim((string)$cut['cut_number'].' — '.(string)$cut['name']).' ('.(int)$cut['car_count'].' cars)';$track=trim((string)$cut['current_track']);$where=(string)$cut['industry_name'].($track!==''?' on '.$track:'');
    if((int)$assignment['operating_base_industry_id']===(int)$cut['current_industry_id'])return 'Couple to '.$label.' at '.$where.' and perform a brake test.';
    return 'Run light from '.(string)$assignment['base_name'].' to '.$where.', couple to '.$label.', and perform a brake test.';
}

function ttChooseSelectedRouteMoves(array $assignment, array $cars, array $industries, array $startingIds, array $reservedIds, array $routeStops): array
{
    $max=max(0,(int)$assignment['requested_car_count']);$baseId=(int)$assignment['operating_base_industry_id'];$endId=(int)$assignment['end_industry_id'];
    $industryById=[];$occupancy=[];foreach($industries as $industry){$id=(int)$industry['id'];$industryById[$id]=$industry;$occupancy[$id]=0;}foreach($cars as $car){$id=(int)$car['current_industry_id'];if($id>0)$occupancy[$id]=($occupancy[$id]??0)+1;}
    usort($routeStops,static fn($a,$b)=>(int)$a['sequence_number']<=>(int)$b['sequence_number']);
    $routeIds=array_map(static fn($stop)=>(int)$stop['industry_id'],$routeStops);$supportIds=[$baseId,$endId];
    foreach($routeStops as $stop){$supportIds[]=(int)$stop['pull_destination_industry_id'];$supportIds[]=(int)$stop['replacement_source_industry_id'];}
    $allowedIds=array_values(array_unique(array_filter(array_merge($routeIds,$supportIds))));$used=[];$moves=[];$diagnostics=[];$capacityDelta=[];
    foreach($routeStops as $stopIndex=>$stop){
        if(count($moves)>=$max)break;$stopId=(int)$stop['industry_id'];$stopIndustry=$industryById[$stopId]??null;if(!$stopIndustry)continue;
        $destination=ttResolvePullDestination($stop,$stopIndex,$routeStops,$industryById,$allowedIds,$baseId);$pullCandidates=[];$spotCandidates=[];
        foreach($cars as $car){$carId=(int)$car['id'];$originId=(int)$car['current_industry_id'];if(isset($used[$carId])||in_array($carId,$reservedIds,true)||!in_array($originId,$allowedIds,true)||trim((string)$car['operations_service'])==='')continue;
            if($originId===$stopId&&ttRouteOutboundReady($car,$stopIndustry))$pullCandidates[]=$car;
            elseif($originId!==$stopId&&ttReplacementSourceMatches($car,$stop,$startingIds,$baseId)&&ttCarCompatibleWithIndustry($car,$stopIndustry))$spotCandidates[]=$car;
        }
        usort($pullCandidates,'ttCarSort');usort($spotCandidates,'ttCarSort');$pulls=[];$spots=[];
        if((int)($stop['exchange_enabled']??0)===1){
            foreach($pullCandidates as $outCar){if(count($moves)+count($pulls)+count($spots)+2>$max||!$destination)break;if(!ttLoadMatches((string)$outCar['load_status'],(string)$stop['outbound_load_status']))continue;$match=null;
                foreach($spotCandidates as $index=>$candidate){if(isset($used[(int)$candidate['id']])||!ttLoadMatches((string)$candidate['load_status'],(string)$stop['inbound_load_status']))continue;if(ttNormalizeService($candidate['operations_service'])===ttNormalizeService($outCar['operations_service'])){$match=$index;break;}}
                if($match===null)continue;$inCar=$spotCandidates[$match];$destId=(int)$destination['id'];$sourceId=(int)$inCar['current_industry_id'];$projected=$capacityDelta;$projected[$sourceId]=($projected[$sourceId]??0)-1;if(!ttRouteCapacityAvailable($destination,$occupancy,$projected))continue;
                $group=bin2hex(random_bytes(12));$pulls[]=ttPlannedCarMove($outCar,$destination,'PULL','Pull '.strtolower((string)$outCar['load_status']).' car for '.$destination['industry_name'],$group,$stopIndustry['industry_name']);$spots[]=ttPlannedCarMove($inCar,$stopIndustry,'SPOT','Spot '.strtolower((string)$inCar['load_status']).' replacement from '.$inCar['origin_name'],$group,$stopIndustry['industry_name']);$used[(int)$outCar['id']]=true;$used[(int)$inCar['id']]=true;$capacityDelta[$sourceId]=($capacityDelta[$sourceId]??0)-1;$capacityDelta[$destId]=($capacityDelta[$destId]??0)+1;
            }
        }else{
            foreach($pullCandidates as $car){if(count($moves)+count($pulls)+count($spots)>=$max||!$destination)break;if($stop['pull_destination_mode']==='next_compatible'&&!ttCarCompatibleWithIndustry($car,$destination))continue;if(!ttRouteCapacityAvailable($destination,$occupancy,$capacityDelta))continue;$pulls[]=ttPlannedCarMove($car,$destination,'PULL','Pull for '.$destination['industry_name'],null,$stopIndustry['industry_name']);$used[(int)$car['id']]=true;$capacityDelta[$stopId]=($capacityDelta[$stopId]??0)-1;$destId=(int)$destination['id'];$capacityDelta[$destId]=($capacityDelta[$destId]??0)+1;}
            foreach($spotCandidates as $car){if(count($moves)+count($pulls)+count($spots)>=$max)break;if(!ttRouteCapacityAvailable($stopIndustry,$occupancy,$capacityDelta))break;$spots[]=ttPlannedCarMove($car,$stopIndustry,'SPOT','Spot from '.$car['origin_name'],null,$stopIndustry['industry_name']);$used[(int)$car['id']]=true;$sourceId=(int)$car['current_industry_id'];$capacityDelta[$sourceId]=($capacityDelta[$sourceId]??0)-1;$capacityDelta[$stopId]=($capacityDelta[$stopId]??0)+1;}
        }
        foreach(array_merge($pulls,$spots)as$move)$moves[]=$move;
        if((int)($stop['exchange_enabled']??0)===1&&$pullCandidates&&!$spots)$diagnostics[]=$stopIndustry['industry_name'].': paired exchange enabled, but no feasible compatible replacement pair was available.';
    }
    if(count($moves)<$max)$diagnostics[]='Selected Route / Territory requested up to '.$max.' move rows; '.count($moves).' valid route-scoped rows were available. No filler work was created.';
    return ['moves'=>$moves,'diagnostics'=>$diagnostics];
}

function ttRouteOutboundReady(array $car,array $industry):bool
{
    return ttIndustrySupports($industry,'ships_services',(string)$car['operations_service'])||ttIndustrySupports($industry,'receives_services',(string)$car['operations_service']);
}

function ttRouteCapacityAvailable(array $industry, array $occupancy, array $capacityDelta): bool
{
    $id = (int)$industry['id'];
    $capacity = (int)($industry['track_capacity'] ?? 0);
    return $capacity <= 0 || (($occupancy[$id] ?? 0) + ($capacityDelta[$id] ?? 0)) < $capacity;
}

function ttResolvePullDestination(array $stop, int $stopIndex, array $routeStops, array $industryById, array $servedIds, int $baseId): ?array
{
    $mode = (string)$stop['pull_destination_mode'];
    $configuredId = (int)$stop['pull_destination_industry_id'];
    $originId = (int)$stop['industry_id'];
    if ($mode === 'operating_base') $configuredId = $baseId;
    if (in_array($mode, ['selected_location','yard','staging_interchange'], true) && $configuredId > 0 && in_array($configuredId, $servedIds, true) && $configuredId !== $originId) return $industryById[$configuredId] ?? null;
    if ($mode === 'yard' || $mode === 'staging_interchange') {
        $pattern = $mode === 'yard' ? '/yard|classification/i' : '/staging|interchange/i';
        foreach ($servedIds as $id) if ($id !== $originId && isset($industryById[$id]) && preg_match($pattern, ($industryById[$id]['industry_name'] ?? '') . ' ' . ($industryById[$id]['industry_type'] ?? ''))) return $industryById[$id];
    }
    if ($mode === 'next_compatible') {
        for ($i = $stopIndex + 1; $i < count($routeStops); $i++) {
            $id = (int)$routeStops[$i]['industry_id'];
            if ($id !== $originId && isset($industryById[$id])) return $industryById[$id];
        }
    }
    if ($configuredId > 0 && $configuredId !== $originId && in_array($configuredId, $servedIds, true)) return $industryById[$configuredId] ?? null;
    return null;
}

function ttReplacementSourceMatches(array $car, array $stop, array $startingIds, int $baseId): bool
{
    $mode = (string)$stop['replacement_source_mode'];
    $carId = (int)$car['id'];
    $originId = (int)$car['current_industry_id'];
    if ($mode === 'operating_base') return $originId === $baseId;
    if (in_array($mode, ['starting_cars','prepared_cut','staged_group'], true)) return in_array($carId, $startingIds, true);
    if ($mode === 'selected_location') return $originId === (int)$stop['replacement_source_industry_id'];
    return false;
}

function ttLoadMatches(string $actual, string $rule): bool
{
    return $rule === 'Any' || strcasecmp($actual, $rule) === 0;
}

function ttCarCompatibleWithIndustry(array $car, array $industry): bool
{
    $field = strcasecmp((string)$car['load_status'], 'Loaded') === 0 ? 'receives_services' : 'ships_services';
    return ttIndustrySupports($industry, $field, (string)$car['operations_service']);
}

function ttCarSort(array $a, array $b): int
{
    return [strtolower((string)$a['reporting_marks']), (string)$a['road_number'], (int)$a['id']] <=> [strtolower((string)$b['reporting_marks']), (string)$b['road_number'], (int)$b['id']];
}

function ttChooseBroadOperationsMoves(array $assignment, array $cars, array $industries, array $startingIds, array $reservedIds): array
{
    $max=max(0,(int)$assignment['requested_car_count']);$baseId=(int)$assignment['operating_base_industry_id'];$endId=(int)$assignment['end_industry_id'];$industryById=[];$occupancy=[];
    foreach($industries as $industry){$id=(int)$industry['id'];$industryById[$id]=$industry;$occupancy[$id]=0;}foreach($cars as $car){$current=(int)$car['current_industry_id'];if($current>0)$occupancy[$current]=($occupancy[$current]??0)+1;}
    $capacityDelta=[];$used=[];$moves=[];$diagnostics=[];$isYard=static fn(array $industry):bool=>preg_match('/yard|staging|interchange|classification/i',($industry['industry_name']??'').' '.($industry['industry_type']??''))===1;$capacityAvailable=static function(array $destination)use(&$occupancy,&$capacityDelta):bool{$id=(int)$destination['id'];$cap=(int)($destination['track_capacity']??0);return $cap<=0||(($occupancy[$id]??0)+($capacityDelta[$id]??0))<$cap;};
    usort($cars,'ttCarSort');foreach($cars as $car){if(count($moves)>=$max)break;$carId=(int)$car['id'];$originId=(int)$car['current_industry_id'];if(isset($used[$carId])||in_array($carId,$reservedIds,true)||trim((string)$car['operations_service'])==='')continue;$isStarting=in_array($carId,$startingIds,true)||$originId===$baseId;
        if($isStarting){$field=strcasecmp((string)$car['load_status'],'Loaded')===0?'receives_services':'ships_services';$choices=[];foreach($industries as $destination){$destinationId=(int)$destination['id'];if($destinationId===$originId||!ttIndustrySupports($destination,$field,(string)$car['operations_service'])||!$capacityAvailable($destination))continue;$choices[]=$destination;}usort($choices,static fn($a,$b)=>[$a['industry_name'],(int)$a['id']]<=>[$b['industry_name'],(int)$b['id']]);if($choices){$destination=$choices[0];$capacityDelta[(int)$destination['id']]=($capacityDelta[(int)$destination['id']]??0)+1;$capacityDelta[$originId]=($capacityDelta[$originId]??0)-1;$moves[]=ttPlannedCarMove($car,$destination,'SPOT','Set out at '.$destination['industry_name']);$used[$carId]=true;}else{$diagnostics[]='No compatible capacity-safe destination for '.trim($car['reporting_marks'].' '.$car['road_number']).'.';}continue;}
        $origin=$industryById[$originId]??null;if(!$origin)continue;$ready=ttIndustrySupports($origin,'ships_services',(string)$car['operations_service'])||ttIndustrySupports($origin,'receives_services',(string)$car['operations_service']);if(!$ready)continue;$destination=null;if($endId>0&&isset($industryById[$endId])&&$endId!==$originId&&$capacityAvailable($industryById[$endId]))$destination=$industryById[$endId];else foreach($industries as $candidate){if((int)$candidate['id']!==$originId&&(int)$candidate['id']!==$baseId&&$isYard($candidate)&&$capacityAvailable($candidate)){$destination=$candidate;break;}}if(!$destination){$diagnostics[]='No explicit terminal, interchange, staging, or non-base yard destination for pull '.trim($car['reporting_marks'].' '.$car['road_number']).'.';continue;}$capacityDelta[(int)$destination['id']]=($capacityDelta[(int)$destination['id']]??0)+1;$capacityDelta[$originId]=($capacityDelta[$originId]??0)-1;$moves[]=ttPlannedCarMove($car,$destination,'PULL','Pull from '.$origin['industry_name'].' for '.$destination['industry_name']);$used[$carId]=true;}
    if(count($moves)<$max)$diagnostics[]='Requested up to '.$max.' cars; '.count($moves).' valid, unreserved, capacity-safe moves were available.';return ['moves'=>$moves,'diagnostics'=>$diagnostics];
}

function ttPlannedCarMove(array $car, array $destination, string $action, string $instruction, ?string $group = null, ?string $workLocation = null): array
{
    return ['equipment_id'=>(int)$car['id'],'movement_group'=>$group??bin2hex(random_bytes(12)),'reporting_marks_snapshot'=>$car['reporting_marks'],'road_number_snapshot'=>$car['road_number'],'equipment_type_snapshot'=>$car['equipment_type'],'service_snapshot'=>$car['operations_service'],'photo_filename_snapshot'=>$car['photo_filename']??null,'original_load_status'=>$car['load_status'],'planned_load_status'=>$car['load_status'],'origin_industry_id'=>(int)$car['current_industry_id'],'origin_name_snapshot'=>$car['origin_name'],'origin_track'=>$car['current_track'],'destination_industry_id'=>(int)$destination['id'],'destination_name_snapshot'=>$destination['industry_name'],'destination_track'=>'','action'=>$action,'instruction'=>$instruction,'work_location'=>$workLocation??($action==='PULL'?$car['origin_name']:$destination['industry_name'])];
}
