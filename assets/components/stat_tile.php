<?php
function tt_stat_tile($label,$value){?>
<div class="tt-stat-tile">
<div class="tt-stat-value"><?=htmlspecialchars((string)$value)?></div>
<div class="tt-stat-label"><?=htmlspecialchars($label)?></div>
</div>
<?php }