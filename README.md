# php-hashcash-implementation

Example of how to implement the hashcash protocol on php. 


Hashcash is a protocol to assure a client make some computational work
in order to ask a service from a server, preventing abuse or denial of service
attacks from some clients. Was originally proposed as a counter-measure
against e-mail spamming:

http://www.hashcash.org/papers/hashcash.pdf

https://en.wikipedia.org/wiki/Proof-of-work_system


This is just an example about how a hashcash could be implemented in a php server, 
see the hashcahs.php docs for more detail.

Usage:
```php
<?php
  //instantiates a puzzler:
  $puzzler = new Hashcash_Puzzler();
  
  //get puzzle:
  $package = $puzzler->get_puzzle_package();
  
  //send this to whoever is the client. Since this is just an example, 
  //we have coded a pure php client, but in a real world scenario 
  //the connection between client and server should be online, 
  //and there is no need they known each other if the client
  //sticks to the correct schema.
  $client = new Hashcash_Client($package);
  $data = $client->get_solution();
  
  //validate the data in the server:
  $puzzler->validate($data);

```
