# twilio
A Drupal module that listens for incoming twilio messages, geocodes address data, and replies with information about nearby KEYSPOT locations.

This Drupal module was developed for [The Reentry Project](https://thereentryproject.org/) in collaboration with Code for Philly at the Power Up Reentry Hackathon.

# How it works
* A Twilio app accepts SMS, and issues a webhook to the drupal site at ```http://example.com/keyspot```
* The module geocodes the SMS body to obtain a location
* Using the location, the module queries Carto SQL for the top 3 nearby Keyspot locations, and sends the response to Twilio
* The user can send another SMS with keywords "food", "shelter", "medicine", or "all" to find nearby locations from the Community Services dataset

# Datasets:
* [Philly KEYSPOT Locations](https://www.opendataphilly.org/dataset/philly-keyspot-locations/resource/12965cc6-410c-4aec-b947-a25337d687e3)
* [Community Services](https://www.opendataphilly.org/dataset/community-services)

