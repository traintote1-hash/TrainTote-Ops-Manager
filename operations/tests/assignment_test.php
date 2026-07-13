<?php
require_once dirname(__DIR__).'/assignment_service.php';
function assignmentExpect($condition,string$message):void{if(!$condition)throw new RuntimeException($message);}
assignmentExpect(ttAssignmentIsEditable(['status'=>'draft'],[]),'Draft assignment should be editable.');
assignmentExpect(ttAssignmentIsEditable(['status'=>'waiting'],['draft']),'Waiting assignment with only a draft list should be editable.');
foreach(['ready','in_progress','needs_review','completed','cancelled']as$status)assignmentExpect(!ttAssignmentIsEditable(['status'=>$status],[]),$status.' assignment must be frozen.');
foreach(['approved','in_progress','needs_review','completed']as$status)assignmentExpect(!ttAssignmentIsEditable(['status'=>'draft'],[$status]),$status.' work must freeze editing.');
[$ready,$reason]=ttSessionStartReadiness([]);assignmentExpect(!$ready&&$reason!=='','Empty session must not start.');
[$ready]=ttSessionStartReadiness([['status'=>'draft','approved_list_count'=>0]]);assignmentExpect(!$ready,'Draft-only assignment must block start.');
[$ready]=ttSessionStartReadiness([['status'=>'ready','approved_list_count'=>1],['status'=>'waiting','approved_list_count'=>1]]);assignmentExpect($ready,'Ready and validly waiting assignments should allow start.');
$service=file_get_contents(dirname(__DIR__).'/assignment_service.php');$edit=file_get_contents(dirname(__DIR__).'/assignment_edit.php');$delete=file_get_contents(dirname(__DIR__).'/assignment_delete.php');$session=file_get_contents(dirname(__DIR__).'/session_edit.php');$formJs=file_get_contents(dirname(__DIR__,2).'/assets/js/assignment-form.js');$generate=file_get_contents(dirname(__DIR__).'/generate.php');
assignmentExpect(strpos($service,'$endPlan===\'return_origin\'?0')!==false,'Return-to-origin must ignore submitted end location.');
assignmentExpect(strpos($service,'$start===\'inherit\'')!==false&&strpos($service,'Choose a valid previous assignment from this session.')!==false,'Inheritance must require a scoped predecessor.');
assignmentExpect(strpos($service,"status='ready'")!==false&&strpos($service,"status='assigned'")!==false,'Prepared-cut replacement must validate and transition cut status.');
assignmentExpect(strpos($service,'ORDER BY pc.position')!==false,'Prepared-cut car order must be preserved.');
assignmentExpect(strpos($service,"SET status='ready'")!==false,'Removing or replacing a prepared cut must release the old cut.');
assignmentExpect(strpos($service,"UPDATE equipment SET")===false&&strpos($edit,"UPDATE equipment SET")===false&&strpos($delete,"UPDATE equipment SET")===false,'Assignment changes must not move equipment or update load status.');
assignmentExpect(strpos($delete,"REQUEST_METHOD']!=='POST'")!==false&&strpos($delete,'ttOperationsRequireCsrf')!==false,'Assignment deletion must require POST and CSRF.');
assignmentExpect(strpos($delete,"status IN('draft','cancelled')")!==false,'Deletion must only remove unapproved draft history.');
assignmentExpect(strpos($delete,'predecessor_assignment_id')!==false,'Deletion must block assignments that retained records depend on.');
assignmentExpect(strpos($session,'ttSessionStartReadiness')!==false,'Start Session must use server-side readiness validation.');
assignmentExpect(strpos($edit,'beginTransaction')!==false&&strpos($edit,'ttAssignmentIsEditable')!==false,'Draft assignment editing must be transactional and enforce the frozen boundary.');
assignmentExpect(strpos($formJs,"method==='prepared_cut'")!==false&&strpos($formJs,"method==='inherit'")!==false&&strpos($formJs,"end.value!=='return_origin'")!==false,'Conditional start and end fields must be progressively disclosed.');
assignmentExpect(strpos($generate,'Generate Switch List')!==false&&strpos($generate,'Regenerate Switch List')!==false,'Generation page must use operator-facing switch-list wording.');
echo "assignment_test: OK\n";
