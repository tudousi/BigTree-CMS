<?
	/*
		Class: BigTreeGeocodingService
			Geocodes addresses with a variety of services.
	*/
	
	class BigTreeGeocodingService {
		
		var $Service = "";
		
		/*
			Constructor:
				Retrieves the current desired service and login credentials.
		*/
		
		function __construct() {
			global $cms;
			$geo_service = $cms->getSetting("bigtree-internal-geocoding-service");
			// If for some reason the setting doesn't exist, make one.
			if (!is_array($geo_service) || !$geo_service["service"]) {
				$this->Service = "google";
				$admin = new BigTreeAdmin;
				$admin->createSetting(array(
					"id" => "bigtree-internal-geocoding-service",
					"encrypted" => "on",
					"system" => "on"
				));
				$admin->updateSettingValue("bigtree-internal-geocoding-service",array("service" => "google"));
			} else {
				$this->Service = $geo_service["service"];
			}
		}

		/*
			Function: geocode
				Geocodes an address.

			Parameters:
				address - A string of address information to geocode.
				ignore_cache - Whether to re-fetch this geocode, even if we already have it cached. Defaults to false.

			Returns:
				An array containing "latitude" and "longitude" keys if successful, otherwise false.
		*/

		function geocode($address,$ignore_cache = false) {
			global $cms;
			if (!$address) {
				return false;
			}
			$address = trim($address);

			// Find out if we already have this information in the cache.
			if (!$ignore_cache) {
				$existing = $cms->cacheGet("org.bigtreecms.geocoding",$address);
				if ($existing) {
					return $existing;
				}
			}

			// Geocode
			if ($this->Service == "google") {
				$geocode = $this->geocodeGoogle($address);
			} elseif ($this->Service == "yahoo") {
				$geocode = $this->geocodeYahoo($address);
			}

			if (!$geocode) {
				return false;
			}
			$cms->cachePut("org.bigtreecms.geocoding",$address,$geocode);
			return $geocode;
		}

		/*
			Function: geocodeGoogle
				Private function for using Google as the geocoder.
		*/

		private function geocodeGoogle($address) {
			$response = BigTree::curl("http://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($address)."&sensor=false");
			try {
				if (is_string($response)) {
					$response = json_decode($response, true);
				}
				$latlng = $response["results"][0]["geometry"]["location"];
				return array("latitude" => $latlng["lat"], "longitude" => $latlng["lng"]);
			} catch (Exception $e) {
				return false;
			}
			return false;
		}

		/*
			Function: geocodeYahoo
				Private function for using Yahoo as the geocoder.
		*/

		private function geocodeYahoo($address) {
			$response = BigTree::curl("http://query.yahooapis.com/v1/public/yql?format=json&q=".urlencode('SELECT * FROM geo.placefinder WHERE text="'.sqlescape($address).'"'));
			try {
				if (is_string($response)) {
					$response = json_decode($response, true);
				}
				$lat = $response["query"]["results"]["Result"]["latitude"];
				$lon = $response["query"]["results"]["Result"]["longitude"];
				if ($lat && $lon) {
					return array("latitude" => $lat, "longitude" => $lon);
				} else {
					return false;
				}
			} catch (Exception $e) {
				return false;
			}
		}
	}