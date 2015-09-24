<?php


        /**
        *
        *version : 0.1
        *@author Sergeon https://github.com/sergeon
        *
        *Represents a Hashcash_Server implementation that creates, stores and validates
        *Hashcash puzzles, and perform actions when a solution from a client is validated.
        *
        *Hashcash protocol is suitable for any non-authenticating web service that wants to
        *prevent some client to ask for a bulk of actions.
        *
        *As you may know, hashcash protocol is used by the Bitcoin network to generate
        *new bitcoins.
        *
        *
        *The idea behind the hashcash protocol is that the client 'pays' for our service
        *using her computing time, by solving a cryptological puzzle we propose to her, and
        *that we can dinamically set that 'payment' depending on how busy is our server.
        *
        *We are sending a 'puzzle' and a 'target' to the client, so she must find a 'nonce' that,
        *hashing our puzzle plus that nonce, the resultant hash starts with our proposed target.
        *each char we add to the target length augment the computational power needed to solve the
        *puzzle exponentially.
        *
        *With that in mind, we can send small targets when our server is free, letting the clients
        *to ask for services freely, and start to sending bigger targets when the server is busy,
        *preventing our server to perform too many actions when the load is heavy.
        *
        *We must store the puzzles we send to prevent clients for reuse solutions. The puzzles we
        *send with the target are digitally signed, so we do not need to track the target for our puzzles,
        *while assuring the target can't be tampered.
        *
        *This class uses the Hashcash_Validator and the Hashcash_Arbiter classes below to perform some actions.
        *
        *Keep in mind this class is just and example, and the current implementation of Hashcash_Arbiter
        *to calculate the proper target is almost basically senseless. Also, this algorithm
        *depends on a database keeping the solved puzzles we send, which of course depends heavily
        *on db access and schema implementation. In this code, we used a Fuelphp ORM like syntax for that, but of course
        *you should code your own way to store the puzzles and retrive them from a database.
        */
        class Hashcash_Puzzler {

        /**
        *Inner Hashcash_Arbiter from whom we get the target.
        *@Hashcash_Arbiter
        */
        private $arbiter;

        /**
        *a string wich client must incrementate with a nonce to match with the target
        *@string
        */
        private $puzzle;

        /**
        *The target: clients aims to get their proposed solution to start with this string
        *@string
        */
        private $target;


        /**
        *target + puzzle combo before digital signature
        */
        private $raw_package;

        /**
        *The whole, digitally signed package we send to the client.
        *@string
        */
        private $package;

        /**
        *
        *ssl secret key used to digitally firm the data that is sended to the client.
        *This prevents clients to reuse hashes or tamper the target.
        *@string
        */
        private $secret_key;

        /**
        *ssl public key to decript our signed package when validating client solution.
        */
        private $public_key;


        /**
        *Did some ssl operation fail?
        *@boolean
        */
        private $ssl_error = false;


        public function __construct(  ){

            $this->arbiter = new Puzzle_Arbiter();

            $this->secret_key = file_get_contents('private_key.pem');

            $this->public_key = file_get_contents('public_key.pem');

        }

        /**
        *Generates a puzzle by hashing a random number.
        */
        private function set_puzzle(){

            sleep(.001);
            $random_number = mt_rand( );

            $puzzle =  hash('sha256' , $random_number);

            $this->puzzle = $puzzle;
        }

        /**
        *Gets a target form the Hashcash_Arbiter
        */
        private function set_target(){

            $this->target = $this->arbiter->get_target();
        }

        /**
        *Merges the raw puzzle with the test
        */
        private function set_raw_package(){

            $this->raw_package = $this->target . "-" . $this->puzzle;
        }


        /**
        *Encrypts a puzzle with our secret key, digitally signing it. So we can be sure
        *no one sends a pre-computed solution when submits a solve.
        */
        private function sign_package(){

            if(openssl_private_encrypt($this->raw_package , $signed_puzzle , $this->secret_key ) )
                $this->package = $signed_puzzle;
            else
                $this->ssl_error = true;
        }


        /**
        *
        *Gets a new puzzle with given difficulty digitally signed.
        *@return string
        */
        public function get_puzzle_package(){

            $this->set_puzzle();
            $this->set_target();
            $this->set_raw_package();
            $this->sign_package();

            if(!$this->ssl_error){

                throw new Exception("Error with ssl signature", 1);
            }
            else{

                $this->save_puzzle();
                return $this->package;
            }
        }

        private function save_puzzle(){

            $puzzle = Model_Puzzle::forge();
            $puzzle->value = $this->puzzle;
            $puzzle->save();

        }

        /**
        *Validates if a solution is correct.
        */
        public function validate($client_data){

            $validator = new Hashcash_Validator( $client_data , $this->public_key );

            $valid =  $validator->validate();

            if($valid)
                $this->perform_action($client_data['action_data']);

            return $valid();
        }

        /**
        *If a solution from client is valid, perform some action
        *based on $action_data
        */
        private function perform_action($action_data ){

            //perform some cool action for the client :-)
        }


    } //end class Hashcash_Puzzler


    /**
    *
    *Class to check the correctness of a package sent by a client.
    */
    class Hashcash_Validator {

        /**
        *
        *data from the client. Has a 'nonce' field with the proposed solution from the client,
        *an 'original_package' field with the original data send to the client in origin,
        *adn an 'action_data' field to actually perform its request.
        *@array
        */
        private $solution_data;

        /**
        *our public_key, to decrypt the original package
        *and be sure data has not been tampered
        */
        private $public_key;

        /**
        *the nonce solution proposed by the client.
        *@string
        */
        private $nonce;


        /**
        *the target the client solution must match
        *@string
        */
        private $target;

        /**
        *original puzzle send to client.
        *@string
        */
        private $puzzle;




        public function __construct(  $solution_data , $public_key ){

                $this->public_key = $public_key;
                $this->solution_data = $solution_data;


        }


        /**
        *extracts all relevant data from the client package.
        */
        private function extract(){

            $this->nonce = $this->solution_data['nonce'];

            openssl_public_decrypt( $this->solution_data['original_package'] , $original_package , $this->public_key);

            $this->target = substr( $original_package , 0 ,  strpos( $original_package , '-') );

            $this->puzzle = substr($original_package , strpos($original_package, '-')+1 , (strlen($original_package) -1) );

        }

        /**
        *Assures given puzzle hasnt been computed before.
        */
        private function nonce_is_new(){

            return empty( Model_Puzzle::query()->where('value' , $this->puzzle )->get_one() );

        }


        /**
        *Checks if the hash of the puzzle plus the nonce matches with the needed difficulty.
        *@return boolean
        */
        private function nonce_is_valid(){

            $result = hash(   "sha256" , $this->puzzle . $this->nonce );

            return (strpos($result , $this->target) === 0) && $this->nonce_is_new() ;

        }


        /**
        *validates the nonce and returns the output.
        *@return boolean wether the solution is correct or not.
        */
        public function validate(){

            $this->extract();


            return $this->nonce_is_valid();

        }

    }//end class Hashcash_Checker


    /**
    *Determines the difficulty of each puzzle, based on system load.
    *Keep in mind this is an example about how hashcash works, and
    *php ssy_getloadavg() could not be a good method to get the server
    *load.
    */
    class Puzzle_Arbiter{

        private $cpu_load = null;

        /**
        *sets cpu load based on sysgetloadavg
        *@float
        */
        private function set_cpu_load(){

            $load = sys_getloadavg();
                $this->cpu_load = $load[0];

        }


        /**
        *gets the length of the target
        *@int
        */
        private function get_difficulty(){

            if($this->cpu_load > 1)
                return 10;

            if($this->cpu_load > 0.9)
                return 9;

            if($this->cpu_load > 0.80)
                return 8;

            if($this->cpu_load > 0.70)
                return 7;

            if($this->cpu_load > 0.6)
                return 6;

            if($this->cpu_load > 0.5)
                return 4;

            if($this->cpu_load > 0.4)
                return 2;


        }


        /**
        *returns the target
        *@return string
        */
        public function get_target(){

            $this->set_cpu_load();

            $difficulty = $this->get_difficulty();
            $target = "";
            for($i = 0 ; $i < $difficulty; $i++)
                $target .= "0";

            return $target;
        }


    }//end class Hashcash_Arbiter

    ?>
