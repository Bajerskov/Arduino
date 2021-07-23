<?php
require_once("./cityprobe.php");
/*
Populate database with data from citryprobe website:
download csv file
read the 10 latest readings from the selected probes
(maybe process  the data here rather than on the return call to minimize process time.)
insert the readings into the db.

*/

?>
<html>
<head>
  <style type="text/css">
  .tg  {border-collapse:collapse;border-spacing:0;border-color:#9ABAD9;}
  .tg td{font-family:Arial, sans-serif;font-size:14px;padding:10px 5px;border-style:solid;border-width:1px;overflow:hidden;word-break:normal;border-color:#9ABAD9;color:#444;background-color:#EBF5FF;}
  .tg th{font-family:Arial, sans-serif;font-size:14px;font-weight:normal;padding:10px 5px;border-style:solid;border-width:1px;overflow:hidden;word-break:normal;border-color:#9ABAD9;color:#fff;background-color:#409cff;}
  .tg .tg-0lax{text-align:left;vertical-align:top}
  </style>
</head>
<body>
<table class="tg">
<tr>
  <th class='tg-0lax'>Core_id</th>
  <th class='tg-0lax'>Date</th>

  <th class='tg-0lax'>Noise</th>

  <th class='tg-0lax'>Particles</th>

  <th class='tg-0lax'>Gas</th>
</tr>
<?php


//need this function to filter out outliers and unrealistic datapoints.
function filter($cityprobe) {

  if($cityprobe['noise'] > 110 || $cityprobe['noise'] < 0) {
    return true;
  }

  if($cityprobe['gas'] > 110 || $cityprobe['noise'] < 0) {
    return true;
  }

  if($cityprobe['particles'] > 110 || $cityprobe['noise'] < 0) {
    return true;
  }

  return false;
}




//mysqli
$servername = "";
$username = "";
$password = "";
$dbname = "";

$db_array;
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
   die("Connection failed: " . $conn->connect_error);
}

//first remove all old data.
$conn->query("truncate cityprobe");


//prepare sql for inserting data to database
$sql = "INSERT INTO cityprobe (core_id, record_date, noise, particles, gas) VALUES (?, ?, ?, ?, ?)";

//prepare sql.
if ($stmt = $conn->prepare($sql)) {

  $row = 1; //row count for reading csv file

  //check if the file can be read and open it.
  if (($handle = fopen("./data/cityprobe.csv", "r")) !== FALSE) {

    $search_array = array(); // array used to keep check on which data sets have been selected.
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

      //skip first row
      if($row++ == 1) { continue; }

      $core_id = $data[1];
      if (!array_key_exists($core_id, $search_array)) {
          $search_array[$core_id] = array();

      }

        $timestamp =strtotime($data[10]);
        $time = date("Y-m-d H:i:s", $timestamp);
        $hour = date("H", $timestamp);
        if($hour == "08" || $hour == "12" || $hour == "16" || $hour == "20") {
          //check if we already have this hour in the array
          if (!in_array($hour, $search_array[$core_id])) {
            $cityprobe = new cityprobe($core_id, $time);
            $cityprobe->set_noise($data[17]);
            $cityprobe->set_particles($data[23], $data[24]);
            $cityprobe->set_gas($data[25], $data[26]);

            $probe_data = $cityprobe->return_as_array();


            //if the numbers are out of reasonable range, skip this entry.
            if(filter($probe_data)) { continue; }

            $search_array[$core_id][] = $hour;

            $stmt->bind_param("ssiii", $core_id, $record_date, $noise, $particles, $gas);


            $core_id = $probe_data['core_id'];
            $record_date = $probe_data['record_date'];
            $noise = $probe_data['noise'];
            $particles = $probe_data['particles'];
            $gas = $probe_data['gas'];

            $stmt->execute();


            $format = '<tr> <td>%s</td> <td>%s</td> <td>%d</td> <td>%d</td> <td>%d</td> </tr>';
            echo sprintf($format, $core_id, $record_date, $noise, $particles, $gas);


          }
        }
    }

    fclose($handle);


  }


echo "</table>";

    $stmt->close();
}


 ?>
</html>
