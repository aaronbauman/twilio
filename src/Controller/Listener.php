<?php

namespace Drupal\twilio\Controller;

use Drupal\Core\Render\HtmlResponse;
use Twilio\Twiml;

class Listener {
  public function listen() {
    $twiml = new Twiml;
    $request = \Drupal::service('request_stack')->getCurrentRequest();
    \Drupal::logger('request')->error(t('<pre>@foo</pre>', ['@foo' => print_r($request->request, 1)]));
    $from = $request->request->get('From');
    $body = $request->request->get('Body');
    \Drupal::logger('body')->error('body ' . $body);
    \Drupal::logger('from')->error('from ' . $from);

    $geo_cache = \Drupal::state()->get('keyspot.geo.' . $from, FALSE);
    \Drupal::logger('geocache')->error($geo_cache);
    if ($geo_cache) {
      $body = strtolower($body);
      switch ($body) {
        case 'medicine':
        case 'food':
        case 'shelter':
        case 'all':
          return $this->doCarto($geo_cache, $body);
          break;
      }
    }

    if (!$geo_cache) {
      $geo = FALSE;
      try {
        $geo = \Drupal::service('geocoder')->geocode($body, array('googlemaps'));
      }
      catch (\Exception $e) {
        // Fall through to empty geo
        \Drupal::logger('exception')->error(t($e->getMessage()));
      }

      // Get address from body
      // If not address geo, reply with "get address" request
      if (empty($geo) || !count($geo)) {
        $twiml->message('Please provide a zip code, cross street, or address');
        return new HtmlResponse((string)$twiml);
      }

      \Drupal::logger('geo')->error(print_r($geo->get(0), 1));

      // Do carto search for nearest X points
      // Return address points.
      $address = $geo->get(0);
      $lat = $address->getLatitude();
      $lon = $address->getLongitude();
      $geo_cache = "$lat,$lon";
      \Drupal::state()->set('keyspot.geo.' . $from, "$lat,$lon");
    }

    $url = "https://aaronbauman.carto.com/api/v2/sql?q=SELECT * FROM keyspotlocationdata ORDER BY the_geom_webmercator<-> ST_Transform(CDB_LatLng($geo_cache), 3857) LIMIT 3";

    // $content = \Drupal::state()->get('keyspot.locations.' . $geo_cache, FALSE);
    // if (!$content) {
    $locations = FALSE;
    try {
      $response = \Drupal::httpClient()->get($url);
      $locations = json_decode($response->getBody()->getContents());
    }
    catch (\Exception $e) {
      \Drupal::logger('db')->error(t('exception ' . $e->getMessage()));
    }
    if (empty($locations->rows)) {
      $twiml->message('No nearby locations found. Please try another address.');
      return new HtmlResponse((string)$twiml);
    }
    \Drupal::logger('locations')->error(t('<pre>@foo</pre>', ['@foo' => print_r($locations, 1)]));
    $content = "\nHere are your nearest computer labs:\n";
    foreach ($locations->rows as $row) {
      $content .= $row->name . "\nAddress: " . $row->street . ' ' . $row->postal_code;
      if (!empty($row->phone_number)) {
        $content .= "\nCall for hours: " . $row->phone_number;
      }
      $content .= "\n\n";
    }
    $content .= "Reply 'food', 'shelter', 'medicine' or 'all' to see more locations";
    \Drupal::logger('response')->error($content);
    $twiml->message($content);
    return new HtmlResponse((string)$twiml);
  }

  protected function doCarto($latlon, $category) {
    $twiml = new Twiml;
    $url = "https://aaronbauman.carto.com/api/v2/sql?q=SELECT * FROM comm_svc2 ";
    $label = '';
    switch ($category) {
      case 'medicine':
        $label = 'Medical';
        $url .= " WHERE category = 'Medical' ";
        break;
      case 'food':
        $label = 'Emergency Food';
        $url .= " WHERE category = 'Emergency Food' ";
        break;
      case 'shelter':
        $label = 'Permanent Housing and Emergency Shelter';
        $url .= " WHERE category IN ('Permanent Housing', 'Emergency Shelter') ";
        break;
    }
    $url .= " ORDER BY the_geom_webmercator<-> ST_Transform(CDB_LatLng($latlon), 3857) LIMIT 3";
    $locations = FALSE;
    try {
      $response = \Drupal::httpClient()->get($url);
      $locations = json_decode($response->getBody()->getContents());
    }
    catch (\Exception $e) {
      \Drupal::logger('db')->error(t('exception ' . $e->getMessage()));
    }
    if (empty($locations->rows)) {
      $twiml->message('No nearby locations found. Please try another address.');
      return new HtmlResponse((string)$twiml);
    }
    \Drupal::logger('locations')->error(t('<pre>@foo</pre>', ['@foo' => print_r($locations, 1)]));

    $content = "\nHere are your nearest $label services:\n";
    foreach ($locations->rows as $row) {
      $content .= $row->organization_name . "\nAddress: " . $row->address;
      if (empty($label)) {
        $content .= "\n" . $row->category;
      }
      if (!empty($row->phone_number) && empty($row->time_open)) {
        $content .= "\nCall for hours: " . $row->phone_number;
      }
      elseif (!empty($row->phone_number)) {
        $content .= "\nPhone: " . $row->phone_number;
      }
      if (!empty($row->days)) {
        if (!empty($row->time_open) && !strpos($row->days, $row->time_open)) {
          $content .= "\nHours: " . $row->time_open;
          if (!empty($row->time_close)) {
            $content .= " - " . $row->time_close;
          }
        }
        else {
          $content .= "\nHours & info: " . $row->days;
        }
      }
      elseif (!empty($row->time_open)) {
        $content .= "\nHours: " . $row->time_open;
        if (!empty($row->time_close)) {
          $content .= " - " . $row->time_close;
        }
      }
      $content .= "\n\n";
    }
    \Drupal::logger('response')->error($content);
    $twiml->message($content);
    return new HtmlResponse((string)$twiml);    
  }
}


