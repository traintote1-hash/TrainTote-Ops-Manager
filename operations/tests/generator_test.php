<?php
require_once dirname(__DIR__) . '/generator.php';

function expectTrue($condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
$industries = [
    ['id'=>1,'industry_name'=>'Cargill','industry_type'=>'Industry','ships_services'=>'grain','receives_services'=>'grain','track_capacity'=>1],
    ['id'=>2,'industry_name'=>'Main Yard','industry_type'=>'Yard','ships_services'=>'all','receives_services'=>'all','track_capacity'=>30],
    ['id'=>3,'industry_name'=>'Flour Mill','industry_type'=>'Industry','ships_services'=>'flour','receives_services'=>'grain','track_capacity'=>2],
    ['id'=>4,'industry_name'=>'Warehouse','industry_type'=>'Industry','ships_services'=>'goods','receives_services'=>'goods','track_capacity'=>1],
    ['id'=>5,'industry_name'=>'Main 1 Staging','industry_type'=>'Staging','ships_services'=>'all','receives_services'=>'all','track_capacity'=>10],
];
$car = static function($id,$location,$marks,$service,$load='Loaded') use ($industries) { foreach($industries as $i){if($i['id']===$location)$name=$i['industry_name'];}return ['id'=>$id,'reporting_marks'=>$marks,'road_number'=>(string)$id,'equipment_type'=>'Boxcar','operations_service'=>$service,'load_status'=>$load,'current_industry_id'=>$location,'current_track'=>'Track 1','origin_name'=>$name??'','photo_filename'=>null]; };
$cars = [$car(1,1,'TT','grain'),$car(2,3,'TT','grain','Empty'),$car(3,4,'TT','goods'),$car(4,2,'TT','grain'),$car(6,5,'ARMN','grain')];
$assignment=['requested_car_count'=>20,'operating_base_industry_id'=>1,'end_industry_id'=>2];
$result=ttChooseOperationsMoves($assignment,$cars,$industries,[],[]);
expectTrue(count($result['moves'])<20,'Maximum target must not invent work.');
foreach($result['moves'] as $move){expectTrue($move['origin_industry_id']!==$move['destination_industry_id'],'Origin and destination must differ.');if($move['action']==='PULL'&&$move['origin_industry_id']!==1){expectTrue($move['destination_industry_id']!==1,'Operating base must not be the universal pull destination.');}}
$ids=array_column($result['moves'],'equipment_id');expectTrue(count($ids)===count(array_unique($ids)),'A car must not receive duplicate independent work.');
$reserved=ttChooseOperationsMoves($assignment,$cars,$industries,[],[2,3,4]);expectTrue(!in_array(2,array_column($reserved['moves'],'equipment_id'),true),'Reserved cars must be excluded.');
expectTrue(ttAssignmentSuffix(1)==='A'&&ttAssignmentSuffix(26)==='Z'&&ttAssignmentSuffix(27)==='AA','Assignment numbering must continue after Z.');

$localIndustries=[
    ['id'=>20,'industry_name'=>'Team Track','industry_type'=>'Yard','ships_services'=>'all','receives_services'=>'all','track_capacity'=>3],
    ['id'=>21,'industry_name'=>'Local Main 1','industry_type'=>'Customer','ships_services'=>'','receives_services'=>'grain','track_capacity'=>1],
    ['id'=>22,'industry_name'=>'Local Main 2','industry_type'=>'Customer','ships_services'=>'','receives_services'=>'goods','track_capacity'=>1],
    ['id'=>23,'industry_name'=>'Cement Sam','industry_type'=>'Customer','ships_services'=>'','receives_services'=>'sand','track_capacity'=>1],
];
$localCar=static function(int$id,int$location,string$service,string$load)use($localIndustries):array{foreach($localIndustries as$industry)if((int)$industry['id']===$location)$name=$industry['industry_name'];return ['id'=>$id,'reporting_marks'=>'LOCAL','road_number'=>(string)$id,'equipment_type'=>'Boxcar','operations_service'=>$service,'load_status'=>$load,'current_industry_id'=>$location,'current_track'=>'Track 1','origin_name'=>$name??'','photo_filename'=>null];};
$localCars=[$localCar(201,20,'grain','Loaded'),$localCar(202,20,'goods','Loaded'),$localCar(203,21,'grain','Empty'),$localCar(204,22,'goods','Empty'),$localCar(205,23,'sand','Empty'),$localCar(206,20,'sand','Empty')];
$localAssignment=['requested_car_count'=>10,'operating_base_industry_id'=>20,'end_industry_id'=>null,'operating_pattern'=>'local_turn','work_scope'=>'entire_railroad'];
$localResult=ttChooseOperationsMoves($localAssignment,$localCars,$localIndustries,[],[]);
expectTrue(count($localResult['moves'])===4,'An entire-railroad Local Turn must create one Pull and one Spot for every available opposite-load exchange pair.');
foreach(array_chunk($localResult['moves'],2)as$exchange){expectTrue($exchange[0]['action']==='PULL'&&$exchange[1]['action']==='SPOT','Each Local Turn customer exchange must list the pickup before the replacement spot.');expectTrue($exchange[0]['destination_industry_id']===20,'Local Turn pickups must return to the operating base.');expectTrue($exchange[0]['movement_group']===$exchange[1]['movement_group'],'A Local Turn pickup and replacement must remain paired.');}
expectTrue(!in_array(205,array_column($localResult['moves'],'equipment_id'),true),'A customer car without another compatible opposite-load replacement must remain at the customer instead of being dumped at the operating base.');
expectTrue(!in_array(206,array_column($localResult['moves'],'equipment_id'),true),'A same-load base car must not be treated as a replacement; unmatched base cars remain in place.');

$routeAssignment=['requested_car_count'=>10,'operating_base_industry_id'=>2,'end_industry_id'=>2,'work_scope'=>'selected_route'];
$routeStops=[[
    'id'=>1,'industry_id'=>3,'sequence_number'=>1,
    'exchange_enabled'=>0,
    'outbound_load_status'=>'Empty','inbound_load_status'=>'Loaded',
    'pull_destination_mode'=>'operating_base','pull_destination_industry_id'=>null,
    'replacement_source_mode'=>'operating_base','replacement_source_industry_id'=>null
]];
$routeResult=ttChooseOperationsMoves($routeAssignment,$cars,$industries,[],[],$routeStops);
expectTrue(count($routeResult['moves'])===2,'Ordinary route work should allow independent Pull and Spot rows.');
expectTrue($routeResult['moves'][0]['action']==='PULL'&&$routeResult['moves'][1]['action']==='SPOT','Normal route work must group Pulls before Spots.');
expectTrue($routeResult['moves'][0]['movement_group']!==$routeResult['moves'][1]['movement_group'],'Exchange-off route work must not create a paired movement group.');
expectTrue($routeResult['moves'][0]['work_location']==='Flour Mill'&&$routeResult['moves'][1]['work_location']==='Flour Mill','Paired work must stay under the same route stop.');
expectTrue($routeResult['moves'][0]['destination_industry_id']===2,'Configured operating base may be the explicit pull destination.');
expectTrue($routeResult['moves'][1]['destination_industry_id']===3,'Loaded replacement must be spotted at the served route stop.');
expectTrue(!in_array(3,array_column($routeResult['moves'],'equipment_id'),true),'Unrelated-location cars must not be pulled to reach the target.');

$pullOnlyStops=$routeStops;$pullOnlyStops[0]['replacement_source_mode']='selected_location';$pullOnlyStops[0]['replacement_source_industry_id']=4;
$pullOnly=ttChooseOperationsMoves($routeAssignment,$cars,$industries,[],[],$pullOnlyStops);
expectTrue(count($pullOnly['moves'])===1&&$pullOnly['moves'][0]['action']==='PULL','A normal route stop must support Pull-only work.');
$spotOnlyCars=[$car(4,2,'TT','grain')];
$spotOnly=ttChooseOperationsMoves($routeAssignment,$spotOnlyCars,$industries,[],[],$routeStops);
expectTrue(count($spotOnly['moves'])===1&&$spotOnly['moves'][0]['action']==='SPOT','A normal route stop must support Spot-only work.');

$exchangeStops=$routeStops;$exchangeStops[0]['exchange_enabled']=1;
$exchangeResult=ttChooseOperationsMoves($routeAssignment,$cars,$industries,[],[],$exchangeStops);
expectTrue(count($exchangeResult['moves'])===2,'An enabled compatible exchange should create one Pull and one Spot.');
expectTrue($exchangeResult['moves'][0]['action']==='PULL'&&$exchangeResult['moves'][1]['action']==='SPOT','Enabled exchange must Pull before Spot.');
expectTrue($exchangeResult['moves'][0]['movement_group']===$exchangeResult['moves'][1]['movement_group'],'Enabled exchange rows must share a movement group.');

$supportStops=$exchangeStops;$supportStops[0]['pull_destination_mode']='selected_location';$supportStops[0]['pull_destination_industry_id']=5;$supportStops[0]['replacement_source_mode']='selected_location';$supportStops[0]['replacement_source_industry_id']=5;
$supportResult=ttChooseOperationsMoves($routeAssignment,$cars,$industries,[],[],$supportStops);
expectTrue(count($supportResult['moves'])===2,'Explicit support location outside the numbered route must be usable.');
expectTrue($supportResult['moves'][0]['destination_industry_id']===5&&$supportResult['moves'][1]['origin_industry_id']===5,'Pull destination and replacement source must use configured support location.');
expectTrue(!in_array(3,array_column($supportResult['moves'],'equipment_id'),true),'Unrelated-location cars must remain excluded when support locations are added.');

$areaStops=$routeStops;$areaStops[0]['operating_area']='North';$areaStops[]=$areaStops[0];$areaStops[1]['id']=2;$areaStops[1]['industry_id']=1;$areaStops[1]['sequence_number']=1;
$areaResult=ttChooseOperationsMoves($routeAssignment,$cars,$industries,[],[],$areaStops);
$areaOrigins=array_column($areaResult['moves'],'origin_industry_id');
expectTrue(in_array(1,$areaOrigins,true)&&in_array(3,$areaOrigins,true),'Industries sharing one Operating Area must all be eligible in that area.');
expectTrue(!in_array(4,$areaOrigins,true),'Industries outside the selected Operating Areas and operating base must remain excluded.');
expectTrue(strpos(implode(' ',$areaResult['diagnostics']),'Selected Route Areas [North]')!==false,'No-work diagnostics must identify the selected Route Areas.');

$emptyRouteRejected=false;try{ttChooseOperationsMoves($routeAssignment,$cars,$industries,[],[],[]);}catch(RuntimeException $e){$emptyRouteRejected=strpos($e->getMessage(),'selected Operating Areas')!==false;}
expectTrue($emptyRouteRejected,'Selected Route with zero stops must be rejected instead of broadening to Entire Railroad.');
$fullIndustries=$industries;$fullIndustries[1]['track_capacity']=1;
$capacityCars=$cars;$capacityCars[]=$car(5,2,'ZZ','coal');
$capacityResult=ttChooseOperationsMoves($routeAssignment,$capacityCars,$fullIndustries,[],[],$exchangeStops);
expectTrue(count($capacityResult['moves'])===0,'A paired exchange must not overfill the configured pull destination.');

$destinationIndustries = [
    ['id'=>101,'industry_name'=>'Yard','industry_type'=>'Industry','ships_services'=>'','receives_services'=>'','track_capacity'=>20],
    ['id'=>102,'industry_name'=>'Scrap Yard','industry_type'=>'Customer','ships_services'=>'Scrap Metal','receives_services'=>'Scrap Metal','track_capacity'=>5],
    ['id'=>103,'industry_name'=>'ABC Lumber','industry_type'=>'Customer','ships_services'=>'Lumber','receives_services'=>'Lumber','track_capacity'=>5],
    ['id'=>104,'industry_name'=>'Full Lumber Dock','industry_type'=>'Customer','ships_services'=>'Lumber','receives_services'=>'Lumber','track_capacity'=>1],
    ['id'=>105,'industry_name'=>'Furniture Factory','industry_type'=>'Customer','ships_services'=>'Furniture','receives_services'=>'Lumber','track_capacity'=>5],
];
$lumberCar = ['id'=>101,'reporting_marks'=>'DWC','road_number'=>'626426','equipment_type'=>'Centerbeam Flatcar','operations_service'=>'Lumber','load_status'=>'Loaded','current_industry_id'=>103,'current_track'=>'Track 1','origin_name'=>'ABC Lumber','photo_filename'=>null];
$fullDockCar = ['id'=>102,'reporting_marks'=>'TT','road_number'=>'9001','equipment_type'=>'Boxcar','operations_service'=>'','load_status'=>'Empty','current_industry_id'=>104,'current_track'=>'Track 1','origin_name'=>'Full Lumber Dock','photo_filename'=>null];
$destinationAssignment = ['requested_car_count'=>1,'operating_base_industry_id'=>101,'end_industry_id'=>101,'work_scope'=>'selected_route'];
$routeStop = static function(int $id,int $industryId,int $sequence,string $mode,?int $destinationId=null,string $area='') : array {
    return ['id'=>$id,'industry_id'=>$industryId,'sequence_number'=>$sequence,'operating_area'=>$area,'exchange_enabled'=>0,'outbound_load_status'=>'Any','inbound_load_status'=>'Any','pull_destination_mode'=>$mode,'pull_destination_industry_id'=>$destinationId,'replacement_source_mode'=>'selected_location','replacement_source_industry_id'=>null];
};
$yardRoute = [
    $routeStop(101,102,1,'yard',null,'East'),
    $routeStop(102,103,2,'yard',null,'North'),
];
$yardResult = ttChooseOperationsMoves($destinationAssignment,[$lumberCar],$destinationIndustries,[],[],$yardRoute);
expectTrue(count($yardResult['moves'])===1&&$yardResult['moves'][0]['destination_industry_id']===101,'Default yard pulls must return a Lumber centerbeam from ABC Lumber to the assignment operating base.');
expectTrue($yardResult['moves'][0]['destination_industry_id']!==102,'A Scrap Yard customer must never be selected as a railroad yard by name.');
expectTrue(ttIndustrySupportRole($destinationIndustries[1])===null,'A customer containing Yard in its name must not be classified as a support yard.');

$operatingBaseRoute = $yardRoute;
$operatingBaseRoute[1]['pull_destination_mode']='operating_base';
$operatingBaseRoute[1]['pull_destination_industry_id']=102;
$operatingBaseResult = ttChooseOperationsMoves($destinationAssignment,[$lumberCar],$destinationIndustries,[],[],$operatingBaseRoute);
expectTrue(count($operatingBaseResult['moves'])===1&&$operatingBaseResult['moves'][0]['destination_industry_id']===101,'Operating-base mode must ignore another configured destination and use the explicit assignment base even when its Industry Type is incomplete.');

$selectedCustomerRoute = $yardRoute;
$selectedCustomerRoute[1]['pull_destination_mode']='selected_location';
$selectedCustomerRoute[1]['pull_destination_industry_id']=102;
$selectedCustomerResult = ttChooseOperationsMoves($destinationAssignment,[$lumberCar],$destinationIndustries,[],[],$selectedCustomerRoute);
expectTrue(count($selectedCustomerResult['moves'])===0,'An explicitly selected customer destination must support the pulled car service.');

$compatibleRoute = [
    $routeStop(201,103,1,'next_compatible',null,'North'),
    $routeStop(202,102,2,'operating_base',null,'East'),
    $routeStop(203,104,3,'operating_base',null,'South'),
    $routeStop(204,105,4,'operating_base',null,'South'),
];
$compatibleResult = ttChooseOperationsMoves($destinationAssignment,[$lumberCar,$fullDockCar],$destinationIndustries,[],[],$compatibleRoute);
expectTrue(count($compatibleResult['moves'])===1&&$compatibleResult['moves'][0]['destination_industry_id']===105,'Next-compatible pulls must skip an incompatible first industry and a full compatible industry before selecting a later compatible, capacity-safe industry.');

$workOrderSource=file_get_contents(dirname(__DIR__).'/work_order.php');
$completionSource=file_get_contents(dirname(__DIR__).'/completion.php');
$loadReviewSource=file_get_contents(dirname(__DIR__).'/load_status.php');
$generateSource=file_get_contents(dirname(__DIR__).'/generate.php');
$printSource=file_get_contents(dirname(__DIR__).'/print_order.php');
expectTrue(strpos($workOrderSource,'ttCompleteWorkOrderLoadNeutral')!==false,'Work-order completion must use the load-neutral completion path.');
expectTrue(strpos($completionSource,'UPDATE equipment SET current_industry_id=?,current_track=?,load_status=')===false,'Physical completion must not update load status.');
expectTrue(strpos($completionSource,'equipment_id IS NOT NULL')!==false,'LOCATION headings must not be processed as equipment moves.');
expectTrue(strpos($generateSource,'count($plan[\'moves\'])')!==false,'LOCATION headings must not count toward planned car moves.');
expectTrue(strpos($workOrderSource,'tt-location-heading')!==false&&strpos($workOrderSource,'colspan=')!==false,'Screen LOCATION rows must render as full-width headings.');
expectTrue(strpos($printSource,'tt-location-heading')!==false&&strpos($printSource,'colspan="8"')!==false,'Print LOCATION rows must render as full-width headings.');
expectTrue(strpos($loadReviewSource,'owner_confirm')!==false,'Post-session load updates must require owner confirmation.');
expectTrue(strpos($loadReviewSource,'ttOperationsRequireRailroadOwner')!==false,'Post-session load updates must require actual owner authorization.');
$routeEditorSource=file_get_contents(dirname(__DIR__,2).'/jobs/route.php');
$jobListSource=file_get_contents(dirname(__DIR__,2).'/jobs/list.php');
$migrationSource=file_get_contents(dirname(__DIR__,2).'/database/migrations/20260714_add_job_route_operating_areas.sql');
expectTrue(strpos($routeEditorSource,"TRIM(location) operating_area")!==false&&strpos($routeEditorSource,'COUNT(*) industry_count')!==false,'Route editor must derive unique Operating Areas and active-industry counts from Industry Location.');
expectTrue(strpos($routeEditorSource,'operating_areas[]')!==false&&strpos($routeEditorSource,'Move Up')!==false,'Route editor must support multi-select and ordered areas.');
expectTrue(strpos($routeEditorSource,'Edit Job Title —')!==false&&strpos($routeEditorSource,'save_job')!==false,'The dedicated editor must save general Job Title settings on the route-management page.');
expectTrue(strpos($routeEditorSource,'Default Operating Pattern')!==false&&strpos($routeEditorSource,'Template Status')!==false&&strpos($routeEditorSource,'Work Scope')!==false&&strpos($routeEditorSource,'Template Description')!==false,'The complete editor must contain every general Job Title field.');
expectTrue(strpos($routeEditorSource,'id="operating-areas"')!==false&&strpos($routeEditorSource,'Selected Default Operating Areas')!==false&&strpos($routeEditorSource,'Area switching rules')!==false,'The complete editor must contain the Operating Area selector, order, and rules.');
expectTrue(strpos($routeEditorSource,'Save Job Title')!==false&&strpos($routeEditorSource,'Back to Job Titles')!==false,'The complete editor must provide clear save and return actions.');
expectTrue(strpos($routeEditorSource,'Job Title updated.')!==false&&strpos($routeEditorSource,"header('Location: list.php")===false,'Saving general Job Title fields must remain on the complete editor and show success.');
expectTrue(strpos($jobListSource,"header('Location: route.php?id='")!==false,'Legacy list edit links must redirect to the complete editor.');
expectTrue(strpos($jobListSource,'btn btn-sm btn-primary')!==false&&strpos($jobListSource,'href="route.php?id=')!==false&&strpos($jobListSource,'>Edit Route</a>')===false,'The Job Titles list must expose one complete Edit action without a separate Edit Route button.');
expectTrue(strpos($generateSource,'#operating-areas">Edit Job Title Route</a>')!==false,'The Generate shortcut must open the complete editor at its Operating Areas section.');
expectTrue(strpos($generateSource,'TRIM(i.location)=TRIM(jrs.operating_area)')!==false&&strpos($generateSource,'i.active=1')!==false,'Generation must expand saved Route Areas to active industries only.');
expectTrue(strpos($migrationSource,'ROW_NUMBER() OVER')!==false&&strpos($migrationSource,'ORDER BY jrs.sequence_number, jrs.id')!==false&&strpos($migrationSource,'AND i.active = 1')!==false&&strpos($migrationSource,'uq_job_route_stop_area')!==false,'Migration must seed the earliest ordered active-industry legacy row for each Location and enforce one active area per Job Title.');
expectTrue(strpos($migrationSource,'DELETE duplicate_stop')===false&&strpos($migrationSource,'DROP TEMPORARY TABLE job_route_area_seed')!==false,'Migration must preserve duplicate legacy route rows and their switching rules.');
expectTrue(strpos($generateSource,'legacy_i.location legacy_location')!==false,'Generation must ignore preserved individual-industry rows after Operating Areas are seeded.');
expectTrue(strpos($loadReviewSource,'ttOperationsRequireCsrf')!==false,'Post-session load updates must remain CSRF protected.');
echo "generator_test: OK\n";
