<?php
require_once dirname(__DIR__).'/generator.php';
function cutExpect($condition,string$message):void{if(!$condition)throw new RuntimeException($message);}
$industries=[
 ['id'=>1,'industry_name'=>'Main 1','industry_type'=>'Staging','ships_services'=>'all','receives_services'=>'all','track_capacity'=>50],
 ['id'=>2,'industry_name'=>'Cargill','industry_type'=>'Industry','ships_services'=>'grain','receives_services'=>'grain','track_capacity'=>8],
 ['id'=>3,'industry_name'=>'Flour Mill','industry_type'=>'Industry','ships_services'=>'grain','receives_services'=>'grain','track_capacity'=>20],
 ['id'=>4,'industry_name'=>'Engine Terminal','industry_type'=>'Yard','ships_services'=>'grain','receives_services'=>'grain','track_capacity'=>50],
 ['id'=>5,'industry_name'=>'Unrelated Warehouse','industry_type'=>'Industry','ships_services'=>'goods','receives_services'=>'goods','track_capacity'=>50],
];
$industryNames=array_column($industries,'industry_name','id');
$car=static fn(int$id,int$location,string$service='grain',string$load='Loaded'):array=>['id'=>$id,'reporting_marks'=>'CUT','road_number'=>(string)$id,'equipment_type'=>'Hopper','operations_service'=>$service,'load_status'=>$load,'current_industry_id'=>$location,'current_track'=>'Main 1','origin_name'=>$industryNames[$location],'photo_filename'=>null];
$startingIds=range(1,15);$cars=[];foreach($startingIds as$id)$cars[]=$car($id,1,$id===15?'coal':'grain');$cars[]=$car(100,4);$cars[]=$car(101,1);$cars[]=$car(102,5,'goods','Empty');foreach(range(200,204)as$id)$cars[]=$car($id,2,'grain','Empty');
$baseAssignment=['start_method'=>'prepared_cut','work_scope'=>'entire_railroad','requested_car_count'=>3,'operating_base_industry_id'=>4,'end_industry_id'=>4,'prepared_cut_industry_id'=>1];
$entire=ttChooseOperationsMoves($baseAssignment,$cars,$industries,$startingIds,[]);
cutExpect(count(array_filter($entire['moves'],static fn($m)=>$m['action']==='PULL'))===0,'Entire Railroad prepared-cut generation must create no unrelated Pulls.');
$entireIds=array_column($entire['moves'],'equipment_id');cutExpect(!in_array(100,$entireIds,true)&&!in_array(101,$entireIds,true)&&!in_array(102,$entireIds,true),'Cars at the base, cut location, or unrelated locations must not join the prepared train.');
cutExpect(count($entireIds)===count(array_unique($entireIds)),'Each prepared-cut car may receive at most one movement.');
cutExpect(count($entireIds)===14&&!in_array(15,$entireIds,true),'Every compatible starting car must be evaluated regardless of the additional-pickup limit.');
cutExpect(strpos(implode(' ',$entire['diagnostics']),'CUT 15')!==false,'An incompatible prepared-cut car must receive a named diagnostic.');
$byLocation=[];foreach($entire['moves']as$move)$byLocation[$move['work_location']][]=$move['equipment_id'];foreach($byLocation as$ids)cutExpect($ids===array_values(array_filter($startingIds,static fn($id)=>in_array($id,$ids,true))),'Prepared-cut order must be preserved within each Spot group.');
cutExpect(count($byLocation)===count(array_unique(array_column($entire['moves'],'work_location'))),'Each Entire Railroad destination must appear once.');
cutExpect(count(array_filter($entire['moves'],static fn($m)=>$m['destination_industry_id']===2))<=8,'Projected destination capacity must be respected.');

