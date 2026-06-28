<?php
function tt_page_header($title,$subtitle=''){
echo "<header class='tt-page-header'><h1>".htmlspecialchars($title)."</h1>";
if($subtitle) echo "<p>".htmlspecialchars($subtitle)."</p>";
echo "</header>";
}
?>