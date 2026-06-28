//
// SEARCH
//

document.getElementById('searchBox').addEventListener('keyup', function(){

let filter = this.value.toUpperCase();

let rows = document.querySelectorAll('.car-row');

rows.forEach(function(row){

let text = row.innerText.toUpperCase();

if(text.indexOf(filter) > -1){

row.style.display='';

}
else{

row.style.display='none';

}

});

});


//
// FILTER BUTTONS
//

document.querySelectorAll('.filter-btn').forEach(function(button){

button.addEventListener('click',function(){

let filter=this.dataset.filter;

let rows=document.querySelectorAll('.car-row');

rows.forEach(function(row){

row.style.display='';

if(filter==='all'){
return;
}

if(filter==='loaded'
&& row.dataset.loaded!=='loaded'){

row.style.display='none';

}

if(filter==='empty'
&& row.dataset.loaded!=='empty'){

row.style.display='none';

}

if(filter==='waybill'
&& row.dataset.waybill!=='yes'){

row.style.display='none';

}

if(filter==='move'
&& row.dataset.move!=='yes'){

row.style.display='none';

}

if(filter==='interchange'
&& !row.dataset.location.includes('interchange')){

row.style.display='none';

}

if(filter==='industry'
&& row.dataset.location.includes('interchange')){

row.style.display='none';

}

if(filter==='unassigned'
&& row.dataset.location!=='unassigned'){

row.style.display='none';

}

});

});

});


//
// PHOTO TOGGLE
//

document.getElementById('togglePhotos').addEventListener('click',function(){

document.querySelectorAll('.photo-column').forEach(function(col){

if(col.style.display==='none'
|| col.style.display===''){

col.style.display='table-cell';

}
else{

col.style.display='none';

}

});

});


//
// DENSITY
//

document.getElementById('densityMode').addEventListener('change',function(){

document.body.classList.remove(
'compact-mode',
'ultra-mode'
);

if(this.value==='compact'){

document.body.classList.add('compact-mode');

}

if(this.value==='ultra'){

document.body.classList.add('ultra-mode');

}

});
//
// SORTING
//

let sortDirections = {};

window.sortTable = function(column){

let table = document.getElementById('carTable');

let tbody = table.tBodies[0];

let rows = Array.from(tbody.querySelectorAll('.car-row'));

sortDirections[column] = !sortDirections[column];

rows.sort(function(a,b){

let x = a.cells[column].innerText.trim().toUpperCase();

let y = b.cells[column].innerText.trim().toUpperCase();

if(sortDirections[column]){

return x.localeCompare(y,undefined,{numeric:true});

}
else{

return y.localeCompare(x,undefined,{numeric:true});

}

});

//
// remove rows
//

rows.forEach(function(row){

tbody.appendChild(row);

});

//
// update arrows
//

document.querySelectorAll('#carTable th .sort-arrow')
.forEach(function(arrow){

arrow.innerHTML='';

});

let arrows =
document.querySelectorAll('#carTable th .sort-arrow');

if(arrows[column]){

arrows[column].innerHTML =
sortDirections[column]
?
' ?'
:
' ?';

}

//
// remember sort
//

localStorage.setItem(
'statusSortColumn',
column
);

localStorage.setItem(
'statusSortDirection',
sortDirections[column]
);

};


//
// RESTORE SORT
//

window.addEventListener('load',function(){

let col =
localStorage.getItem('statusSortColumn');

let dir =
localStorage.getItem('statusSortDirection');

if(col!==null){

sortDirections[col] = (dir==='true');

sortTable(parseInt(col));

}

});

//
// FAVORITES
//

let favorites =
JSON.parse(
localStorage.getItem('favoriteCars')
|| '[]'
);


//
// INITIALIZE STARS
//

document.querySelectorAll('.favorite-cell')
.forEach(function(cell){

let row = cell.closest('.car-row');

let car =
row.cells[1].innerText.trim();

if(favorites.includes(car)){

cell.innerHTML='?';

cell.style.color='gold';

}
else{

cell.innerHTML='?';

}

cell.addEventListener('click',function(e){

e.stopPropagation();

if(favorites.includes(car)){

favorites =
favorites.filter(
x => x!==car
);

cell.innerHTML='?';

cell.style.color='';

}
else{

favorites.push(car);

cell.innerHTML='?';

cell.style.color='gold';

}

localStorage.setItem(
'favoriteCars',
JSON.stringify(favorites)
);

});

});


//
// FAVORITES FILTER
//

document.querySelectorAll('.filter-btn')
.forEach(function(button){

button.addEventListener('click',function(){

if(this.dataset.filter!=='favorites'){
return;
}

document.querySelectorAll('.car-row')
.forEach(function(row){

let car =
row.cells[1].innerText.trim();

if(favorites.includes(car)){

row.style.display='';

}
else{

row.style.display='none';

}

});

});

});

