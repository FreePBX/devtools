<?php
global $db;
global $amp_conf;

if (! function_exists("out")) {
	function out($text) {
		echo $text."<br />";
	}
}

if (! function_exists("outn")) {
	function outn($text) {
		echo $text;
	}
}


$autoincrement = (($amp_conf["AMPDBENGINE"] == "sqlite") || ($amp_conf["AMPDBENGINE"] == "sqlite3")) ? "AUTOINCREMENT":"AUTO_INCREMENT";

outn(_('Adding directory_details table...'));

$sql = "CREATE TABLE directory_details (
    id INT NOT NULL PRIMARY KEY $autoincrement,
    dirname varchar(50),
    description varchar(150),    
    announcement INT,
    valid_recording INT,
    callid_prefix varchar(10),
    alert_info varchar(50),
    repeat_loops varchar(3),
    repeat_recording INT,
    invalid_recording INT,
    invalid_destination varchar(50)
)";

$check = $db->query($sql);
if (DB::IsError($check)) {
  out(_('failed'));
	out(_('Can not create `directory_details` table: ') . $check->getMessage());
  return false;
}
out(_('ok'));
outn(_('Adding directory_entries table...'));

$sql = "CREATE TABLE directory_entries (
    id INT NOT NULL PRIMARY KEY,
    name varchar(50),
    type varchar(25),
    audio varchar(50),
    dial varchar(50)
);";

$check = $db->query($sql);
if (DB::IsError($check)) {
  out(_('failed'));
	out(_('Can not create `directory_entries` table: ') . $check->getMessage());
}
out(_('ok'));

?>
