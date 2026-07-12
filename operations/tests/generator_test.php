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
echo "generator_test: OK\n";
