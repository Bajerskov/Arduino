#include <ArduinoJson.h>
#include <FastLED.h>
#include <WiFi.h>
#include <HTTPClient.h>

const char* ssid = "box";
const char* password =  "ixd604e19";

const int magnetPin[10] = {39, 34, 35, 32, 33, 25, 26, 27, 14, 12};
StaticJsonDocument<1000> doc;
const char *payload;

//RGB_LEDS
#define LEDS_PER_RING 3
#define DATA_PIN 15
#define NUM_LEDS 30
#define BRIGHTNESS 70
#define LED_TYPE WS2812B
CRGB leds[NUM_LEDS];

#define GAS_LED 23
#define GAS_SENSOR 22
#define NOISE_LED 1
#define NOISE_SENSOR 3
#define PARTICLES_LED 21
#define PARTICLES_SENSOR 19
#define TIMING_POT 18
#define MODIFIER_POT 5

const int magnetSensorActivate = 200;
int pollutionChoice = 0;

struct pinLocation {
  int magnet_value = 0; //the measured magnetic value. 
  bool active = false;      // is this point activated on the map
  int gas = 0;          // from 0 - 100
  int particles = 0;    // from 0 - 100
  int noise = 0;        // from 0 - 100
  int fadeValue = 0; 
  bool fading = true;
  int brightness = 0;
};

//make space for 10 pinLocations in heap memory 
pinLocation pinLocationData[10];

void setup() {
  Serial.begin(115200);
  

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.println(F("Connecting to WiFi.."));
  }
 
  Serial.println(F("Connected to WiFi network"));

  FastLED.addLeds<WS2812B, 15>(leds, NUM_LEDS);
  FastLED.setBrightness(BRIGHTNESS);
  for(int i=0; i>10; i++) {
    pinMode(magnetPin[i], INPUT);
    
  }


  //gas button
  attachInterrupt(digitalPinToInterrupt(GAS_SENSOR), gas_button, HIGH);
  //particles button
  attachInterrupt(digitalPinToInterrupt(PARTICLES_SENSOR), particles_button, HIGH);
  //noise button
  attachInterrupt(digitalPinToInterrupt(NOISE_SENSOR), noise_button, HIGH);

 generateLocationData();
  delay(1000);
  //turn off all LED's
  blackout();
}

void light_button() {
  digitalWrite(GAS_LED, (pollutionChoice == 1) ? HIGH : LOW); 
  digitalWrite(PARTICLES_LED, (pollutionChoice == 2) ? HIGH : LOW);
  digitalWrite(NOISE_LED, (pollutionChoice == 3) ? HIGH : LOW);  
}


void gas_button() {
  pollutionChoice = 1;
  light_button();
}
void particles_button() {
  pollutionChoice = 2;
  light_button();
}

void noise_button() {
  pollutionChoice = 3;
  light_button();
}

void generateLocationData() {
  for(int i=0; i>10; i++) {
    
    int magnetSensor = analogRead(magnetPin[i]);
    
    pinLocationData[i].magnet_value = magnetSensor;
    pinLocationData[i].active = (magnetSensor > magnetSensorActivate) ? true : false;
    
  }
}

String url;


void loop() {
/*
  Iteration checklist. Because of HEAP memory restrictions, we have to be careful with getting new data.
  √  //first     -> check time variable. has it been changed ? 
              Yes -> mark the need for new data. update the url for the new timing 
  √  //Second    -> check modifier. has it been changed ? 
              Yes -> mark the need for new data. update the url for the new modifier
  √  //third     -> Fetch the data assign it to objects and insert into array .
    //Fourth    -> Check if any magnet sensors are activated and light up the area. 
*/

  bool update_model = false;

  String timing_part;
  if(checkTimer(&timing_part)) { //if true - time has changed. 
    update_model = true;
  } 

  String modifier_part;
  if(checkModifier(&modifier_part)) { //if true - modifier has changed
    update_model = true;
  }


  if(update_model) {
      Serial.println(F("update model"));
      url = "http://anderslf.dk/p6/api.php?key=ixd604&request=" + String(modifier_part) + "&time=" + String(timing_part);
      char* d;
      url.toCharArray(d,url.length());
      Serial.println(url);
      fetch_json(url);

    for(int i=0; i < 10; i++) {
      String key = String(i+1);
      JsonObject tmpObj = doc[key];
       pinLocationData[i].gas = tmpObj["gas"];
       pinLocationData[i].particles = tmpObj["particles"];
       pinLocationData[i].noise = tmpObj["noise"];
    }
  }


  Serial.print("heap: ");
  Serial.print(esp_get_minimum_free_heap_size());
  Serial.print(" ");
  Serial.println(esp_get_free_heap_size());

//loop dataset and light up areas
  for(int i=0; i < 10; i++) {
    if(pinLocationData[i].active) {
      if(pollutionChoice == 1) {
        pulse_light(pinLocationData[i].gas,i*LEDS_PER_RING);
      } else if(pollutionChoice == 2) {
        pulse_light(pinLocationData[i].particles,i*LEDS_PER_RING);
      } else if(pollutionChoice == 3) {
        pulse_light(pinLocationData[i].noise,i*LEDS_PER_RING);
      }
    } else {
      pulse_light(0,i*LEDS_PER_RING);
    }
  }
  
  delay(20);
}


