<?php
require_once dirname(__DIR__).'/fast_clock_service.php';
function fastClockExpect($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}

fastClockExpect(ttFastClockRatios()===[1,2,4,6,8,10],'Only the requested Fast Clock ratios may be configured.');
fastClockExpect(ttFastClockNormalizeStart('08:05')===485&&ttFastClockFormatMinutes(485)==='08:05','Configured model time must round-trip through storage.');
foreach(ttFastClockRatios() as $ratio){$clock=['fast_clock_base_model_seconds'=>28800,'fast_clock_running'=>1,'fast_clock_base_real_epoch'=>1000,'fast_clock_ratio'=>$ratio];fastClockExpect(ttFastClockModelSeconds($clock,1060)===28800+60*$ratio,"Ratio $ratio:1 must advance model time accurately.");}
$wrap=['fast_clock_base_model_seconds'=>86390,'fast_clock_running'=>1,'fast_clock_base_real_epoch'=>1000,'fast_clock_ratio'=>10];
fastClockExpect(ttFastClockModelSeconds($wrap,1002)===10,'Fast Clock must wrap cleanly at midnight.');
$paused=['fast_clock_base_model_seconds'=>12345,'fast_clock_running'=>0,'fast_clock_base_real_epoch'=>1000,'fast_clock_ratio'=>10];
fastClockExpect(ttFastClockModelSeconds($paused,9999)===12345,'Paused time must remain fixed.');

$root=dirname(__DIR__);$endpoint=file_get_contents($root.'/fast_clock.php');$widget=file_get_contents($root.'/fast_clock_widget.php');$session=file_get_contents($root.'/session_edit.php');$history=file_get_contents($root.'/history_view.php');$js=file_get_contents(dirname($root).'/assets/js/fast-clock.js');$migration=file_get_contents(dirname($root).'/database/migrations/20260718_add_operations_fast_clock.sql');
fastClockExpect(strpos($endpoint,'ttOperationsRequireCsrf')!==false&&strpos($endpoint,'ttOperationsRequireRailroadOwner')!==false,'Fast Clock mutations must require CSRF and railroad-owner authorization.');
fastClockExpect(strpos($endpoint,'ttLoadFastClock($pdo, $sessionId, $railroadId, true)')!==false,'Fast Clock mutations must lock a railroad-scoped session row.');
fastClockExpect(strpos($endpoint,"['draft', 'ready']")!==false&&strpos($endpoint,'fast_clock_started_at')!==false,'Ratio and configuration changes must stop once the clock starts.');
fastClockExpect(strpos($widget,"!empty(\$fastClock['fast_clock_enabled'])")!==false,'Disabled sessions must render no clock widget or synchronization script.');
fastClockExpect(strpos($widget,'ttOperationsIsRailroadOwner')!==false&&strpos($widget,'$fastClockCanControl')!==false,'Operators must see clock state without receiving owner controls.');
fastClockExpect(strpos($js,'15000')!==false&&strpos($js,'document.hidden')!==false,'Enabled clock pages need lightweight visibility-aware synchronization.');
fastClockExpect(strpos($session,"action==='configure_fast_clock'")!==false&&strpos($session,'ttFreezeFastClock')!==false,'Session Builder must configure the clock and freeze it when the session closes.');
fastClockExpect(strpos($history,'Fast Clock')!==false&&strpos($history,'fast_clock_start_minutes')!==false&&strpos($history,'fast_clock_ratio')!==false,'Session History must show retained Fast Clock settings.');
fastClockExpect(strpos($migration,'fast_clock_base_model_seconds')!==false&&strpos($migration,'fast_clock_last_sync_at')!==false,'Migration must persist authoritative clock state.');
echo "fast_clock_test: OK\n";
