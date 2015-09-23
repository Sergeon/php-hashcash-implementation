<?php

    /**
    *Represents a client solving a hashcash puzzle in order to ask for
    *some service to a server.
    */
    class Hashcash_Client {

        /**
        *ssl public key of the server needed to decrypt the puzzle.
        *@string
        */
        public $public_key;


        /**
        *A string -usally a hash- from which we need to get the zeroes hash by addinga nonce.
        *@string
        */
        private $puzzle;

        /**
        *a string that must be the start of our computed solution.
        *@string
        */
        private $target;

        /**
        *the nonce that generates the solution by hashing the puzzle plus the nonce
        */
        private $nonce;

        private $puzzle_data;

        /**
        *Original data retrieved from server
        */
        private $request;


        public function __construct( $puzzle_package ){
            $this->public_key = file_get_contents('public_key.pem');
            $this->request = $puzzle_package;

            //decrypt the server package into $this->puzzle_data
            openssl_public_decrypt( $puzzle_package , $this->puzzle_data , $this->public_key );

            $this->set_target();
            $this->set_puzzle();


        }

        /**
        *Exctracts the target from the package.
        */
        private function set_target(){

            $this->target = substr( $this->puzzle_data ,0,  strpos($this->puzzle_data , '-') );

        }

        /**
        *extracts the puzzle from the package.
        */
        private function set_puzzle(){
            $this->puzzle = substr($this->puzzle_data , strpos($this->puzzle_data , '-')+1 , (strlen($this->puzzle_data) -1) );
        }

        /**
        *Finds a hash that match with the test by adding a nonce to the puzzle, then sets the nonce.
        *@return boolean
        */
        private function solve(){
            $i = 0;
            while( true){

                $proposal = hash( 'sha256' , $this->puzzle . $i );

                if( $this->is_valid($proposal)){
                    $this->nonce = $i;
                    return true;
                }

                $i++;
            }
            return false;
        }

        /**
        *wether given solution matchs with the target
        *@return boolean
        */
        private function is_valid($proposal){

            return strpos( $proposal , $this->target)  === 0 ;

        }

        /**
        *return the nonce
        *@return string
        */
        private function get_nonce(){
            return $this->nonce;
        }

        /**
        *return the puzzle
        *@return string
        */
        private function get_puzzle(){

            return $this->puzzle();
        }

        /**
        *return the original puzzle package
        *@return string
        */
        private function get_original_request(){

            return $this->request;
        }


        public function get_solution(){

            $this->solve();

            $result = array();
            $result['nonce'] = $this->nonce;
            $result['original_package'] = $this->request;

            /**
            *In a real-world scenario, this data would be used by the
            *server to perform the action we are asking.
            */
            $result['action_data'] = "gimme pizza";

            return $result;

        }




    }//end class Hashcash_Client

?>