void fetch_json(String endpoint) {
  
  if ((WiFi.status() == WL_CONNECTED)) { //Check the current connection status
 
    HTTPClient http;
 
    http.begin(endpoint); //Specify the URL
    int httpCode = http.GET();  //Make the request
 
    if (httpCode > 0) { //Check for the returning code
 
        deserializeJson(doc, http.getString().c_str());
        
      }
 
    else {
      Serial.println("Error on HTTP request");
    }
 
    http.end(); //Free the resources
  }
}

int green;
int red;
int pinBrightness;
int fadeAmount;
//pulses the light by intervals
void pulse_light(int percent, int offset) {

  pinLocationData[offset].fadeValue = map(percent, 0, 100, 2, 15);

  fadeAmount = (pinLocationData[offset].fading) ? pinLocationData[offset].fadeValue*1 : pinLocationData[offset].fadeValue*-1;

  //usefull if we want the colors to change when fading. maps the rgb values of red and green to a percentage.
  red = map(percent, 0, 100, 255, 0);
  green = map(percent, 0, 100, 0, 255);

  for(int i = offset; i < offset+LEDS_PER_RING; i++) {
      leds[i].setRGB(red,green,0);
    leds[i].fadeLightBy(pinLocationData[offset].brightness);

  }

//calculate a new brightness and constrain it to legal values
  pinBrightness = pinLocationData[offset].brightness + fadeAmount;
  pinBrightness = constrain(pinBrightness, 0, 255);
  pinLocationData[offset].brightness = pinBrightness;
  // reverse the direction of the fading at the ends of the fade:
  if(pinBrightness == 0 || pinBrightness == 255)
  {
    pinLocationData[offset].fading = !pinLocationData[offset].fading;
  }  

}


int modifier;
bool checkModifier(String *modify_string) {

  int reading = map(analogRead(MODIFIER_POT), 0, 4095, 0, 4);
  Serial.print("Modifier: ");
  Serial.println(analogRead(MODIFIER_POT));
  switch (reading) {
    case 1: // ingen biler 
       *modify_string = "nocars";
      break;
    case 2: // double biler
      *modify_string = "doublecars";
      break;
    case 3: //ingen brændeovne
      *modify_string = "nofireplace";
      break;
    case 4: //nytårs aften
      *modify_string = "newyear";
      break;
    default : //no parameter has been chosen or an error occured.
      *modify_string = "normal";
     break;

  }

   if(modifier == reading) {
      return false;
    } else {
      return true;
      modifier = reading;
    }

}


int timing;
bool checkTimer(String *timing_part) {
  //digital read something
  
  int reading = map(analogRead(TIMING_POT), 0, 4095, 1, 4);
  Serial.print("Timing: ");
  Serial.println(analogRead(TIMING_POT));
  
  switch (reading) {
    case 1: // morgen
      *timing_part = "morning";
      break;
    case 2: // middag
      *timing_part = "midday";
      break;
    case 3: //eftermiddag
      *timing_part = "afternoon";
      break;
    case 4: //aften
      *timing_part = "evening";
      break;
    default : //no parameter has been chosen or an error occured.
      *timing_part = "midday";
      break;
  }
      
    if(timing == reading) {
      return false;
    } else {
  
      timing = reading;
      return true;
    }
}


//shows black colour on all leds = turned off
void blackout() {
  for(int i = 0; i < NUM_LEDS; i++) {
   leds[i].setRGB(0,0,0);
  }
  FastLED.show();

}