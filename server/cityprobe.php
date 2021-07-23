<?php
define("pm10_min", 0, TRUE);
define("pm10_max", 50, TRUE);

define("pm25_min", 0, TRUE);
define("pm25_max", 25, TRUE);

define("co_min", 95, TRUE);
define("co_max", 340, TRUE);

define("no2_min", 200, TRUE);
define("no2_max", 2200, TRUE);

define("noise_min", 40, TRUE);
define("noise_max", 80, TRUE);

class cityprobe {
    private $_core_id;
    private $_record_date;
    private $_noise;
    private $_particles;
    private $_gas;

   public function __construct($core_id, $record_date) {
     $this->_record_date = $record_date;
     $this->_core_id = $core_id;
   }

   public function set_noise($noise) {
      $this->_noise = $this->map($noise, noise_min, noise_max, 0, 100);
   }

   public function set_gas($no2, $co) {
     $no2_scaled = $this->map($no2, no2_min, no2_max, 0, 100);
     $co_scaled = $this->map($co, co_min, co_max, 0, 100);

     $this->_gas = ($no2_scaled > $co_scaled) ? $no2_scaled : $co_scaled;
   }

   public function set_particles($pm10, $pm25) {
     $pm10_scaled = $this->map($pm10, pm10_min, pm10_max, 0, 100);
     $pm25_scaled = $this->map($pm25, pm25_min, pm25_max, 0, 100);

     $this->_particles = ($pm10_scaled > $pm25_scaled) ? $pm10_scaled : $pm25_scaled;
   }

   public function return_as_array() {

     return array("core_id" => $this->_core_id,
                  "record_date" => $this->_record_date,
                  "particles" => $this->_particles,
                  "gas" => $this->_gas,
                  "noise" => $this->_noise);
   }



   private function map($x, int $srcmin, int $srcmax, int $destmin, int $destmax) {
          //how far in the source range is $x (0..1)
          $pos = (($x - $srcmin) / ($srcmax-$srcmin));

          //figure out where that puts us in the destination range
          $rescaled = ($pos * ($destmax-$destmin)) + $destmin;
         return round($rescaled);
   }
}

 ?>
