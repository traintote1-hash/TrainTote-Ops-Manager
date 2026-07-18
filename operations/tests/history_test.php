<?php
require_once dirname(__DIR__).'/history_service.php';
function historyExpect($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}

$moves=[
    ['equipment_id'=>1,'actual_outcome'=>'moved','exception_reason_code'=>''],
    ['equipment_id'=>2,'actual_outcome'=>'not_moved','exception_reason_code'=>'track_blocked'],
    ['equipment_id'=>3,'actual_outcome'=>'moved','exception_reason_code'=>'moved_different_location'],
    ['equipment_id'=>null,'actual_outcome'=>'pending','exception_reason_code'=>''],
];
$counts=ttHistoryMoveCounts($moves);
historyExpect($counts===['completed'=>2,'exceptions'=>2],'Completed-move counts must include only successful persisted moves while exceptions come from persisted exception records.');
historyExpect(ttHistoryOutcomeLabel($moves[0])==='Moved as planned','Successful completed moves need a clear outcome.');
historyExpect(ttHistoryOutcomeLabel($moves[2])==='Moved to different location','Changed-destination exceptions need a clear outcome.');
historyExpect(ttHistoryExceptionType($moves[1])==='Not Moved'&&ttHistoryExceptionReason($moves[1])==='Track blocked','Not Moved exceptions must display their stored reason.');
historyExpect(ttHistoryRecordedValue(null)==='Not recorded'&&ttHistoryRecordedValue('')==='Not recorded','Missing older history values need a neutral fallback.');

$root=dirname(__DIR__);$list=file_get_contents($root.'/history.php');$detail=file_get_contents($root.'/history_view.php');$service=file_get_contents($root.'/history_service.php');$sidebar=file_get_contents(dirname($root).'/assets/components/sidebar.php');$css=file_get_contents(dirname($root).'/assets/css/operations.css');
historyExpect(strpos($list,"isset(\$_SESSION['user_id'])")!==false&&strpos($detail,"isset(\$_SESSION['user_id'])")!==false,'History pages must require authentication.');
historyExpect(strpos($service,"s.railroad_id=? AND s.status IN('completed','cancelled')")!==false,'History list access must be railroad-scoped and limited to closed sessions.');
historyExpect(strpos($service,"id=? AND railroad_id=? AND status IN('completed','cancelled')")!==false&&strpos($detail,"http_response_code(404)")!==false,'History detail must reject another railroad or a non-history session.');
historyExpect(strpos($service,"m.actual_outcome='moved'")!==false&&strpos($service,"m.exception_reason_code")!==false,'List counts must use persisted move outcomes and exception records.');
historyExpect(strpos($detail,"sl2.railroad_id=a.railroad_id")!==false&&strpos($detail,"m.railroad_id=?")!==false,'Detail work orders and moves must remain railroad-scoped.');
historyExpect(strpos($detail,'Actual Outcome')!==false&&strpos($detail,'Exception')!==false&&strpos($detail,'exception_notes')!==false,'Detail must render actual outcomes and exception notes.');
historyExpect(strpos($detail,'Print / Reprint')!==false&&strpos($detail,'name="action"')===false,'Detail must be read-only while linking the existing print view.');
historyExpect(strpos($sidebar,'/operations/history.php')!==false,'Operations navigation must include the dedicated Session History page.');
historyExpect(strpos($css,'.tt-history-table{min-width:1040px}')!==false&&strpos($css,'@media(max-width:576px){.tt-history-stats')!==false,'History tables and summary statistics need responsive overflow and phone layouts.');
echo "history_test: OK\n";
