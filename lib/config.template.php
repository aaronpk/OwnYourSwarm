<?php
class Config {
  public static $baseURL = 'https://ownyourswarm.example';

  public static $redis = 'tcp://127.0.0.1:6379';

  public static $beanstalkServer = '127.0.0.1';
  public static $beanstalkPort = 11300;

  public static $dbHost = '127.0.0.1';
  public static $dbName = 'ownyourswarm';
  public static $dbUsername = 'ownyourswarm';
  public static $dbPassword = 'p4ssw0rd';

  public static $foursquareClientID = '';
  public static $foursquareClientSecret = '';
  public static $foursquareClientPushSecret = '';
}
