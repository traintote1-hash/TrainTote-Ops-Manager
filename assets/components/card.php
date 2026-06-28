<?php
function tt_card_open($title=''){?>
<section class="tt-card">
<?php if($title):?><div class="tt-card-header"><h2><?=htmlspecialchars($title)?></h2></div><?php endif;?>
<div class="tt-card-body">
<?php }
function tt_card_close(){?></div></section><?php }