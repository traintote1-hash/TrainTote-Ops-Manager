<?php
session_start();header('Content-Type: application/json');require_once '../config/database.php';require_once 'lib.php';require_once 'dispatcher_service.php';
if(!isset($_SESSION['user_id'])){http_response_code(401);echo json_encode(['error'=>'Authentication required']);exit;}
try{
 $userId=(int)$_SESSION['user_id'];$railroad=ttDispatcherAccessRailroad($pdo,$userId);ttDispatcherRequireAccess($railroad);$railroadId=(int)$railroad['id'];$sessionId=(int)($_GET['session_id']??$_POST['session_id']??0);$session=ttDispatcherSession($pdo,$sessionId,$railroadId);
 if(!ttDispatcherEffectiveEnabled($railroad,$session)){http_response_code(404);throw new RuntimeException('Dispatcher is disabled for this operating session.');}
 if($_SERVER['REQUEST_METHOD']==='POST'){ttOperationsRequireCsrf();ttDispatcherUpdateAssignment($pdo,(int)($_POST['assignment_id']??0),$sessionId,$railroadId,$userId,$_POST);$session=ttDispatcherSession($pdo,$sessionId,$railroadId);}
 echo json_encode(['session_status'=>$session['status'],'polling'=>$session['status']==='in_progress','assignments'=>ttDispatcherAssignments($pdo,$sessionId,$railroadId)]);
}catch(Throwable$e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();if(http_response_code()<400)http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);}
