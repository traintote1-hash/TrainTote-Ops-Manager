<?php
session_start();header('Content-Type: application/json');require_once '../config/database.php';require_once 'lib.php';
if(!isset($_SESSION['user_id'])){http_response_code(401);echo json_encode(['error'=>'Authentication required']);exit;}
try{
    ttOperationsRequireCsrf();$railroad=ttOperationsRailroad($pdo,(int)$_SESSION['user_id']);$railroadId=(int)$railroad['id'];$moveId=(int)($_POST['move_id']??0);$complete=($_POST['complete']??'0')==='1'?1:0;
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('SELECT sl.id switch_list_id,sl.assignment_id,sl.status list_status,a.status assignment_status,s.status session_status FROM operation_switch_list_moves m JOIN operation_switch_lists sl ON sl.id=m.switch_list_id AND sl.railroad_id=m.railroad_id JOIN operation_assignments a ON a.id=sl.assignment_id AND a.railroad_id=sl.railroad_id JOIN operating_sessions s ON s.id=sl.session_id AND s.railroad_id=sl.railroad_id WHERE m.id=? AND m.railroad_id=? FOR UPDATE');$stmt->execute([$moveId,$railroadId]);$state=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$state||$state['session_status']!=='in_progress'||!in_array($state['assignment_status'],['ready','in_progress','needs_review'],true)||!in_array($state['list_status'],['approved','in_progress'],true))throw new RuntimeException('Switch-list progress can only be changed while the operating session is Active.');
    $pdo->prepare('UPDATE operation_switch_list_moves SET progress_complete=?,progress_updated_at=NOW() WHERE id=? AND railroad_id=?')->execute([$complete,$moveId,$railroadId]);
    $pdo->prepare("UPDATE operation_switch_lists SET status='in_progress',started_at=IFNULL(started_at,NOW()) WHERE id=? AND railroad_id=? AND status='approved'")->execute([(int)$state['switch_list_id'],$railroadId]);
    $pdo->prepare("UPDATE operation_assignments SET status='in_progress',started_at=IFNULL(started_at,NOW()) WHERE id=? AND railroad_id=? AND status='ready'")->execute([(int)$state['assignment_id'],$railroadId]);
    $pdo->commit();echo json_encode(['ok'=>true,'complete'=>(bool)$complete]);
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);}