//
// COLLAPSIBLE GROUPS
//

let collapsedGroups =
JSON.parse(
localStorage.getItem('collapsedGroups')
|| '{}'
);

document.querySelectorAll('.group-toggle')
.forEach(function(button){

let group =
button.dataset.group;

//
// restore collapse
//

if(collapsedGroups[group]){

button.innerHTML='?';

document.querySelectorAll(
'.car-row[data-group="'+group+'"]'
).forEach(function(row){

row.style.display='none';

});

}

button.addEventListener('click',function(e){

e.preventDefault();

if(button.innerHTML==='?'){

button.innerHTML='?';

document.querySelectorAll(
'.car-row[data-group="'+group+'"]'
).forEach(function(row){

row.style.display='none';

});

collapsedGroups[group]=true;

}
else{

button.innerHTML='?';

document.querySelectorAll(
'.car-row[data-group="'+group+'"]'
).forEach(function(row){

row.style.display='';

});

collapsedGroups[group]=false;

}

localStorage.setItem(
'collapsedGroups',
JSON.stringify(collapsedGroups)
);

});

});

//
// SAVED VIEWS
//

document.querySelectorAll('.saved-view')
.forEach(function(button){

button.addEventListener('click',function(){

let view =
this.dataset.view;

//
// DEFAULT
//

if(view==='default'){

document.body.classList.remove(
'compact-mode',
'ultra-mode'
);

document.querySelectorAll('.photo-column')
.forEach(function(col){

col.style.display='none';

});

}

//
// YARDMASTER
//

if(view==='yardmaster'){

document.body.classList.add(
'compact-mode'
);

document.querySelectorAll('.photo-column')
.forEach(function(col){

col.style.display='none';

});

}

//
// SWITCH CREW
//

if(view==='switchcrew'){

document.body.classList.add(
'ultra-mode'
);

document.querySelectorAll('.photo-column')
.forEach(function(col){

col.style.display='none';

});

//
// filter move needed
//

document.querySelectorAll('.car-row')
.forEach(function(row){

if(row.dataset.move==='yes'){

row.style.display='';

}
else{

row.style.display='none';

}

});

}

//
// DISPATCHER
//

if(view==='dispatcher'){

document.body.classList.remove(
'compact-mode',
'ultra-mode'
);

document.querySelectorAll('.photo-column')
.forEach(function(col){

col.style.display='table-cell';

});

}

localStorage.setItem(
'savedView',
view
);

});

});

//
// RESTORE VIEW
//

window.addEventListener('load',function(){

let view =
localStorage.getItem('savedView');

if(view){

let button =
document.querySelector(
'.saved-view[data-view="'+view+'"]'
);

if(button){

button.click();

}

}

});

//
// EXPORT CSV
//

document.getElementById('exportCSV')
.addEventListener('click', function(){

let csv = [];

//
// headers
//

let headers = [];

document.querySelectorAll('#carTable th')
.forEach(function(th){

headers.push(
th.innerText.trim()
);

});

csv.push(headers.join(','));

//
// rows
//

document.querySelectorAll('.car-row')
.forEach(function(row){

if(row.style.display==='none'){
return;
}

let data=[];

row.querySelectorAll('td')
.forEach(function(cell){

let text =
cell.innerText
.replace(/\n/g,' ')
.replace(/,/g,' ');

data.push(text);

});

csv.push(data.join(','));

});

//
// download
//

let blob = new Blob(
[csv.join('\n')],
{
type:'text/csv'
}
);

let url =
window.URL.createObjectURL(blob);

let a =
document.createElement('a');

a.href=url;

a.download='car_status.csv';

a.click();

window.URL.revokeObjectURL(url);

});



//
// DOUBLE CLICK GROUP HEADER
//

document.querySelectorAll('.group-header')
.forEach(function(header){

header.addEventListener('dblclick',function(){

let button =
header.querySelector('.group-toggle');

if(button){

button.click();

}

});

});



//
// HIGHLIGHT ACTIVE FILTER BUTTON
//

document.querySelectorAll('.filter-btn')
.forEach(function(button){

button.addEventListener('click',function(){

document.querySelectorAll('.filter-btn')
.forEach(function(btn){

btn.classList.remove(
'btn-primary'
);

});

this.classList.add(
'btn-primary'
);

});

});



//
// RESET SEARCH CLEARS FILTER
//

document.getElementById('searchBox')
.addEventListener('search',function(){

document.querySelector(
'.filter-btn[data-filter="all"]'
).click();

});



//
// SCROLL TO TOP AFTER SORT
//

function scrollTopBoard(){

window.scrollTo({

top:0,

behavior:'smooth'

});

}