$routeStops=[
 ['id'=>1,'industry_id'=>2,'sequence_number'=>1,'exchange_enabled'=>0,'outbound_load_status'=>'Empty','inbound_load_status'=>'Loaded','pull_destination_mode'=>'operating_base','pull_destination_industry_id'=>null,'replacement_source_mode'=>'prepared_cut','replacement_source_industry_id'=>null],
 ['id'=>2,'industry_id'=>3,'sequence_number'=>2,'exchange_enabled'=>0,'outbound_load_status'=>'Empty','inbound_load_status'=>'Loaded','pull_destination_mode'=>'operating_base','pull_destination_industry_id'=>null,'replacement_source_mode'=>'prepared_cut','replacement_source_industry_id'=>null],
];
$routeAssignment=$baseAssignment;$routeAssignment['work_scope']='selected_route';$route=ttChooseOperationsMoves($routeAssignment,$cars,$industries,$startingIds,[],$routeStops);$routeIds=array_column($route['moves'],'equipment_id');$pulls=array_values(array_filter($route['moves'],static fn($m)=>$m['action']==='PULL'));$spots=array_values(array_filter($route['moves'],static fn($m)=>$m['action']==='SPOT'));
cutExpect(count($pulls)===3,'requested_car_count must limit only additional route pickups.');
cutExpect(count($spots)===14,'All compatible prepared-cut cars must still be evaluated for route Spots.');
cutExpect(!in_array(102,$routeIds,true),'Pulls must not originate from an unconfigured route location.');
foreach($spots as$move)cutExpect(in_array($move['destination_industry_id'],[2,3],true),'Selected Route Spots must use actual route stops only.');
$locations=[];foreach($route['moves']as$move){$locations[$move['work_location']][]=$move['action'];}cutExpect(array_keys($locations)===['Cargill','Flour Mill'],'Route work must follow configured stop order and visit each location once.');foreach($locations as$actions){$firstSpot=array_search('SPOT',$actions,true);if($firstSpot!==false)foreach(array_slice($actions,$firstSpot)as$action)cutExpect($action==='SPOT','Pulls must precede Spots at each route stop.');}
cutExpect(count($routeIds)===count(array_unique($routeIds)),'Prepared-cut and pickup cars must not receive duplicate work.');

$same=ttPreparedCutPickupInstruction(['operating_base_industry_id'=>1,'base_name'=>'Main 1'],['current_industry_id'=>1,'industry_name'=>'Main 1','current_track'=>'Track 2','cut_number'=>'CUT-00001','name'=>'Main cut','car_count'=>15]);cutExpect(strpos($same,'Track 2')!==false&&strpos($same,'CUT-00001')!==false,'Same-location pickup instruction must use the saved cut track and identity.');
$travel=ttPreparedCutPickupInstruction(['operating_base_industry_id'=>4,'base_name'=>'Engine Terminal'],['current_industry_id'=>1,'industry_name'=>'Main 1','current_track'=>'Track 2','cut_number'=>'CUT-00001','name'=>'Main cut','car_count'=>15]);cutExpect(strpos($travel,'Run light from Engine Terminal to Main 1 on Track 2')!==false,'Travel pickup instruction must use actual base, cut location, and cut track.');
$generate=file_get_contents(dirname(__DIR__).'/generate.php');$sessionForm=file_get_contents(dirname(__DIR__).'/session_edit.php');$editForm=file_get_contents(dirname(__DIR__).'/assignment_edit.php');$formJs=file_get_contents(dirname(__DIR__,2).'/assets/js/assignment-form.js');cutExpect(strpos($generate,'count($plan[\'moves\'])')!==false,'planned_move_count must count equipment movements only.');cutExpect(strpos($generate,"'instruction-pickup',null")!==false,'Pickup instruction must remain a non-equipment row.');cutExpect(strpos($generate,'$preparedCut[\'current_track\']')!==false,'Pickup row must use the Prepared Cut track.');cutExpect(strpos($generate,'Maximum additional pickups')!==false&&strpos($generate,'Starting train')!==false,'Generation page must show prepared-cut-specific details.');cutExpect(strpos($sessionForm,'tt-prepared-pickup-help')!==false&&strpos($editForm,'tt-prepared-pickup-help')!==false,'Create/Edit must explain the additional-pickup limit.');cutExpect(strpos($formJs,"limit.value='0'")!==false&&strpos($formJs,'!form.dataset.assignmentEdit')!==false,'New prepared-cut assignments must default additional pickups to zero without overwriting edits.');cutExpect(strpos($generate,'UPDATE equipment SET')===false,'Generation must not update equipment state.');
echo "prepared_cut_test: OK\n";
