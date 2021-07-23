<?php
header('Content-Type: application/json');

//mysqli
$servername = "";
$username = "";
$password = "";
$dbname = "";


$probes = array(
"3c001c000751373239383834" => 1,
"29001b000751373239383834" => 2,
"2f002f001847373239323130" => 3,
"30002a001947373239323130" => 4,
"320033000751373239383834" => 5,
"420055000751373239383834" => 6,
"2f001b000751373239383834" => 7,
"280027001947373239323130" => 8,
"39001e000751373239383834" => 9,
"380044001947373239323130" => 10
);



$key = $_GET['key'];
$request = $_GET['request'];
$time = $_GET['time'];
if($key != "ixd604" || empty($request) || empty($request)) {
  exit;
}


$sql_build = "SELECT * FROM cityprobe WHERE ";
if($time == "morning") {
  $sql_build .= "HOUR(record_date) = 8";
} else if($time == "midday") {
  $sql_build .= "HOUR(record_date) = 12";
} else if($time == "afternoon") {
  $sql_build .= "HOUR(record_date) = 16";
} else if($time == "evening") {
  $sql_build .= "HOUR(record_date) = 20";
}

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
   die("Connection failed: " . $conn->connect_error);
}

//nytår
//SELECT * FROM cityprobe WHERE record_date >= '2019-01-01 13:00:00' AND record_date <= '2019-01-01 23:59:59';
/*
request=normal
request=cars
request=fireplaces
request=newyear

time=morning
time=midday
time=afternoon
time=evening



*/

$gas_modifier = 1;
$particles_modifier = 1;
$noise_modifer = 1;


switch ($request) {
    case 'nocars':
    $gas_modifier = bcsub(1, 0.2, 2);
    $particles_modifier = bcsub(1, 0.2, 2);
    $noise_modifer = bcsub(1, 0.2, 2);
    break;
    case 'nofireplace':
    $gas_modifier = bcsub(1, 0.34, 2);
    $particles_modifier = bcsub(1, 0.34, 2);
    //$noise_modifer = bcsub(1, 0.34, 2);
    break;
    case 'doublecars':
    $gas_modifier = bcadd(1, 0.3, 2);
    $particles_modifier = bcadd(1, 0.3, 2);
    $noise_modifer = bcadd(1, 0.3, 2);
    break;

    default:
    // code...
    break;
}


$json_data = array();



$sql = "SELECT * FROM cityprobe WHERE HOUR(record_date) = 8 ";
$result = $conn->query($sql_build);

if ($result->num_rows > 0) {

   while($row = $result->fetch_assoc()) {
    var_dump($row);
     $json_data[  $probes[ $row['core_id'] ] ] = array( "noise" => round($row["noise"]*$noise_modifer),
                                          "gas" => round($row["gas"]*$gas_modifier),
                                          "particles" => round($row["particles"]*$particles_modifier));
   }

} else {
   echo "0 results";
}
$result->free();
$conn->close();

echo json_encode($json_data);

?>
