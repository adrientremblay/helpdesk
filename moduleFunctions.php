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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


function isTechnician($gibbonPersonID, $connection2){
  try {
    $data=array("gibbonPersonID"=> $gibbonPersonID);
    $sql="SELECT * FROM helpDeskTechnicians WHERE gibbonPersonID=:gibbonPersonID";
    $result=$connection2->prepare($sql);
    $result->execute($data);
  }
  catch(PDOException $e) {
	   print $e;
  }

  return ($result->rowCount()==1);
}

function getTechnicianID($gibbonPersonID, $connection2){
  try {
    $data=array("gibbonPersonID"=> $gibbonPersonID);
    $sql="SELECT * FROM helpDeskTechnicians WHERE helpDeskTechnicians.gibbonPersonID=:gibbonPersonID ";
    $result=$connection2->prepare($sql);
    $result->execute($data);
  }
  catch(PDOException $e) {
	   print $e;
  }
  $id = null;
  if($result->rowCount()==1){
  	$array = $result->fetch();
  	$id = (int)$array["technicianID"];
  }
  return $id;
}

function hasTechnicianAssigned($issueID, $connection2)
{
  try {
    $data=array("issueID"=> $issueID);
    $sql="SELECT * FROM helpDeskIssue WHERE helpDeskIssue.issueID=:issueID ";
    $result=$connection2->prepare($sql);
    $result->execute($data);
  }
  catch(PDOException $e) {
	print $e;
  }
  $array = $result->fetchAll();
  $id = $array[0]["technicianID"];
  return ($id != null);
}

function getAllTechnicians($connection2)
{
  try {
    $data=array();
    $sql="SELECT helpDeskTechnicians.gibbonPersonID, surname, preferredName
	FROM helpDeskTechnicians
	JOIN gibbonPerson ON (helpDeskTechnicians.gibbonPersonID=gibbonPerson.gibbonPersonID)
	WHERE status='Full' ORDER BY surname, preferredName ASC";
    $result=$connection2->prepare($sql);
    $result->execute($data);
  }
  catch(PDOException $e) {
	print $e;
  }
  return $result->fetchAll();
}

function technicianExists($connection2, $technicianID)
{
  try {
    $data=array("technicianID"=> $technicianID);
    $sql="SELECT * FROM helpDeskTechnicians WHERE technicianID=:technicianID";
    $result=$connection2->prepare($sql);
    $result->execute($data);
  }
  catch(PDOException $e) {
	   print $e;
  }

  return ($result->rowCount()==1);
}

function technicianExistsFromPersonID($connection2, $gibbonPersonID) {
  try {
    $data=array("gibbonPersonID"=> $gibbonPersonID);
    $sql="SELECT * FROM helpDeskTechnicians WHERE gibbonPersonID=:gibbonPersonID";
    $result=$connection2->prepare($sql);
    $result->execute($data);
  }
  catch(PDOException $e) {
    print $e;
  }

  return ($result->rowCount()==1);
}

function notifyTechnican($connection2, $guid, $issueID) {
  try {
    $data=array();
    $sql="SELECT * FROM helpDeskTechnicians";
    $result=$connection2->prepare($sql);
    $result->execute($data);
  }
  catch(PDOException $e) {
	   print $e;
  }

  while($row = $result->fetch()) {
  	setNotification($connection2, $guid, $row["gibbonPersonID"], "A new issue has been added.", "Help Desk", "/index.php?q=/modules/Help Desk/issues_discuss_view.php&issueID=" . $issueID);
  }
}

function relatedToIssue($connection2, $issueID, $gibbonPersonID) {
  try {
    $data=array("issueID"=> $issueID);
    $sql="SELECT * FROM helpDeskIssue WHERE issueID=:issueID";
    $result=$connection2->prepare($sql);
    $result->execute($data);
  }
  catch(PDOException $e) {
	print $e;
  }  
  $row = $result->fetch();
  if($row['technicianID']!=null) {
    try {
      $data=array("issueID"=> $issueID);
      $sql="SELECT helpDeskIssue.*, helpDeskTechnicians.technicianID, helpDeskTechnicians.gibbonPersonID AS personID FROM helpDeskIssue JOIN helpDeskTechnicians ON (helpDeskIssue.technicianID=helpDeskTechnicians.technicianID) WHERE issueID=:issueID";
      $result=$connection2->prepare($sql);
      $result->execute($data);
    }  
    catch(PDOException $e) {
	  print $e;
    }
    $row = $result->fetch();  
    $isRelated = $row["gibbonPersonID"]==$gibbonPersonID || $row["personID"]==$gibbonPersonID;
  }
  else {
    $isRelated = $row["gibbonPersonID"]==$gibbonPersonID;
  }

  return $isRelated;
}

function isPersonsIssue($connection2, $issueID, $gibbonPersonID) {
  try {
    $data=array("issueID"=> $issueID, "gibbonPersonID"=> $gibbonPersonID);
    $sql="SELECT * FROM helpDeskIssue WHERE issueID=:issueID AND gibbonPersonID=:gibbonPersonID";
    $result=$connection2->prepare($sql);
    $result->execute($data);
  }
  catch(PDOException $e) {
	print $e;
  }
  
  return ($result->rowCount()==1);
}

function getOwnerOfIssue($connection2, $issueID) {
	try {
    $data=array("issueID"=> $issueID);
    $sql="SELECT helpDeskIssue.gibbonPersonID FROM helpDeskIssue WHERE issueID=:issueID";
    $result=$connection2->prepare($sql);
    $result->execute($data);
    $row = $result->fetch();
  }
  catch(PDOException $e) {
	print $e;
  }
  
  return $row["gibbonPersonID"]; 
}

function getTechWorkingOnIssue($connection2, $issueID) {
  try {
    $data=array("issueID"=> $issueID);
    $sql="SELECT helpDeskTechnicians.gibbonPersonID AS personID FROM helpDeskIssue JOIN helpDeskTechnicians ON (helpDeskIssue.technicianID=helpDeskTechnicians.technicianID) WHERE issueID=:issueID";
    $result=$connection2->prepare($sql);
    $result->execute($data);
    $row = $result->fetch();
  }
  catch(PDOException $e) {
	print $e;
  }
  
  return $row["personID"];	
}

function getAllPeople($connection2, $excludeTechnicians = false) {
	try {
		$data=array();
		$sql="SELECT gibbonPersonID, surname, preferredName FROM gibbonPerson WHERE status='Full'";
		$sql.=" ORDER BY surname, preferredName ASC";
		$result=$connection2->prepare($sql);
		$result->execute($data);
	} catch(PDOException $e) {
	print $e;
	}
	$allPeople = array();
  if($excludeTechnicians) {
		try {
			$data2=array();
			$sql2="SELECT * FROM helpDeskTechnicians";
			$result2=$connection2->prepare($sql2);
			$result2->execute($data2);
		} catch(PDOException $e) {
		print $e;
		}
		while($row = $result->fetch()) {
			$gibbonPersonID = $row["gibbonPersonID"];
			$match = false;
			while($row2 = $result->fetch()) {
				if($match) { break; }
				$match = ($gibbonPersonID == $row2["gibbonPersonID"]);
			}
			if(!$match) {
				array_push($allPeople, $row);
			}
		}
  	} else {
		while($row = $result->fetch()) {
			array_push($allPeople, $row);
		}	
  	}
  	 return $allPeople;
  }
?>
