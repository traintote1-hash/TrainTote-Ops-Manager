<?php
function tt_breadcrumb(array $items){
    echo '<nav class="tt-breadcrumb">';
    $last=array_key_last($items);
    foreach($items as $i=>$label){
        echo '<span>'.htmlspecialchars($label).'</span>';
        if($i!==$last) echo ' &raquo; ';
    }
    echo '</nav>';
}
?>