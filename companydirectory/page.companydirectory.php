<?php
//check for ajax request and prosses that imideatly
if(isset($_REQUEST['ajaxgettr'])){//got ajax request
	$opts=explode('|',$_REQUEST['ajaxgettr']);
	echo companydirectory_draw_entires_tr($opts[1],'',$opts[0],$opts[2]);
	exit;
}
//get vars
$requestvars=array('id','action','entries','newentries');
foreach($requestvars as $var){
	$$var=isset($_REQUEST[$var])?$_REQUEST[$var]:'';
}
//draw right nav bar
companydirectory_drawListMenu();


if($action=='' && $id==''){
	echo '<h2 id="title">Company Directory</h2>';
	echo '<br /><br /><input type="button" value="'._('Add a new Company Directory').'" onclick="window.location.href=\'/admin/config.php?type=tool&display=companydirectory&action=add\';"/>';
	echo '<br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br />';
}

?>

<script type="text/javascript">
$(document).ready(function(){
	//show/hide add buttin/dropdown
	$('#addbut').click(function(){
		$(this).fadeOut(250,
		function(){
			$('#addrow').fadeIn(250);
		});
		return false;
	});
	$('#addrow').change(function(){
		$(this).fadeOut(250,
		function(){
			$('#addbut').not("span").fadeIn(250).find("span").hide();
		});
		addrow($('#addusersel').val());
		return false;
	})		
});

//add a new entrie to the table
function addrow(user){
	$('#addusersel').val('');//reset select box
	console.log(user)
	$.ajax({
  url: location.href,
  data: 'ajaxgettr='+user,
  success: function(data) {
    $('.result').html(data);
    $('#dir_entires_tbl').last().append(data);
  }
});

}
</script>

<style type="text/css">
#addrow{display:none;}
#dir_entires_tbl :not(tfoot) tr:nth-child(odd){background-color:#FCE7CE;}
</style>
