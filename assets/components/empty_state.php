<?php
function tt_empty_state($title,$msg){?>
<div class="tt-empty-state">
<h3><?=htmlspecialchars($title)?></h3>
<p><?=htmlspecialchars($msg)?></p>
</div>
<?php }