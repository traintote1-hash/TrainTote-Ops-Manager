<?php
require_once dirname(__DIR__).'/completion.php';

function completionExpect($condition,string$message):void{if(!$condition)throw new RuntimeException($message);}

$move=static fn(int$id,int$equipmentId,int$origin,string$originTrack,int$destination,string$destinationTrack,string$marks):array=>['id'=>$id,'equipment_id'=>$equipmentId,'reporting_marks_snapshot'=>$marks,'road_number_snapshot'=>(string)$equipmentId,'origin_industry_id'=>$origin,'origin_track'=>$originTrack,'destination_industry_id'=>$destination,'destination_track'=>$destinationTrack];
$moves=[$move(11,1,10,'A',30,'C','TTX'),$move(12,2,20,'B',31,'D','BOX')];
$equipment=[1=>['id'=>1,'current_industry_id'=>10,'current_track'=>'A','load_status'=>'Loaded'],2=>['id'=>2,'current_industry_id'=>20,'current_track'=>'B','load_status'=>'Empty']];
$industryExists=static fn(int$id):bool=>in_array($id,[30,31,40],true);

$default=ttPrepareWorkOrderResults($moves,$equipment,[],$industryExists);
completionExpect($default['moved_as_planned']===2&&$default['not_moved']===0,'No submitted outcomes must complete every move as planned.');
completionExpect($default['results'][0]['actual_industry_id']===30&&$default['results'][0]['actual_track']==='C'&&$default['results'][0]['update_equipment'],'Default completion must use the planned destination and track.');

$unchecked=ttPrepareWorkOrderResults($moves,$equipment,['progress_complete'=>['11'=>'0','12'=>'0']],$industryExists);
completionExpect($unchecked['moved_as_planned']===2,'Progress checkboxes must not be required or alter completion outcomes.');

$notMoved=ttPrepareWorkOrderResults($moves,$equipment,['exception_outcome'=>['11'=>'not_moved'],'reason'=>['11'=>'track_blocked']],$industryExists);
completionExpect($notMoved['results'][0]['outcome']==='not_moved'&&!$notMoved['results'][0]['update_equipment'],'A Not Moved exception must not update equipment.');
completionExpect($notMoved['results'][0]['actual_industry_id']===10&&$notMoved['results'][0]['actual_track']==='A','A Not Moved exception must preserve the car current location and track.');

$different=ttPrepareWorkOrderResults([$moves[0]],$equipment,['exception_outcome'=>['11'=>'moved_elsewhere'],'actual_industry_id'=>['11'=>40],'actual_track'=>['11'=>'Siding 2'],'exception_notes'=>['11'=>'Dispatcher changed the setout.']],$industryExists);
completionExpect($different['moved_different']===1&&$different['results'][0]['actual_industry_id']===40&&$different['results'][0]['actual_track']==='Siding 2','A different-destination exception must use its submitted destination and track.');
completionExpect($different['results'][0]['reason']==='moved_different_location'&&$different['results'][0]['notes']!=='','A different-destination exception must preserve a note explaining the change.');

$beforeInvalid=$equipment;$invalidRejected=false;try{ttPrepareWorkOrderResults($moves,$equipment,['exception_outcome'=>['12'=>'not_moved']],$industryExists);}catch(RuntimeException$e){$invalidRejected=strpos($e->getMessage(),'valid exception reason')!==false;}
completionExpect($invalidRejected&&$equipment===$beforeInvalid,'Invalid exception data must fail preflight without changing equipment state.');

$stale=$equipment;$stale[1]['current_industry_id']=99;$beforeStale=$stale;$staleRejected=false;try{ttPrepareWorkOrderResults($moves,$stale,[],$industryExists);}catch(RuntimeException$e){$staleRejected=strpos($e->getMessage(),'Stale physical state detected for TTX 1')!==false;}
completionExpect($staleRejected&&$stale===$beforeStale,'Stale physical state must stop preflight without applying partial results.');

$sequentialMoves=[$move(21,1,10,'A',30,'C','TTX'),$move(22,1,30,'C',40,'E','TTX')];$sequential=ttPrepareWorkOrderResults($sequentialMoves,$equipment,[],$industryExists);
completionExpect($sequential['moved_as_planned']===2&&$sequential['results'][1]['actual_industry_id']===40,'Sequential planned moves for the same car must validate against the preceding planned result.');

$completionSource=file_get_contents(dirname(__DIR__).'/completion.php');$workOrderSource=file_get_contents(dirname(__DIR__).'/work_order.php');
completionExpect(strpos($completionSource,'$plan=ttPrepareWorkOrderResults')<strpos($completionSource,"foreach(\$plan['results']"),'Every result must pass preflight before any equipment update loop runs.');
completionExpect(strpos($completionSource,'progress_complete=1')!==false&&strpos($completionSource,"\$post['progress_complete']")===false,'Completion must mark moves complete without reading progress checkbox state.');
completionExpect(strpos($workOrderSource,'>Complete Work Order</button>')!==false&&strpos($workOrderSource,'Moves are accepted as planned unless an exception is added.')!==false,'The primary closeout action must clearly accept planned moves by default.');
completionExpect(strpos($workOrderSource,'tt-move-exception')!==false&&strpos($workOrderSource,'Add Exception')!==false&&strpos($workOrderSource,'Moved to a Different Location')!==false,'Per-car closeout controls must be collapsed and exception-focused.');
completionExpect(strpos($workOrderSource,'planned moves will be applied.')!==false&&strpos($workOrderSource,'will be recorded.')!==false,'Final submission must confirm planned-move and exception counts.');
completionExpect(strpos($workOrderSource,'name="outcome[')===false,'The work order must not require an always-visible result selection for every car.');

echo "completion_test: OK\n";
