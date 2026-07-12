<?php
require_once dirname(__DIR__) . '/generator.php';

function expectTrue($condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
$industries = [
    ['id'=>1,'industry_name'=>'Cargill','industry_type'=>'Industry','ships_services'=>'grain','receives_services'=>'grain','track_capacity'=>1],
    ['id'=>2,'industry_name'=>'Main Yard','industry_type'=>'Yard','ships_services'=>'all','receives_services'=>'all','track_capacity'=>30],
    ['id'=>3,'industry_name'=>'Flour Mill','industry_type'=>'Industry','ships_services'=>'flour','receives_services'=>'grain','track_capacity'=>2],
    ['id'=>4,'industry_name'=>'Warehouse','industry_type'=>'Industry','ships_services'=>'goods','receives_services'=>'goods','track_capacity'=>1],
];
$car = static function($id,$location,$marks,$service,$load='Loaded') use ($industries) { foreach($industries as $i){if($i['id']===$location)$name=$i['industry_name'];}return ['id'=>$id,'reporting_marks'=>$marks,'road_number'=>(string)$id,'equipment_type'=>'Boxcar','operations_service'=>$service,'load_status'=>$load,'current_industry_id'=>$location,'current_track'=>'Track 1','origin_name'=>$name??'','photo_filename'=>null]; };
$cars = [$car(1,1,'TT','grain'),$car(2,3,'TT','grain','Empty'),$car(3,4,'TT','goods'),$car(4,2,'TT','grain')];
$assignment=['requested_car_count'=>20,'operating_base_industry_id'=>1,'end_industry_id'=>2];
$result=ttChooseOperationsMoves($assignment,$cars,$industries,[],[]);
expectTrue(count($result['moves'])<20,'Maximum target must not invent work.');
foreach($result['moves'] as $move){expectTrue($move['origin_industry_id']!==$move['destination_industry_id'],'Origin and destination must differ.');if($move['action']==='PULL'&&$move['origin_industry_id']!==1){expectTrue($move['destination_industry_id']!==1,'Operating base must not be the universal pull destination.');}}
$ids=array_column($result['moves'],'equipment_id');expectTrue(count($ids)===count(array_unique($ids)),'A car must not receive duplicate independent work.');
$reserved=ttChooseOperationsMoves($assignment,$cars,$industries,[],[2,3,4]);expectTrue(!in_array(2,array_column($reserved['moves'],'equipment_id'),true),'Reserved cars must be excluded.');
expectTrue(ttAssignmentSuffix(1)==='A'&&ttAssignmentSuffix(26)==='Z'&&ttAssignmentSuffix(27)==='AA','Assignment numbering must continue after Z.');

$routeAssignment=['requested_car_count'=>10,'operating_base_industry_id'=>2,'end_industry_id'=>2,'work_scope'=>'selected_route'];
$routeStops=[[
    'id'=>1,'industry_id'=>3,'sequence_number'=>1,
    'outbound_load_status'=>'Empty','inbound_load_status'=>'Loaded',
    'pull_destination_mode'=>'operating_base','pull_destination_industry_id'=>null,
    'replacement_source_mode'=>'operating_base','replacement_source_industry_id'=>null
]];
$routeResult=ttChooseOperationsMoves($routeAssignment,$cars,$industries,[],[],$routeStops);
expectTrue(count($routeResult['moves'])===2,'A feasible route exchange should create one Pull and one Spot.');
expectTrue($routeResult['moves'][0]['action']==='PULL'&&$routeResult['moves'][1]['action']==='SPOT','Route exchange must order Pull before Spot.');
expectTrue($routeResult['moves'][0]['work_location']==='Flour Mill'&&$routeResult['moves'][1]['work_location']==='Flour Mill','Paired work must stay under the same route stop.');
expectTrue($routeResult['moves'][0]['destination_industry_id']===2,'Configured operating base may be the explicit pull destination.');
expectTrue($routeResult['moves'][1]['destination_industry_id']===3,'Loaded replacement must be spotted at the served route stop.');
expectTrue(!in_array(3,array_column($routeResult['moves'],'equipment_id'),true),'Unrelated-location cars must not be pulled to reach the target.');
$fullIndustries=$industries;$fullIndustries[1]['track_capacity']=1;
$capacityCars=$cars;$capacityCars[]=$car(5,2,'ZZ','coal');
$capacityResult=ttChooseOperationsMoves($routeAssignment,$capacityCars,$fullIndustries,[],[],$routeStops);
expectTrue(count($capacityResult['moves'])===0,'A paired exchange must not overfill the configured pull destination.');

$workOrderSource=file_get_contents(dirname(__DIR__).'/work_order.php');
$completionSource=file_get_contents(dirname(__DIR__).'/completion.php');
$loadReviewSource=file_get_contents(dirname(__DIR__).'/load_status.php');
expectTrue(strpos($workOrderSource,'ttCompleteWorkOrderLoadNeutral')!==false,'Work-order completion must use the load-neutral completion path.');
expectTrue(strpos($completionSource,'UPDATE equipment SET current_industry_id=?,current_track=?,load_status=')===false,'Physical completion must not update load status.');
expectTrue(strpos($loadReviewSource,'owner_confirm')!==false,'Post-session load updates must require owner confirmation.');
echo "generator_test: OK\n";
