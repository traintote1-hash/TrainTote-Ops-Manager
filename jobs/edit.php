<?php
session_start(); require_once '../config/database.php'; require_once '../operations/lib.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$railroad=ttOperationsRailroad($pdo,(int)$_SESSION['user_id']); $id=(int)($_GET['id']??0); $types=ttJobTypes(); $error='';
$stmt=$pdo->prepare('SELECT * FROM jobs WHERE id=? AND railroad_id=?'); $stmt->execute([$id,(int)$railroad['id']]); $job=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$job){http_response_code(404);die('Job title not found.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{ttOperationsRequireCsrf();$name=substr(trim((string)($_POST['job_name']??'')),0,120);$type=(string)($_POST['job_type']??'');$description=substr(trim((string)($_POST['description']??'')),0,5000);if($name===''||!isset($types[$type]))throw new RuntimeException('Enter a name and select a valid operating pattern.');
 $stmt=$pdo->prepare('UPDATE jobs SET job_name=?,job_type=?,custom_job_type=?,description=?,active=? WHERE id=? AND railroad_id=?');$stmt->execute([$name,$type,'',$description,($_POST['active']??'1')==='1'?1:0,$id,(int)$railroad['id']]);header('Location: view.php?id='.$id);exit;}catch(Throwable $e){$error=$e->getMessage();}}
?>
<?php include '../includes/header.php'; ?><title>Edit Job Title</title></head><body><?php include '../includes/navbar.php'; ?>
<div class="container mt-4" style="max-width:800px"><p class="text-uppercase text-muted small mb-1">Operations</p><h1>Edit Job Title</h1><p class="text-muted">Legacy home-location and equipment associations are preserved in the database but are not used by new operating sessions.</p>
<?php if($error):?><div class="alert alert-danger"><?=ttHtml($error)?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf_token" value="<?=ttHtml(ttOperationsCsrfToken())?>"><div class="mb-3"><label class="form-label">Job Name</label><input class="form-control" name="job_name" maxlength="120" required value="<?=ttHtml($job['job_name'])?>"></div>
<div class="mb-3"><label class="form-label">Default Operating Pattern</label><select class="form-select" name="job_type"><?php foreach($types as $value=>$label):?><option value="<?=ttHtml($value)?>" <?=$job['job_type']===$value?'selected':''?>><?=ttHtml($label)?></option><?php endforeach;?></select></div>
<div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="active"><option value="1" <?=$job['active']?'selected':''?>>Active</option><option value="0" <?=!$job['active']?'selected':''?>>Inactive</option></select></div>
<div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="5" maxlength="5000"><?=ttHtml($job['description'])?></textarea></div><button class="btn btn-success">Save Changes</button> <a class="btn btn-secondary" href="view.php?id=<?=$id?>">Cancel</a></form></div><?php include '../includes/footer.php'; ?>
