<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

include "../../functions.php" ;
include "../../config.php" ;

include "./moduleFunctions.php" ;

//New PDO DB connection
try {
  $connection2=new PDO("mysql:host=$databaseServer;dbname=$databaseName;charset=utf8", $databaseUsername, $databasePassword);
  $connection2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $connection2->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
catch(PDOException $e) {
  echo $e->getMessage();
}

@session_start() ;

//Set timezone from session variable
date_default_timezone_set($_SESSION[$guid]["timezone"]);

$URL=$_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/Help Desk/helpDesk_manageTechnicianGroup.php" ;

if (isActionAccessible($guid, $connection2, "/modules/Help Desk/helpDesk_manageTechnicianGroup.php")==FALSE) {
  //Fail 0
  $URL=$URL . "&addReturn=fail0" ;
  header("Location: {$URL}");
}
else {
  //Proceed!
  if(isset($_GET["groupID"])) {
    $groupID=$_GET["groupID"] ;
  }
  else {
    $URL=$URL . "&addReturn=fail1" ;
    header("Location: {$URL}");
    exit();
  }
  
  if(isset($_POST["group"])) {
  	$newGroupID = $_POST["group"];
  }
  else {
    $URL=$URL . "&addReturn=fail1" ;
    header("Location: {$URL}");
    exit();
  }
  
  try {
		$data3=array();
		$sql3="SELECT * FROM helpDeskTechGroups ORDER BY helpDeskTechGroups.groupID ASC";
		$result3=$connection2->prepare($sql3);
		$result3->execute($data3);
  	} catch(PDOException $e) {
		print $e;
	}
	if($result3->rowcount() == 1) {
		$URL=$URL . "&addReturn=fail4";
		header("Location: {$URL}");
		exit();
	}
  
  //Write to database

  try {
 	 $gibbonModuleID = getModuleIDFromName($connection2, "Help Desk");
	if($gibbonModuleID == null) {
		throw new PDOException("Invalid gibbonModuleID.");
	}
    $data=array("groupID" => $groupID) ;
    $sql="DELETE FROM helpDeskTechGroups WHERE groupID=:groupID" ;
    $result=$connection2->prepare($sql);
    $result->execute($data);

	$data2=array("groupID" => $groupID, "newGroupID" => $newGroupID) ;
    $sql2="UPDATE helpDeskTechnicians SET groupID=:newGroupID WHERE groupID=:groupID" ;
    $result2=$connection2->prepare($sql2);
    $result2->execute($data2);
  } catch(PDOException $e) {
    //Fail 2
    $URL = $URL."&addReturn=fail2" ; 
    header("Location: {$URL}");
    exit();
  }
	  
	setLog($connection2, $_SESSION[$guid]["gibbonSchoolYearID"], $gibbonModuleID, $_SESSION[$guid]["gibbonPersonID"], "Technician Group Removed", array("groupID"=>$groupID, "newGroupID"=>$newGroupID));

  //Success 0
  $URL=$URL . "&addReturn=success0" ;
  header("Location: {$URL}");
}
?>
