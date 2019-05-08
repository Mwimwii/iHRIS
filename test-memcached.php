<?php

if ( array_key_exists( 'IHRIS_MEMCACHED_SERVER', $_SERVER ) ) {
    print("Hello to memcached test...");
    $memcache = new Memcached; // instantiating memcache extension class
    $memcache->connect(mysql_memcached, 11211); // try 127.0.0.1 instead of localhost
    // if it is not working

    echo "Server's version: " . $memcache->getVersion() . "<br />\n";

    // we will create an array which will be stored in cache serialized
    $testArray = array('horse', 'cow', 'pig');
    $tmp = serialize($testArray);

    $memcache->add("key", $tmp, 30);

    echo "Data from the cache:<br />\n";
    print_r(unserialize($memcache->get("key")));
} else {
    print ("Could not find memcached server \n");
    print_r($_SERVER);
}
?>