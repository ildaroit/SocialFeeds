<?php
/**
 * Implementation of FourSquare feed reader
 */
class FeedSource_Foursquare extends FeedSource
{
	const API_URL = 'https://api.foursquare.com/v2/users/self/checkins';
	
	public function doUpdate()
	{
		$this->latest_id = (int)$this->latest_id;
		$latest_id = $this->latest_id;
		
		$querystring = array(
			'oauth_token' => $this->extra_params['oauth_token'],
			'limit' => 500,
		);
		
		// If we have a minimum date, use it!
		if ($this->latest_id > 0)
		{
			$querystring['afterTimestamp'] = $this->latest_id;
		}
		
		$url = self::API_URL . '?' . http_build_query($querystring, null, '&');
		
		$data = json_decode(file_get_contents($url));
		$data = array_reverse($data->response->checkins->items);
		
		foreach ($data as $checkin)
		{
			$this->saveToDB($checkin->id, $checkin->createdAt, $checkin->venue->name, (isset($checkin->shout) ? $checkin->shout : null), null, array(
				'venue_id' => $checkin->venue->id,
				'location' => $checkin->venue->location
			));
			
			$latest_id = max($latest_id, $checkin->createdAt);
		}
		
		$this->latest_id = $latest_id;
	}

	public function loadFromDB($row)
	{
		$result = (object)array(
			'id' => $row->id,
			'text' => 'At <a href="' . $this->getVenueUrl($row->extra_data['venue_id']) . '" rel="nofollow" target="_blank">' . $row->text . '</a> (' . $row->extra_data['location']->city . ', ' . $row->extra_data['location']->state . ')',
			'subtext' => '<a href="' . $this->getMapUrl($row->extra_data['location']->lat, $row->extra_data['location']->lng) . '" rel="nofollow" target="_blank">View map</a>',
			'url' => $this->getCheckinUrl($this->username, $row->original_id),
			'date' => $row->date,
			'type' => 'foursquare',
		);
		
		if ($row->description != null)
		{
			$result->text .= ': ' . $row->description;
		}
		
		return $result;
	}
	
	protected static function getCheckinUrl($username, $checkinId)
	{
		return 'http://foursquare.com/' . $username . '/checkin/' . $checkinId;
	}
	
	protected static function getVenueUrl($venueId)
	{
		return 'http://foursquare.com/venue/' . $venueId;
	}
	
	protected static function getMapUrl($lat, $lon)
	{
		return 'http://maps.google.com/maps?q=' . $lat . ',' . $lon;
	}
}